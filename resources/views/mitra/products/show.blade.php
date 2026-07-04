<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Detail Produk
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Informasi lengkap produk yang ditampilkan di etalase.
                </p>
            </div>

            <a href="{{ route('mitra.products.index') }}"
               class="inline-flex items-center px-3 py-1.5 text-xs rounded-lg border border-gray-300
                      bg-white text-gray-700 hover:bg-gray-50
                      dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600">
                ← Kembali ke daftar
            </a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white dark:bg-gray-800/90 shadow-sm border border-emerald-50/70
                    dark:border-gray-700 sm:rounded-2xl overflow-hidden">
            <div class="p-6 sm:p-8 text-gray-900 dark:text-gray-100">

                <h3 class="text-lg font-semibold mb-6">
                    {{ $product->nama_produk }}
                </h3>

                {{-- GRID 2 KOLOM --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Nama Produk
                            </label>
                            <div class="mt-1 text-sm">
                                {{ $product->nama_produk }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Kategori Produk
                            </label>
                            <div class="mt-1 text-sm">
                                {{ $product->kategori_produk ?? '-' }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Deskripsi Produk
                        </label>
                        <div class="mt-1 text-sm whitespace-pre-line">
                            {{ $product->deskripsi_produk ?? '-' }}
                        </div>
                    </div>

                </div>

                {{-- GRID 3 KOLOM --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Harga (Rp)
                        </label>
                        <div class="mt-1 text-sm">
                            Rp {{ number_format($product->harga, 0, ',', '.') }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Stok
                        </label>
                        <div class="mt-1 text-sm">
                            {{ $product->stok }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Berat (gram)
                        </label>
                        <div class="mt-1 text-sm">
                            {{ $product->berat ?? '-' }}
                        </div>
                    </div>
                </div>

                {{-- FOTO --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Foto Produk
                    </label>

                    @if ($product->foto_produk)
                        <img src="{{ asset('storage/' . $product->foto_produk) }}"
                             alt="{{ $product->nama_produk }}"
                             class="h-40 w-40 object-cover rounded-xl border border-gray-200
                                    dark:border-gray-700 cursor-pointer hover:opacity-90"
                             onclick="openPreview()">
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Tidak ada foto produk.
                        </p>
                    @endif
                </div>

                {{-- TOMBOL --}}
                <div class="pt-6 flex justify-end gap-3">

                    {{-- HAPUS --}}
                    <form action="{{ route('mitra.products.destroy', $product->id) }}"
                          method="POST"
                          onsubmit="return confirm('Yakin ingin menghapus produk ini? Tindakan ini tidak bisa dibatalkan.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-lg
                                       text-xs font-semibold
                                       bg-rose-600 text-white shadow-sm hover:bg-rose-700">
                            Hapus Produk
                        </button>
                    </form>

                    {{-- EDIT --}}
                    <a href="{{ route('mitra.products.edit', $product->id) }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg
                              text-xs font-semibold
                              bg-emerald-600 text-white shadow-sm hover:bg-emerald-700">
                        Edit Produk
                    </a>
                </div>

            </div>
        </div>
    </div>

    {{-- MODAL PREVIEW FOTO --}}
    @if ($product->foto_produk)
        <div id="previewModal"
             class="fixed inset-0 z-50 hidden items-center justify-center
                    bg-black/70 backdrop-blur-sm">
            <div class="relative max-w-3xl mx-auto p-4">
                <button onclick="closePreview()"
                        class="absolute -top-3 -right-3 bg-white text-gray-800
                               rounded-full w-8 h-8 flex items-center justify-center
                               shadow hover:bg-gray-100">
                    ✕
                </button>
                <img src="{{ asset('storage/' . $product->foto_produk) }}"
                     alt="{{ $product->nama_produk }}"
                     class="max-h-[80vh] rounded-xl shadow-lg">
            </div>
        </div>

        <script>
            function openPreview() {
                document.getElementById('previewModal').classList.remove('hidden');
                document.getElementById('previewModal').classList.add('flex');
            }

            function closePreview() {
                document.getElementById('previewModal').classList.add('hidden');
                document.getElementById('previewModal').classList.remove('flex');
            }
        </script>
    @endif

</x-app-layout>
