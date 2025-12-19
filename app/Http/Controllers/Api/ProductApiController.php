<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductApiController extends Controller
{
    public function index()
    {
        $products = Product::latest()->get();

        return response()->json($products);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }
}
