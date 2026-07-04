<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['user','mitra','items'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['user','mitra','items.product']);
        return view('admin.orders.show', compact('order'));
    }

    public function ship(Order $order)
    {
        // hanya bisa ship kalau sudah siap_dikirim + delivery
        if ($order->metode_pengiriman !== 'delivery' || $order->status_order !== 'siap_dikirim') {
            return back()->with('error', 'Order belum siap dikirim.');
        }

        // generate resi otomatis kalau belum ada
        if (empty($order->nomor_resi)) {
            $order->nomor_resi = \App\Models\Order::generateResi();
        }

        $order->status_order = 'dikirim';
        $order->save();

        return back()->with('success', 'Pesanan dikirim. Nomor resi berhasil dibuat.');
    }

    public function finish(Order $order)
    {
        // admin selesaiin hanya kalau delivery sudah dikirim
        if ($order->metode_pengiriman !== 'delivery' || $order->status_order !== 'dikirim') {
            return back()->with('error', 'Order belum dalam status dikirim.');
        }

        $order->update(['status_order' => 'selesai']);

        return back()->with('success', 'Pesanan ditandai selesai.');
    }

    public function printResi(Order $order)
    {
        // hanya boleh cetak kalau sudah ada nomor resi
        if (empty($order->nomor_resi)) {
            return back()->with('error', 'Resi belum dibuat.');
        }

        $order->load(['user','mitra','items.product']);
        return view('admin.orders.print_resi', compact('order'));
    }

    public function approveTransfer(Order $order)
{
    // hanya untuk transfer
    if ($order->metode_pembayaran !== 'transfer') {
        return back()->with('error', 'Metode pembayaran bukan transfer.');
    }

    // harus ada bukti
    if (empty($order->payment_proof) && empty($order->payment_proof_path)) {
        return back()->with('error', 'Bukti transfer belum ada.');
    }

    // harus waiting_verification
    if ($order->payment_status !== 'waiting_verification') {
        return back()->with('error', 'Status pembayaran tidak valid untuk approve.');
    }

    DB::transaction(function () use ($order) {
        $order->update([
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        // kalau kamu punya logika escrow/komisi, taruh di sini nanti
    });

    return back()->with('success', 'Pembayaran transfer disetujui. Status menjadi PAID.');
}
}
