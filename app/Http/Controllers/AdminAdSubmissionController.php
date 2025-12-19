<?php

namespace App\Http\Controllers;

use App\Models\AdSubmission;
use Illuminate\Http\Request;

class AdminAdSubmissionController extends Controller
{
    public function index()
    {
        $ads = AdSubmission::with(['product','mitra'])
            ->orderByRaw("FIELD(status,'pending','approved','active','rejected','ended','cancelled')")
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.ads.index', compact('ads'));
    }

    public function show(AdSubmission $ad)
    {
        $ad->load(['product','mitra']);
        return view('admin.ads.show', compact('ad'));
    }

    public function approve(Request $request, AdSubmission $ad)
    {
        if ($ad->status !== 'pending') {
            return back()->with('error', 'Hanya pengajuan pending yang bisa di-approve.');
        }

        $ad->update([
            'status' => 'approved',
            'admin_note' => null,
        ]);

        return back()->with('success', 'Pengajuan di-approve. Tunggu pembayaran/aktivasi.');
    }

    public function reject(Request $request, AdSubmission $ad)
    {
        $request->validate([
            'admin_note' => 'required|string|max:500',
        ]);

        $ad->update([
            'status' => 'rejected',
            'admin_note' => $request->admin_note,
        ]);

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function markPaid(AdSubmission $ad)
    {
        if (!in_array($ad->status, ['pending','approved'])) {
            return back()->with('error', 'Pembayaran hanya bisa diverifikasi saat pending/approved.');
        }

        if (empty($ad->payment_proof)) {
            return back()->with('error', 'Bukti transfer belum ada.');
        }

        $ad->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Pembayaran ditandai LUNAS.');
    }

    public function activate(AdSubmission $ad)
{
    if ($ad->status !== 'approved') {
        return back()->with('error', 'Iklan hanya bisa diaktifkan dari status approved.');
    }

    if ($ad->payment_status !== 'paid') {
        return back()->with('error', 'Belum lunas. Mark paid dulu.');
    }

    $days = (int) ($ad->duration_days ?? 0);
    if ($days <= 0) {
        return back()->with('error', 'Durasi paket belum tersimpan.');
    }

    $start = now();
    $end   = now()->addDays($days);

    $ad->update([
        'status'   => 'active',
        'start_at' => $start,
        'end_at'   => $end,
    ]);

    return back()->with('success', 'Iklan berhasil diaktifkan otomatis.');
}

    public function end(AdSubmission $ad)
    {
        if ($ad->status !== 'active') {
            return back()->with('error', 'Hanya iklan aktif yang bisa diakhiri.');
        }

        $ad->update([
            'status' => 'ended',
        ]);

        return back()->with('success', 'Iklan diakhiri.');
    }
}
