<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\CodSettlement;
use Carbon\CarbonPeriod;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MitraSalesReportController extends Controller
{
    public function index(Request $request)
{
    $mitraId = Auth::user()->mitra_id;

    $from = $request->input('from');
    $to   = $request->input('to');

    if (!$from && !$to) {
        $from = now()->subDays(30)->toDateString();
        $to   = now()->toDateString();
    }

    $baseQuery = Order::with(['items.product', 'user', 'codSettlement'])
        ->where('mitra_id', $mitraId)
        ->where('status_order', 'selesai');

    $filteredQuery = (clone $baseQuery)
        ->when($from, fn($q) => $q->whereDate('selesai_at', '>=', $from))
        ->when($to,   fn($q) => $q->whereDate('selesai_at', '<=', $to));

    $allOrders = (clone $filteredQuery)->get();

    $totalRevenue = $allOrders->sum(fn($order) => $order->items->sum('subtotal'));
    $totalOrders  = $allOrders->count();
    $totalItems   = $allOrders->sum(fn($order) => $order->items->sum('jumlah'));

    // ===== GRAFIK (lengkap per hari) =====
    $start  = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
    $end    = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();
    $period = CarbonPeriod::create($start, $end);

    // omzet per tanggal selesai (selesai_at)
    $dailyMap = $allOrders
        ->groupBy(fn($o) => optional($o->selesai_at)->toDateString())
        ->map(fn($orders) => $orders->sum(fn($o) => $o->items->sum('subtotal')));

    $chartLabels = collect();
    $chartValues = collect();

    foreach ($period as $date) {
        $key = $date->toDateString();
        $chartLabels->push($date->format('d M'));
        $chartValues->push((float) ($dailyMap[$key] ?? 0));
    }

    // ===== LIST TRANSAKSI =====
    $orders = $filteredQuery
        ->orderByDesc('selesai_at')
        ->paginate(10)
        ->withQueryString();

        // ===== PENCairan COD (uang masuk rekening mitra dari admin) =====
$fromDt = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
$toDt   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();

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
        'orders'       => $orders,
        'from'         => $from,
        'to'           => $to,
        'totalRevenue' => $totalRevenue,
        'totalOrders'  => $totalOrders,
        'totalItems'   => $totalItems,
        'chartLabels'  => $chartLabels,
        'chartValues'  => $chartValues,
        'codPayoutTotal' => $codPayoutTotal,
        'codPayoutCount' => $codPayoutCount,
    ]);
}
}