<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-lg text-gray-800 leading-tight">
                    Daftar Pesanan Pembeli
                </h2>
                <p class="text-xs text-gray-500">
                    Kelola pesanan yang masuk ke toko Farmedia Anda.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-4 lg:px-6 space-y-4">

            @php
    // current view dari controller / query string
    $currentView = $view ?? request('view', 'aktif');

    $tabs = [
        'aktif'      => 'Aktif',
        'selesai'    => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        'semua'      => 'Semua',
    ];
@endphp

{{-- TAB FILTER ALA SHOPIFY --}}
<div class="flex flex-wrap items-center gap-2 mb-3 text-xs">
    @foreach($tabs as $key => $label)
        @php $active = $currentView === $key; @endphp

        <a href="{{ route('mitra.orders.index', ['view' => $key]) }}"
           class="inline-flex items-center rounded-full border px-3 py-1.5
                  {{ $active
                        ? 'bg-emerald-600 text-white border-emerald-600'
                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

            {{-- Flash success --}}
            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 rounded-md text-xs">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Ringkasan kecil --}}
            <div class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-100 text-xs text-emerald-800">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[10px] text-white font-semibold">
                    {{ $orders->count() }}
                </span>
                <span>Pesanan aktif di toko Anda.</span>
            </div>

            {{-- LIST PESANAN DALAM BENTUK TABEL MINIMALIS --}}
@if ($orders->isEmpty())
    <div class="bg-white rounded-lg border border-slate-200 p-4 text-xs text-slate-500">
        Belum ada pesanan untuk produk toko Anda.
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

        {{-- Bar atas kecil --}}
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-900">
                Daftar Pesanan
            </p>
            <p class="text-xs text-slate-500">
                {{ $orders->count() }} pesanan aktif
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">
                            Order
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">
                            Tanggal
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">
                            Pembeli
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">
                            Total
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">
                            Metode
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">
                            Status
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-500 uppercase tracking-wide">
                            Item
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-500 uppercase tracking-wide">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200">
                    @foreach($orders as $index => $order)
                        @php
                            $totalQty = $order->items->sum('jumlah');
                            $totalHarga = $order->items->sum(function($item) {
                                return $item->jumlah * $item->harga_satuan;
                            });

                            $status = $order->status_order;

                            $statusMap = [
                                'menunggu_konfirmasi' => ['label' => 'Menunggu konfirmasi', 'bg' => 'bg-amber-50',  'text' => 'text-amber-700',  'border' => 'border-amber-200'],
                                'confirmed'           => ['label' => 'Dikonfirmasi',        'bg' => 'bg-emerald-50','text' => 'text-emerald-700','border' => 'border-emerald-200'],
                                'diproses'            => ['label' => 'Diproses',             'bg' => 'bg-emerald-50','text' => 'text-emerald-700','border' => 'border-emerald-200'],
                                'dikemas'             => ['label' => 'Dikemas',              'bg' => 'bg-sky-50',    'text' => 'text-sky-700',    'border' => 'border-sky-200'],
                                'dikirim'             => ['label' => 'Dikirim',              'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200'],
                                'siap_diambil'        => ['label' => 'Siap diambil',         'bg' => 'bg-teal-50',   'text' => 'text-teal-700',   'border' => 'border-teal-200'],
                                'selesai'             => ['label' => 'Selesai',              'bg' => 'bg-emerald-600','text' => 'text-white',    'border' => 'border-emerald-600'],
                                'rejected'            => ['label' => 'Ditolak',              'bg' => 'bg-rose-50',   'text' => 'text-rose-700',   'border' => 'border-rose-200'],
                                'dibatalkan'          => ['label' => 'Dibatalkan',           'bg' => 'bg-red-50',    'text' => 'text-red-700',    'border' => 'border-red-200'],
                            ];

                            $statusStyle = $statusMap[$status] ?? [
                                'label' => ucfirst(str_replace('_', ' ', $status)),
                                'bg'    => 'bg-slate-100',
                                'text'  => 'text-slate-700',
                                'border'=> 'border-slate-200',
                            ];
                        @endphp

                        <tr class="hover:bg-slate-50">
                            {{-- Checkbox --}}
                            {{-- <td class="px-3 py-3 align-top">
                                <input type="checkbox" class="rounded border-slate-300">
                            </td> --}}

                            {{-- Order --}}
                            <td class="px-3 py-3 align-top whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold text-slate-900">
                                        ORD-{{ $order->id }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        #{{ $index + 1 }}
                                    </span>
                                </div>
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-3 py-3 align-top whitespace-nowrap">
                                <span class="text-xs text-slate-700">
                                    {{ $order->created_at?->format('d M Y, H:i') }}
                                </span>
                            </td>

                            {{-- Pembeli --}}
                            <td class="px-3 py-3 align-top">
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold text-slate-900">
                                        {{ $order->user->name ?? '-' }}
                                    </span>
                                    @if($order->user?->email)
                                        <span class="text-[11px] text-slate-500 truncate max-w-[160px]">
                                            {{ $order->user->email }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Total --}}
                            <td class="px-3 py-3 align-top whitespace-nowrap">
                                <span class="text-xs font-semibold text-emerald-700">
                                    Rp {{ number_format($totalHarga, 0, ',', '.') }}
                                </span>
                            </td>

                            {{-- Metode --}}
                            <td class="px-3 py-3 align-top whitespace-nowrap">
                                <span class="text-xs text-slate-700">
                                    {{ $order->metode_pengiriman === 'pickup' ? 'Ambil di toko' : 'Kirim ke alamat' }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-3 py-3 align-top">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-medium {{ $statusStyle['bg'] }} {{ $statusStyle['text'] }} {{ $statusStyle['border'] }}">
                                    {{ $statusStyle['label'] }}
                                </span>
                            </td>

                            {{-- Item --}}
                            <td class="px-3 py-3 align-top text-right whitespace-nowrap">
                                <span class="text-xs text-slate-700">
                                    {{ $totalQty }} {{ $totalQty > 1 ? 'items' : 'item' }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-3 py-3 align-top text-right">
                                <div class="inline-flex items-center gap-2">
                                    @if ($order->status_order === 'menunggu_konfirmasi')
                                        <form action="{{ route('mitra.orders.confirm', $order->id) }}"
                                              method="POST"
                                              onsubmit="return confirm('Konfirmasi pesanan ini?')">
                                            @csrf
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-full bg-emerald-600 text-[11px] text-white hover:bg-emerald-700">
                                                Konfirmasi
                                            </button>
                                        </form>

                                        <form action="{{ route('mitra.orders.reject', $order->id) }}"
                                              method="POST"
                                              onsubmit="return confirm('Tolak pesanan ini?')">
                                            @csrf
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-full border border-rose-300 bg-rose-50 text-[11px] text-rose-700 hover:bg-rose-100">
                                                Tolak
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('mitra.orders.show', $order->id) }}"
                                           class="inline-flex px-3 py-1.5 rounded-full border border-slate-300 text-[11px] font-medium text-slate-700 hover:bg-slate-50">
                                            Detail
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
@endif


        </div>
    </div>
</x-app-layout>
