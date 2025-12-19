<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'mitra_id',
        'nama_produk',
        'kategori_produk',
        'deskripsi_produk',
        'harga',
        'stok',
        'berat',
        'foto_produk',
    ];

    public function mitra()
{
    return $this->belongsTo(\App\Models\Mitra::class, 'mitra_id');
}

public function reviews()
{
    return $this->hasMany(\App\Models\ProductReview::class, 'product_id');
}

}
