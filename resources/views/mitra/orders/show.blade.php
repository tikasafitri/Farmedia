<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detail Pesanan
                </h2>
                <p class="text-sm text-gray-500">
                    Kode pesanan <span class="font-mono font-semibold">ORD-{{ $order->id }}</span>
                </p>
            </div>

            <div class="flex items-center gap-3 text-xs text-gray-500">
                <span>Dibuat: {{ $order->created_at?->format('d M Y, H:i') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- STATUS --}}
            @php
                $status = $order->status_order;
                $statusLabel = [
                    'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
                    'diproses'            => 'Diproses',
                    'dikemas'             => 'Dikemas',
                    'siap_dikirim'        => 'Siap Dikirim',
                    'dikirim'             => 'Dikirim',
                    'siap_diambil'        => 'Siap Diambil',
                    'selesai'             => 'Selesai',
                    'rejected'            => 'Ditolak',
                    'dibatalkan'          => 'Dibatalkan',
                    'pending_cancel'      => 'Menunggu Pembatalan',
                ][$status] ?? 'Status Tidak Dikenal';

                $statusColor = match ($status) {
                    'menunggu_konfirmasi' => 'bg-amber-50 text-amber-700 border-amber-100',
                    'diproses','dikemas','siap_dikirim','dikirim','siap_diambil' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                    'selesai'   => 'bg-sky-50 text-sky-700 border-sky-100',
                    'rejected','dibatalkan' => 'bg-rose-50 text-rose-700 border-rose-100',
                    'pending_cancel' => 'bg-orange-50 text-orange-700 border-orange-100',
                    default     => 'bg-slate-50 text-slate-700 border-slate-200',
                };
            @endphp

            <div class="bg-white border border-emerald-50 shadow-sm rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">
                        Status Pesanan
                    </p>
                    <div class="mt-2 inline-flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $statusColor }}">
                            {{ $statusLabel }}
                        </span>

                        @if($order->metode_pengiriman === 'pickup')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
                                Ambil di Toko
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
                                Dikirim ke Alamat
                            </span>
                        @endif
                    </div>
                </div>

                <div class="text-xs text-gray-500 space-y-0.5">
                    <p>Metode Pembayaran: <span class="font-medium text-gray-700">{{ strtoupper($order->metode_pembayaran) }}</span></p>
                    <p>Total Dibayar:
                        <span class="font-semibold text-emerald-700">
                            Rp {{ number_format($order->total_harga, 0, ',', '.') }}
                        </span>
                    </p>
                </div>
            </div>

{{-- AKSI PENJUAL --}}
<div class="bg-white border border-emerald-50 shadow-sm rounded-2xl p-5">
    <h3 class="text-sm font-semibold text-gray-800 mb-3">Aksi Penjual</h3>

    @if ($order->status_order === 'pending_cancel')
        <p class="text-sm text-gray-500 mb-3">
            Pembeli sedang mengajukan <span class="font-semibold">pembatalan pesanan</span>.
            Silakan pilih apakah ingin menyetujui atau menolak.
        </p>

        <div class="bg-orange-50 border border-orange-200 rounded-2xl px-5 py-4">
            <p class="font-semibold text-orange-800 text-sm">
                Pembeli mengajukan pembatalan pesanan.
            </p>

            <div class="mt-3 flex flex-col sm:flex-row gap-2">
                <form action="{{ route('mitra.orders.cancel.approve', $order->id) }}" method="POST">
                    @csrf
                    <button class="w-full sm:w-auto px-4 py-2 rounded-full bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                        Setujui Pembatalan
                    </button>
                </form>

                <form action="{{ route('mitra.orders.cancel.reject', $order->id) }}" method="POST">
                    @csrf
                    <button class="w-full sm:w-auto px-4 py-2 rounded-full border border-emerald-600 bg-white text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                        Tolak Pembatalan
                    </button>
                </form>
            </div>
        </div>

    @elseif(in_array($order->status_order, ['selesai','rejected','dibatalkan']))
        <p class="text-sm text-gray-500">
            Tidak ada aksi. Pesanan sudah <span class="font-semibold">{{ strtolower($statusLabel) }}</span>.
        </p>

    @else
        {{-- AKSI NORMAL --}}
        <div class="space-y-3">

            {{-- MODE PICKUP --}}
            @if($order->metode_pengiriman === 'pickup')

                @if($order->status_order === 'menunggu_konfirmasi')
                    <form method="POST" action="{{ route('mitra.orders.confirm', $order->id) }}">
                        @csrf
                        <button class="px-5 py-2.5 rounded-full bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                            Konfirmasi Pesanan
                        </button>
                    </form>

                @elseif($order->status_order === 'diproses')
                    <form method="POST" action="{{ route('mitra.orders.readyPickup', $order->id) }}">
                        @csrf
                        <button class="px-5 py-2.5 rounded-full bg-sky-600 text-white text-sm font-semibold hover:bg-sky-700">
                            Tandai Siap Diambil
                        </button>
                    </form>

                @elseif($order->status_order === 'siap_diambil')
                    <form method="POST" action="{{ route('mitra.orders.finish', $order->id) }}">
                        @csrf
                        <button class="px-5 py-2.5 rounded-full bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                            Tandai Sudah Diambil
                        </button>
                    </form>
                @endif

            {{-- MODE DELIVERY --}}
            @else

                @if($order->status_order === 'menunggu_konfirmasi')
                    <form method="POST" action="{{ route('mitra.orders.confirm', $order->id) }}">
                        @csrf
                        <button class="px-5 py-2.5 rounded-full bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                            Konfirmasi Pesanan
                        </button>
                    </form>

                @elseif($order->status_order === 'diproses')
                    <form method="POST" action="{{ route('mitra.orders.pack', $order->id) }}">
                        @csrf
                        <button class="px-5 py-2.5 rounded-full bg-sky-600 text-white text-sm font-semibold hover:bg-sky-700">
                            Tandai Dikemas
                        </button>
                    </form>

                @elseif($order->status_order === 'dikemas')
                    <form method="POST" action="{{ route('mitra.orders.readyShip', $order->id) }}">
                        @csrf
                        <button class="px-5 py-2.5 rounded-full bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                            Pesanan Siap Dikirim
                        </button>
                    </form>

                @elseif($order->status_order === 'siap_dikirim')
    <div class="space-y-2">
        <p class="text-sm text-gray-500">
            Pesanan sudah siap dikirim. Kamu bisa cetak resi untuk ditempel pada paket.
        </p>

        <a href="{{ route('mitra.orders.printResi', $order->id) }}"
           target="_blank"
           class="inline-flex items-center justify-center rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
            🧾 Cetak Resi
        </a>
    </div>

@elseif($order->status_order === 'dikirim')
    <div class="space-y-2">
        <p class="text-sm text-gray-500">
            Pesanan sudah dikirim. Resi masih bisa dicetak ulang jika diperlukan.
        </p>

        <a href="{{ route('mitra.orders.printResi', $order->id) }}"
           target="_blank"
           class="inline-flex items-center justify-center rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
            🧾 Cetak Resi
        </a>
    </div>
@endif

            @endif

            {{-- TOMBOL KONFIRMASI PEMBAYARAN (TRANSFER) --}}
            @if(
                $order->metode_pembayaran === 'transfer'
                && $order->payment_status === 'pending'
                && in_array($order->status_order, ['menunggu_konfirmasi', 'diproses'])
            )
                <div class="mt-4 border-t border-dashed border-gray-200 pt-4">
                    <p class="text-xs text-gray-500 mb-2">
                        Pembeli memilih <span class="font-semibold">Transfer Bank</span>.
                        Klik tombol di bawah kalau pembayaran sudah diterima.
                    </p>

                    <form action="{{ route('mitra.orders.confirmPayment', $order->id) }}"
                          method="POST"
                          onsubmit="return confirm('Konfirmasi pembayaran untuk pesanan ini?')">
                        @csrf
                        <button class="px-4 py-2 text-xs rounded-full bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
                            Konfirmasi Pembayaran
                        </button>
                    </form>
                </div>
            @endif

        </div>
    @endif
</div>

            {{-- GRID INFO UTAMA --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- INFO PEMBELI --}}
                <div class="bg-white border border-emerald-50 shadow-sm rounded-2xl p-5">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">
                        Informasi Pembeli
                    </h3>

                    <div class="space-y-1 text-sm">
                        <p>
                            <span class="text-gray-500">Nama</span><br>
                            <span class="font-medium text-gray-800">{{ $order->user->name }}</span>
                        </p>
                        <p>
                            <span class="text-gray-500">Email</span><br>
                            <span class="text-gray-700">{{ $order->user->email }}</span>
                        </p>

                        @if($order->metode_pengiriman === 'delivery')
                            <p class="pt-2">
                                <span class="text-gray-500">Alamat Pengiriman</span><br>
                                <span class="text-gray-700 text-sm leading-relaxed">
                                    {{ $order->alamat_pengiriman }}
                                </span>
                            </p>
                        @else
                            <p class="pt-2">
                                <span class="text-gray-500">Metode Pengiriman</span><br>
                                <span class="text-gray-700">Ambil di Toko</span>
                            </p>
                            <p>
                                <span class="text-gray-500">Alamat Toko</span><br>
                                <span class="text-gray-700 text-sm leading-relaxed">
                                    {{ $order->mitra->alamat }}
                                </span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- RINGKASAN PEMBAYARAN --}}
                <div class="bg-white border border-emerald-50 shadow-sm rounded-2xl p-5">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">
                        Ringkasan Pembayaran
                    </h3>

                    @php
                        $totalProduk = $order->items->sum('subtotal');
                        $ongkir = $order->ongkir ?? 0;
                    @endphp

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Total Harga Produk</dt>
                            <dd class="font-medium text-gray-800">
                                Rp {{ number_format($totalProduk, 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Ongkos Kirim</dt>
                            <dd class="font-medium text-gray-800">
                                Rp {{ number_format($ongkir, 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="border-t border-dashed border-gray-200 my-3"></div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-600 font-semibold">Total Dibayar</dt>
                            <dd class="text-lg font-extrabold text-emerald-700">
                                Rp {{ number_format($order->total_harga, 0, ',', '.') }}
                            </dd>
                        </div>
                    </dl>
                </div>

            </div>

            {{-- PRODUK --}}
            <div class="bg-white border border-emerald-50 shadow-sm rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-800">
                        Produk dalam Pesanan
                    </h3>
                    <span class="text-xs text-gray-400">
                        {{ $order->items->count() }} item
                    </span>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach($order->items as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 py-3">
                            <div class="flex items-center gap-3 flex-1">
                                <img src="{{ asset('storage/' . $item->product->foto_produk) }}"
                                     class="w-16 h-16 rounded-lg object-cover border border-gray-100">

                                <div>
                                    <p class="font-semibold text-gray-800">
                                        {{ $item->product->nama_produk }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $item->jumlah }} × Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>

                            <div class="sm:text-right">
                                <p class="text-xs text-gray-500">Subtotal</p>
                                <p class="font-semibold text-emerald-700">
                                    Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>


        </div>
    </div>
</x-app-layout>
