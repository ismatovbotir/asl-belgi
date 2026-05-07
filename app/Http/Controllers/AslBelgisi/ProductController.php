<?php

namespace App\Http\Controllers\AslBelgisi;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\AslBelgisi\ProductRegistry\ProductRegistryService;

class ProductController extends Controller
{
    public function __construct(private ProductRegistryService $service) {}

    public function index()
    {
        $groups   = ProductGroup::orderBy('name_ru')->get();
        $products = Product::orderBy('name')->paginate(10);
        $lastSync = Product::max('synced_at');
        return view('aslbelgisi.products.index', compact('groups', 'products', 'lastSync'));
    }

    public function sync(\Illuminate\Http\Request $request)
    {
        $request->validate(['product_group' => 'required|string|exists:product_groups,code']);

        try {
            $count = $this->service->syncAll($request->input('product_group'));
            return back()->with('success', "Synced {$count} products from ASL BELGISI.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}
