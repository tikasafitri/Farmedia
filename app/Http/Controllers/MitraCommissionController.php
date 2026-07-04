<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\CommissionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MitraCommissionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'mitra') {
            abort(403, 'Hanya mitra yang dapat mengakses halaman ini.');
        }

        $mitraId = (int) $user->mitra_id;

        // Komisi produk (buat keterangan di UI saja)
        $commissionPercent = 10;

        // Periode default: 30 hari terakhir
        $to   = $request->input('to');
        $from = $request->input('from');

        if (!$from || !$to) {
            $to   = now()->toDateString();
            $from = now()->subDays(30)->toDateString();
        }

        // Query dasar: order selesai milik mitra ini, dalam periode
        $baseQuery = Order::with(['user', 'items.product'])
            ->where('mitra_id', $mitraId)
            ->where('status_order', 'selesai')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        // Ambil invoice aktif (kalau ada)
        $activeInvoice = CommissionInvoice::query()
            ->where('mitra_id', $mitraId)
            ->whereIn('status', ['unpaid', 'waiting_verification'])
            ->latest()
            ->first();

        $invoiceStatus   = $activeInvoice?->status;
        $invoicePenalty  = 0;
        $invoiceTotalDue = 0;
        $invoiceDueDate  = $activeInvoice?->due_date;
        $invoiceOverdue  = false;

        if ($activeInvoice) {
            // pakai method dari model kalau kamu sudah copas model CommissionInvoice yang kemarin
            if (method_exists($activeInvoice, 'computedPenalty')) {
                $invoicePenalty  = (float) $activeInvoice->computedPenalty();
                $invoiceTotalDue = (float) $activeInvoice->computedTotalDue();
                $invoiceOverdue  = (bool) $activeInvoice->isOverdue();
            } else {
                // fallback: kalau model belum ada computedPenalty()
                $invoicePenalty  = (float) ($activeInvoice->penalty ?? 0);
                $invoiceTotalDue = (float) ($activeInvoice->total_due ?? ((float)$activeInvoice->amount + $invoicePenalty));
                $invoiceOverdue  = $activeInvoice->due_date ? now()->gt($activeInvoice->due_date) : false;
            }
        }

        // Untuk daftar di layar (paginate)
        $orders = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        // Untuk ringkasan total
        $allOrders = $baseQuery->get();

        // Hutang komisi = order selesai milik mitra ini yang:
        // - metode pengiriman pickup / ambil_di_toko
        // - metode pembayaran cash
        // - komisi belum lunas
        $outstandingOrders = $allOrders->filter(function ($o) {
            $isPickup = in_array($o->metode_pengiriman, ['pickup', 'ambil_di_toko']);
            $isCash   = (strtolower((string) $o->metode_pembayaran) === 'cash');
            $belumLunas = is_null($o->komisi_lunas_at);

            return $isPickup && $isCash && $belumLunas;
        });

        // Total hutang komisi (produk + ongkir)
        $totalOutstanding = (float) $outstandingOrders->sum(function ($o) {
            return (float) $o->komisi_produk + (float) $o->komisi_ongkir;
        });

        $totalOrdersCount = $allOrders->count();

        // nilai produk = total - ongkir
        $totalProduct = (float) $allOrders->sum(function ($order) {
            return max(0, (float)$order->total_harga - (float)$order->ongkir);
        });

        // total komisi = komisi_produk + komisi_ongkir
        $totalKomisiProduk = (float) $allOrders->sum('komisi_produk');
        $totalKomisiOngkir = (float) $allOrders->sum('komisi_ongkir');
        $totalCommission   = $totalKomisiProduk + $totalKomisiOngkir;

        // pendapatan bersih mitra = total dibayar pembeli - komisi platform
        $totalNet = (float) $allOrders->sum(function ($order) {
            return (float)$order->total_harga - ((float)$order->komisi_produk + (float)$order->komisi_ongkir);
        });

        return view('mitra.commission.index', compact(
            'orders',
            'from',
            'to',
            'commissionPercent',
            'totalProduct',
            'totalCommission',
            'totalNet',
            'totalOrdersCount',
            'totalOutstanding',
            // ===== tambahan untuk UI bayar hutang =====
            'activeInvoice',
            'invoiceStatus',
            'invoicePenalty',
            'invoiceTotalDue',
            'invoiceDueDate',
            'invoiceOverdue',
        ));
    }
}
