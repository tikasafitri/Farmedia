<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function uploadProof(Request $request, Order $order)
    {
        if ((int) auth()->id() !== (int) $order->user_id) abort(403);

        if ($order->metode_pembayaran !== 'transfer') {
            return back()->with('error', 'Metode pembayaran bukan transfer.');
        }

        if (in_array($order->payment_status, ['paid','expired'])) {
            return back()->with('error', 'Status pembayaran tidak valid.');
        }

        $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $path = $request->file('payment_proof')->store('order_payments', 'public');

        $order->update([
            'payment_proof'      => $path,
            'payment_proof_path' => $path,
            'payment_status'     => 'waiting_verification',
        ]);

        return back()->with('success', 'Bukti transfer terkirim. Menunggu verifikasi admin.');
    }
}
