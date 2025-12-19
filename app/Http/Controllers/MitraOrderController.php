<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Mitra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MitraOrderController extends Controller
{
    /**
     * Daftar pesanan untuk mitra.
     */
    public function index(Request $request)
{
    $mitraId = Auth::user()->mitra_id;

    // view: aktif | selesai | dibatalkan | semua (default: aktif)
    $view = $request->query('view', 'aktif');

    $query = Order::where('mitra_id', $mitraId)
        ->with(['user', 'items.product'])
        ->orderBy('created_at', 'desc');

    // Filter sesuai tab yang dipilih
    if ($view === 'aktif') {
        $query->whereIn('status_order', [
            'menunggu_konfirmasi',
            'confirmed',       // kalau ada data lama
            'diproses',
            'dikemas',
            'dikirim',
            'siap_diambil',
            'pending_cancel',  // kalau kamu pakai status ini
            'siap_dikirim',
        ]);
    } elseif ($view === 'selesai') {
        $query->where('status_order', 'selesai');
    } elseif ($view === 'dibatalkan') {
        $query->whereIn('status_order', ['rejected', 'dibatalkan']);
    }
    // kalau view === 'semua' → tanpa filter status, biarkan saja

    $orders = $query->get(); // tetap pakai get() seperti kode kamu semula

    return view('mitra.orders.index', [
        'orders' => $orders,
        'view'   => $view,
    ]);
}

    /**
     * Halaman detail pesanan mitra.
     */
    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'mitra']);

        return view('mitra.orders.show', compact('order'));
    }

    /**
     * Konfirmasi pesanan → sekarang menjadi “diproses”.
     */
    public function confirm(Order $order)
    {
        if ((int)auth()->user()->mitra_id !== (int)$order->mitra_id) abort(403);

        $order->status_order = 'diproses';
        $order->save();

        return redirect()->route('mitra.orders.index')
                         ->with('success', 'Pesanan dikonfirmasi dan masuk tahap diproses.');
    }

    /**
     * Tolak pesanan.
     */
    public function reject(Order $order)
    {
        $order->status_order = 'rejected';
        $order->save();

        return redirect()->route('mitra.orders.index')
                         ->with('success', 'Pesanan berhasil ditolak.');
    }

    /**
     * Melanjutkan status ke tahap berikutnya (otomatis).
     */
    public function nextStep(Order $order)
{
    // guard kepemilikan
    if ((int)auth()->user()->mitra_id !== (int)$order->mitra_id) abort(403);

    // Mode delivery (mitra STOP di siap_dikirim)
    $deliveryFlow = ['menunggu_konfirmasi', 'diproses', 'dikemas', 'siap_dikirim'];

    // Mode pickup
    $pickupFlow = ['menunggu_konfirmasi', 'diproses', 'siap_diambil', 'selesai'];

    $flow = $order->metode_pengiriman === 'pickup' ? $pickupFlow : $deliveryFlow;

    $currentIndex = array_search($order->status_order, $flow);

    if ($currentIndex !== false && $currentIndex < count($flow) - 1) {
        $order->status_order = $flow[$currentIndex + 1];
        $order->save();
    }

    return back()->with('success', 'Status pesanan berhasil diperbarui.');
}
    public function pack(Order $order)
{
    if ($order->metode_pengiriman !== 'delivery') {
        return back()->with('error', 'Pesanan ini bukan pengiriman.');
    }

    $order->status_order = 'dikemas';
    $order->save();

    return back()->with('success', 'Pesanan ditandai sebagai sedang dikemas.');
}

public function readyPickup(Order $order)
{
    if ($order->metode_pengiriman !== 'pickup') {
        return back()->with('error', 'Pesanan ini bukan metode ambil di toko.');
    }

    $order->status_order = 'siap_diambil';
    $order->save();

    return back()->with('success', 'Pesanan siap diambil pembeli.');
}

public function finish(Order $order)
{
    if ((int)auth()->user()->mitra_id !== (int)$order->mitra_id) abort(403);

    // Pickup boleh selesai oleh mitra
    if ($order->metode_pengiriman === 'pickup') {
        $order->status_order = 'selesai';
        $order->save();
        return back()->with('success', 'Pesanan telah ditandai selesai.');
    }

    // Delivery tidak boleh selesai oleh mitra
    return back()->with('error', 'Pesanan delivery diselesaikan oleh admin setelah kurir mengonfirmasi.');
}

public function approveCancel(Order $order)
{
    if (auth()->user()->mitra_id !== $order->mitra_id) {
        abort(403);
    }

    $order->update([
        'status_order' => 'dibatalkan'
    ]);

    return back()->with('success', 'Pembatalan pesanan telah disetujui.');
}


public function rejectCancel(Order $order)
{
    if (auth()->user()->mitra_id !== $order->mitra_id) {
        abort(403);
    }

    // Kembalikan ke status sebelum pending_cancel
    $previous = $order->status_order_before ?? 'menunggu_konfirmasi';

    $order->update([
        'status_order' => $previous
    ]);

    return back()->with('success', 'Permintaan pembatalan ditolak.');
}

public function confirmPayment(Order $order)
{
    if (auth()->user()->mitra_id !== $order->mitra_id) {
        abort(403);
    }

    if (in_array($order->status_order, ['dibatalkan', 'rejected'])) {
        return back()->with('error', 'Pesanan sudah dibatalkan / ditolak, tidak bisa konfirmasi pembayaran.');
    }

    if ($order->metode_pembayaran !== 'transfer') {
        return back()->with('error', 'Metode pembayaran bukan transfer bank.');
    }

    $order->update([
        'payment_status' => 'paid',
    ]);

    return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
}

public function readyShip(Order $order)
{
    // pastikan order milik mitra login (kalau kamu pakai)
    if ((int) auth()->user()->mitra_id !== (int) $order->mitra_id) abort(403);

    // generate resi kalau belum ada
    if (empty($order->nomor_resi)) {
        $order->nomor_resi = 'FM-' . now()->format('ymd') . '-' . str_pad((string)$order->id, 6, '0', STR_PAD_LEFT);
    }

    $order->status_order = 'siap_dikirim';
    $order->save();

    return redirect()->route('mitra.orders.show', $order->id)->with('success', 'Pesanan siap dikirim & resi dibuat.');
}


public function printResi(Order $order)
{
    // optional: pastikan order ini milik mitra yang login
    if ((int) auth()->user()->mitra_id !== (int) $order->mitra_id) {
        abort(403);
    }

    if (empty($order->nomor_resi)) {
    return redirect()
        ->route('mitra.orders.show', $order->id)
        ->with('error', 'Resi belum dibuat. Klik "Pesanan Siap Dikirim" dulu untuk membuat resi.');
}

    $order->load(['user','mitra','items.product']);

    return view('shared.print_resi', compact('order'));
}

}
