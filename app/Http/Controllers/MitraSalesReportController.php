<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Order;
use App\Models\CodSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MitraSalesReportController extends Controller
{
    public function index(Request $request)
    {
        $mitraId = Auth::user()->mitra_id;

        $from = $request->input('from');
        $to   = $request->input('to');

        // default 30 hari terakhir
        if (!$from && !$to) {
            $from = now()->subDays(30)->toDateString();
            $to   = now()->toDateString();
        }

        $fromDt = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        $toDt   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();

        // Base: hanya order selesai milik mitra
        $baseQuery = Order::with(['items.product', 'user', 'codSettlement'])
            ->where('mitra_id', $mitraId)
            ->where('status_order', 'selesai');

        /**
         * FILTER PERIODE YANG AMAN:
         * - kalau selesai_at ada → pakai selesai_at
         * - kalau selesai_at NULL → fallback updated_at
         */
        $filteredQuery = (clone $baseQuery)->where(function ($q) use ($fromDt, $toDt) {
            $q->where(function ($qq) use ($fromDt, $toDt) {
                $qq->whereNotNull('selesai_at')
                   ->whereBetween('selesai_at', [$fromDt, $toDt]);
            })->orWhere(function ($qq) use ($fromDt, $toDt) {
                $qq->whereNull('selesai_at')
                   ->whereBetween('updated_at', [$fromDt, $toDt]);
            });
        });

        // Ambil semua untuk ringkasan + chart
        $allOrders = (clone $filteredQuery)->get();

        $totalRevenue = $allOrders->sum(fn($order) => $order->items->sum('subtotal'));
        $totalOrders  = $allOrders->count();
        $totalItems   = $allOrders->sum(fn($order) => $order->items->sum('jumlah'));

        // ===== GRAFIK (lengkap per hari, pakai tanggal selesai efektif) =====
        $period = CarbonPeriod::create($fromDt->copy()->startOfDay(), $toDt->copy()->startOfDay());

        // mapping omzet per "tanggal efektif selesai" (selesai_at jika ada, else updated_at)
        $dailyMap = $allOrders
            ->groupBy(function ($o) {
                $dt = $o->selesai_at ?? $o->updated_at;
                return optional($dt)->toDateString();
            })
            ->map(fn($orders) => $orders->sum(fn($o) => $o->items->sum('subtotal')));

        $chartLabels = collect();
        $chartValues = collect();

        foreach ($period as $date) {
            $key = $date->toDateString();
            $chartLabels->push($date->format('d M'));
            $chartValues->push((float) ($dailyMap[$key] ?? 0));
        }

        // ===== LIST TRANSAKSI (urut terbaru selesai) =====
        // OrderBy: selesai_at desc lalu updated_at desc (fallback)
        $orders = (clone $filteredQuery)
            ->orderByRaw('COALESCE(selesai_at, updated_at) DESC')
            ->paginate(10)
            ->withQueryString();

        // ===== Pencairan COD (uang masuk rekening mitra dari admin) =====
        $codPayoutTotal = CodSettlement::query()
            ->where('status', 'paid')
            ->whereBetween('payout_at', [$fromDt, $toDt])
            ->whereHas('order', function ($q) use ($mitraId) {
                $q->where('mitra_id', $mitraId)
                  ->where('metode_pembayaran', 'cod');
            })
            ->sum('net_to_seller');

        $codPayoutCount = CodSettlement::query()
            ->where('status', 'paid')
            ->whereBetween('payout_at', [$fromDt, $toDt])
            ->whereHas('order', function ($q) use ($mitraId) {
                $q->where('mitra_id', $mitraId)
                  ->where('metode_pembayaran', 'cod');
            })
            ->count();

        return view('mitra.sales.index', [
            'orders'         => $orders,
            'from'           => $from,
            'to'             => $to,
            'totalRevenue'   => $totalRevenue,
            'totalOrders'    => $totalOrders,
            'totalItems'     => $totalItems,
            'chartLabels'    => $chartLabels,
            'chartValues'    => $chartValues,
            'codPayoutTotal' => $codPayoutTotal,
            'codPayoutCount' => $codPayoutCount,
        ]);
    }
}
