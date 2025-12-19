<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Pesanan</h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl border p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th>ID</th>
                        <th>Pembeli</th>
                        <th>Mitra</th>
                        <th>Status</th>
                        <th>Resi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $o)
                        <tr class="border-t">
                            <td class="py-2">ORD-{{ $o->id }}</td>
                            <td>{{ $o->user->name ?? '-' }}</td>
                            <td>{{ $o->mitra->nama_toko ?? '-' }}</td>
                            <td>{{ $o->status_order }}</td>
                            <td>{{ $o->nomor_resi ?? '-' }}</td>
                            <td class="text-right">
                                <a class="text-emerald-700 underline"
                                   href="{{ route('admin.orders.show', $o->id) }}">
                                   Detail
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
