<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Percy\Core\Models\Tenant;
use Percy\Core\Models\Product;
use Percy\Core\Models\Category;

class StorefrontController extends Controller
{
    public function index($tenant_domain)
    {
        // 1. Buscamos de quién es esta tienda usando el subdominio
        $tenant = Tenant::where('domain', $tenant_domain)
                        ->where('is_active', true)
                        ->firstOrFail(); // Si no existe o está apagado, lanza error 404

        // 2. Traemos las categorías de este cliente (que tengan productos)
        $categories = Category::where('tenant_id', $tenant->id)->get();

        // 3. Traemos el catálogo de productos activos
        $products = Product::where('tenant_id', $tenant->id)
                           ->where('active', true)
                           ->with(['category', 'unidadSunat']) // 🌟 MAGIA: Agregamos unidadSunat al arreglo
                           ->get();

        // 4. Mandamos todo a la vista pública
        return view('storefront.index', compact('tenant', 'categories', 'products'));
    }
}
