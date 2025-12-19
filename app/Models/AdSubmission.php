<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSubmission extends Model
{
    protected $fillable = [
        'mitra_id',
        'product_id',
        'placement',
        'target_kategori',
        'kategori_snapshot',
        'status',
        'start_at',
        'end_at',
        'payment_method',
        'payment_status',
        'payment_proof',
        'paid_at',
        'price',
        'admin_note',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
        'paid_at'  => 'datetime',
    ];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
