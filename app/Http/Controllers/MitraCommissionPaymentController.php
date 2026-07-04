<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\CommissionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MitraCommissionPaymentController extends Controller
{
    private function outstandingOrdersQuery(int $mitraId)
    {
    return Order::query()
        ->where('mitra_id', $mitraId)
        ->where('status_order', 'selesai')
        ->whereRaw('LOWER(metode_pembayaran) = ?', ['cash'])
        ->whereIn('metode_pengiriman', ['pickup', 'ambil_di_toko'])
        ->whereNull('komisi_lunas_at'); // ✅ ini
    }

    private function getOrCreateActiveInvoice(int $mitraId): ?CommissionInvoice
    {
        $active = CommissionInvoice::query()
            ->where('mitra_id', $mitraId)
            ->whereIn('status', ['unpaid', 'waiting_verification'])
            ->latest()
            ->first();

        // kalau masih ada aktif, pakai itu
        if ($active) return $active;

        $orders = $this->outstandingOrdersQuery($mitraId)->get();
        if ($orders->isEmpty()) return null;

        $amount = $orders->sum(fn($o) => (float)$o->komisi_produk + (float)$o->komisi_ongkir);

        // due date: max(selesai_at) + 3 hari (fallback created_at)
        $maxFinish = $orders->max(fn($o) => $o->selesai_at ?? $o->updated_at ?? $o->created_at);
        $dueDate = ($maxFinish ? \Carbon\Carbon::parse($maxFinish) : now())->addDays(3);

        return DB::transaction(function () use ($mitraId, $orders, $amount, $dueDate) {
            $invoice = CommissionInvoice::create([
                'mitra_id'  => $mitraId,
                'amount'    => (float)$amount,
                'penalty'   => 0,
                'total_due' => (float)$amount,
                'due_date'  => $dueDate,
                'status'    => 'unpaid',
            ]);

            $invoice->orders()->attach($orders->pluck('id')->all());

            return $invoice;
        });
    }

    // GET /mitra/komisi/bayar
    public function payForm()
    {
        $mitraId = (int) Auth::user()->mitra_id;

        $invoice = $this->getOrCreateActiveInvoice($mitraId);

        if (!$invoice) {
            return redirect()->route('mitra.commission.index')
                ->with('success', 'Tidak ada hutang komisi yang perlu dibayar.');
        }

        // update snapshot penalty+total_due supaya fix saat mitra submit
        $penalty = $invoice->computedPenalty();
        $invoice->update([
            'penalty'   => $penalty,
            'total_due' => (float)$invoice->amount + (float)$penalty,
        ]);

        $invoice->load(['orders.items.product']);

        // rekening platform (bisa kamu pindahkan ke config/env)
        $bank = [
            'nama'   => 'BCA',
            'nomor'  => '1234567890',
            'pemilik'=> 'FARMEDIA',
        ];

        return view('mitra.commission.pay', compact('invoice', 'bank'));
    }

    // POST /mitra/komisi/bayar
    public function submitPayment(Request $request)
    {
        $mitraId = (int) Auth::user()->mitra_id;
        $invoice = CommissionInvoice::query()
            ->where('mitra_id', $mitraId)
            ->whereIn('status', ['unpaid', 'waiting_verification'])
            ->latest()
            ->firstOrFail();

        $request->validate([
            'payment_method' => ['required', 'in:transfer'],
            'payment_proof'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('payment_proof')->store('commission_proofs', 'public');

        // refresh penalty saat submit (biar fair)
        $penalty = $invoice->computedPenalty();
        $totalDue = (float)$invoice->amount + (float)$penalty;

        $invoice->update([
            'payment_method'      => $request->payment_method,
            'payment_proof_path'  => $path,
            'notes'               => $request->notes,
            'penalty'             => $penalty,
            'total_due'           => $totalDue,
            'status'              => 'waiting_verification',
        ]);

        return redirect()->route('mitra.commission.index')
            ->with('success', 'Bukti pembayaran terkirim. Menunggu verifikasi admin.');
    }
}
