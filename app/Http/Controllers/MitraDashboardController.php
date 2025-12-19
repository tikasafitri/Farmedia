<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MitraDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $mitraId = $user->mitra_id;

        // Periode default: 30 hari terakhir
        $from = Carbon::now()->subDays(30)->startOfDay();
        $to   = Carbon::now()->endOfDay();

        // Semua order selesai untuk periode ini
        $orders = Order::with('items')
            ->where('mitra_id', $mitraId)
            ->where('status_order', 'selesai')
            ->whereBetween('updated_at', [$from, $to])
            ->get();

        $totalSales   = 0;   // total omzet
        $totalOrders  = $orders->count();
        $totalQty     = 0;

        foreach ($orders as $order) {
            $orderTotal = 0;

            foreach ($order->items as $item) {
                $subtotal   = $item->subtotal ?? ($item->jumlah * $item->harga_satuan);
                $orderTotal += $subtotal;
                $totalQty   += $item->jumlah;
            }

            $totalSales += $orderTotal;
        }

        // Produk aktif = produk milik mitra dengan stok > 0
        $activeProducts = Product::where('mitra_id', $mitraId)
            ->where('stok', '>', 0)
            ->count();

        // Komisi (sementara diasumsikan 10%)
        $commissionPercent = 10;
        $netRevenue = $totalSales * (1 - $commissionPercent / 100);

        // ================= GRAFIK 7 HARI TERAKHIR =================
        $startChart = Carbon::now()->subDays(6)->startOfDay(); // 7 hari (hari ini + 6 hari ke belakang)

        $chartLabels     = [];
        $salesPerDayMap  = [];
        $ordersPerDayMap = [];

        // Inisialisasi semua hari dengan 0
        for ($i = 0; $i < 7; $i++) {
            $dateObj = $startChart->copy()->addDays($i);
            $key     = $dateObj->format('Y-m-d');

            $chartLabels[]           = $dateObj->format('d M');
            $salesPerDayMap[$key]    = 0;
            $ordersPerDayMap[$key]   = 0;
        }

        // Hitung data per hari
        foreach ($orders as $order) {
    // pakai updated_at sebagai waktu "selesai"
    if ($order->updated_at->lt($startChart)) {
        continue;
    }

    $key = $order->updated_at->format('Y-m-d');

    if (! array_key_exists($key, $salesPerDayMap)) {
        continue;
    }

    $orderTotal = 0;
    foreach ($order->items as $item) {
        $subtotal    = $item->subtotal ?? ($item->jumlah * $item->harga_satuan);
        $orderTotal += $subtotal;
    }

    $salesPerDayMap[$key]  += $orderTotal;
    $ordersPerDayMap[$key] += 1;
}

        $chartSales  = array_values($salesPerDayMap);
        $chartOrders = array_values($ordersPerDayMap);

        $periodText = $from->format('d M Y') . ' - ' . $to->format('d M Y');

        return view('mitra.dashboard', [
            'totalSales'         => $totalSales,
            'totalOrders'        => $totalOrders,
            'totalQty'           => $totalQty,
            'activeProducts'     => $activeProducts,
            'netRevenue'         => $netRevenue,
            'commissionPercent'  => $commissionPercent,
            'periodText'         => $periodText,
            'chartLabels'        => $chartLabels,
            'chartSales'         => $chartSales,
            'chartOrders'        => $chartOrders,
        ]);
    }
}
