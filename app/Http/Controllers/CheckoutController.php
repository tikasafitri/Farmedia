<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    /**
     * Halaman checkout.
     */
    public function show(Product $product)
    {
        $user = Auth::user();

        return view('user.checkout.show', [
            'product' => $product,
            'user'    => $user,
        ]);
    }

    /**
     * Proses pesanan.
     */
    public function store(Request $request, Product $product)
    {
        $user = Auth::user();

        // === VALIDASI DASAR ===
        $validated = $request->validate([
            'jumlah'             => 'required|integer|min:1|max:' . $product->stok,
            'alamat_pengiriman'  => 'required|string|max:500',
            'metode_pengiriman'  => 'required|string|in:delivery,pickup',
            'metode_pembayaran'  => 'required|string',
            'ongkir'             => 'nullable|integer|min:0'
        ]);

        // ==============================
        //    VALIDASI LOGIKA TAMBAHAN
        // ==============================

        // Jika pickup → COD tidak boleh
        if ($validated['metode_pengiriman'] === 'pickup') {
            if (!in_array($validated['metode_pembayaran'], ['cash', 'transfer', 'qris'])) {
                return back()->withErrors(['metode_pembayaran' => 'COD tidak diperbolehkan untuk Ambil di Toko.']);
            }
        }

        // Jika delivery → Cash tidak boleh
        if ($validated['metode_pengiriman'] === 'delivery') {
            if (!in_array($validated['metode_pembayaran'], ['cod', 'transfer', 'qris'])) {
                return back()->withErrors(['metode_pembayaran' => 'Metode Pembayaran tidak valid untuk pengiriman kurir.']);
            }
        }

        // ==============================
        //       PERHITUNGAN HARGA
        // ==============================

        $jumlah = $validated['jumlah'];
        $subtotal = $product->harga * $jumlah;

        // Pickup = ongkir 0
        $ongkir = $validated['metode_pengiriman'] === 'pickup'
            ? 0
            : ($validated['ongkir'] ?? 0);

        $total = $subtotal + $ongkir;
        // Komisi platform
        $komisiProduk = $subtotal * 0.10; // 10% produk
        $komisiOngkir = $ongkir > 0 ? $ongkir * 0.05 : 0; // 5% ongkir

        // ==============================
        //        SIMPAN ORDER
        // ==============================

        // tentukan payment_status & payment_deadline
$paymentStatus   = 'pending';
$paymentDeadline = null;

// kalau metode = transfer bank → kasih deadline 24 jam
if ($validated['metode_pembayaran'] === 'transfer') {
    $paymentDeadline = now()->addDay(); // +1 hari dari sekarang
}

// kalau nanti mau QRIS dianggap langsung lunas, bisa diatur:
// if ($validated['metode_pembayaran'] === 'qris') {
//     $paymentStatus = 'paid';
// }

$order = Order::create([
    'user_id'            => $user->id,
    'mitra_id'           => $product->mitra_id,
    'total_harga'        => $total,
    'ongkir'             => $ongkir,
    'metode_pembayaran'  => $validated['metode_pembayaran'],
    'alamat_pengiriman'  => $validated['alamat_pengiriman'],
    'metode_pengiriman'  => $validated['metode_pengiriman'],
    'status_order'       => 'menunggu_konfirmasi',

    // kolom baru
    'payment_status'     => $paymentStatus,
    'payment_deadline'   => $paymentDeadline,

    'komisi_produk'      => $komisiProduk,
    'komisi_ongkir'      => $komisiOngkir,
]);

        // ==============================
        //        SIMPAN ITEM ORDER
        // ==============================

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'jumlah'       => $jumlah,
            'harga_satuan' => $product->harga,
            'subtotal'     => $subtotal,
        ]);

        // Kurangi stok
        $product->decrement('stok', $jumlah);

        return redirect()
            ->route('orders.index')
            ->with('success', 'Pesanan berhasil dibuat!');
    }

    public function checkoutFromCart(Request $request)
{
    // Kalau tidak ada produk yang dipilih -> balik ke keranjang
    if (!$request->has('selected_items') || empty($request->selected_items)) {
        return redirect()
            ->route('user.cart.index')
            ->with('error', 'Pilih minimal satu produk yang ingin di-checkout.');
    }

    $cart        = session('cart', []);
    $items       = [];
    $selectedIds = [];

    foreach ($request->selected_items as $id) {
        if (isset($cart[$id])) {
            $items[$id]    = $cart[$id];
            $selectedIds[] = $id;
        }
    }

    // Safety: kalau ternyata semua ID tidak valid
    if (empty($items)) {
        return redirect()
            ->route('user.cart.index')
            ->with('error', 'Data keranjang tidak valid. Silakan coba lagi.');
    }

    return view('user.checkout.cart-checkout', [
        'items'       => $items,
        'selectedIds' => $selectedIds,
    ]);
}

public function processCart(Request $request)
{
    $request->validate([
        'alamat_pengiriman' => 'required|string|max:500',
        'metode_pengiriman' => 'required|in:delivery,pickup',
        'metode_pembayaran' => 'required|string',
        'ongkir'            => 'nullable|integer|min:0',
        'selected_ids'      => 'required|array',
    ]);

    $cart        = session('cart', []);
    $items       = [];
    $barangTotal = 0;

    foreach ($request->selected_ids as $id) {
        if (isset($cart[$id])) {
            $items[$id] = $cart[$id];
            $barangTotal += $cart[$id]['qty'] * $cart[$id]['harga'];
        }
    }

    if (empty($items)) {
        return redirect()->route('user.cart.index')
            ->with('error', 'Keranjang tidak valid, silakan coba lagi.');
    }

    $metodePengiriman = $request->metode_pengiriman;
    $metodePembayaran = $request->metode_pembayaran;

    // VALIDASI KOMBINASI seperti store() satu produk
    if ($metodePengiriman === 'pickup' &&
        !in_array($metodePembayaran, ['cash', 'transfer', 'qris'])) {

        return back()->withErrors([
            'metode_pembayaran' => 'COD tidak diperbolehkan untuk Ambil di Toko.',
        ]);
    }

    if ($metodePengiriman === 'delivery' &&
        !in_array($metodePembayaran, ['cod', 'transfer', 'qris'])) {

        return back()->withErrors([
            'metode_pembayaran' => 'Metode Pembayaran tidak valid untuk pengiriman kurir.',
        ]);
    }

    // Hitung ongkir (pickup = 0)
    $ongkir = $metodePengiriman === 'pickup'
        ? 0
        : ($request->ongkir ?? 0);

    $total = $barangTotal + $ongkir;

    $komisiProduk = $barangTotal * 0.10;
    $komisiOngkir = $ongkir > 0 ? $ongkir * 0.05 : 0;


    // Tentukan mitra dari produk pertama
    $firstProductId = array_key_first($items);
    $firstProduct   = Product::find($firstProductId);

    // tentukan payment_status & payment_deadline
$paymentStatus   = 'pending';
$paymentDeadline = null;

if ($metodePembayaran === 'transfer') {
    $paymentDeadline = now()->addDay();
}

$order = Order::create([
    'user_id'           => auth()->id(),
    'mitra_id'          => $firstProduct ? $firstProduct->mitra_id : null,
    'total_harga'       => $total,
    'ongkir'            => $ongkir,
    'metode_pembayaran' => $metodePembayaran,
    'alamat_pengiriman' => $request->alamat_pengiriman,
    'metode_pengiriman' => $metodePengiriman,
    'status_order'      => 'menunggu_konfirmasi',

    // kolom baru
    'payment_status'    => $paymentStatus,
    'payment_deadline'  => $paymentDeadline,

    'komisi_produk'     => $komisiProduk,
    'komisi_ongkir'     => $komisiOngkir,
]);

    // Simpan item-item pesanan & kurangi stok
    foreach ($items as $productId => $item) {
        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $productId,
            'jumlah'       => $item['qty'],
            'harga_satuan' => $item['harga'],
            'subtotal'     => $item['qty'] * $item['harga'],
        ]);

        Product::where('id', $productId)->decrement('stok', $item['qty']);
    }

    // Hapus item yang sudah di-checkout dari keranjang
    foreach ($request->selected_ids as $id) {
        unset($cart[$id]);
    }
    session()->put('cart', $cart);

    return redirect()->route('orders.index')
        ->with('success', 'Pesanan dari keranjang berhasil dibuat.');
}

}
