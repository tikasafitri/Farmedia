<?php

namespace App\Http\Controllers;

use App\Models\CommissionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCommissionInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'waiting_verification');

        $invoices = CommissionInvoice::query()
            ->with(['mitra'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.commission_invoices.index', compact('invoices', 'status'));
    }

    public function show(CommissionInvoice $invoice)
    {
        $invoice->load(['mitra', 'orders.items.product', 'orders.user']);
        return view('admin.commission_invoices.show', compact('invoice'));
    }

    public function approve(CommissionInvoice $invoice)
    {
        if ($invoice->status !== 'waiting_verification') {
            return back()->with('error', 'Invoice tidak dalam status verifikasi.');
        }

        DB::transaction(function () use ($invoice) {
            // tandai lunas
            $invoice->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            // semua order di invoice -> komisi_lunas = true
            $orderIds = $invoice->orders()->pluck('orders.id')->all();
            \App\Models\Order::whereIn('id', $orderIds)->update([
                'komisi_lunas_at' => now(),
                ]);
        });

        return redirect()->route('admin.commission_invoices.index', ['status' => 'waiting_verification'])
            ->with('success', 'Pembayaran disetujui. Komisi ditandai lunas.');
    }

    public function reject(Request $request, CommissionInvoice $invoice)
    {
        if ($invoice->status !== 'waiting_verification') {
            return back()->with('error', 'Invoice tidak dalam status verifikasi.');
        }

        $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $invoice->update([
            'status' => 'unpaid',
            'notes'  => $request->notes,
        ]);

        return back()->with('success', 'Pembayaran ditolak. Invoice dikembalikan ke status unpaid.');
    }
}
