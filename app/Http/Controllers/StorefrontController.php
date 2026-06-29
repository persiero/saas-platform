<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Percy\Core\Models\Category;
use Percy\Core\Models\DeliveryZone;
use Percy\Core\Models\Product;
use Percy\Core\Models\Tenant;
use Percy\Core\Services\Sales\WebOrderService;

class StorefrontController extends Controller
{
    public function index($tenant_domain)
    {
        $tenant = $this->findActiveTenant($tenant_domain);

        $categories = Category::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('active', true)
            ->with(['category', 'unidadSunat'])
            ->get();

        return view('storefront.index', compact('tenant', 'categories', 'products'));
    }

    public function checkout($tenant_domain)
    {
        $tenant = $this->findActiveTenant($tenant_domain);

        $deliveryZones = DeliveryZone::query()
            ->with('district')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        return view('storefront.checkout', compact('tenant', 'deliveryZones'));
    }

    public function processWebOrder(Request $request, $tenant_domain)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_dni' => ['nullable', 'string', 'max:20'],
            'order_type' => ['required', 'in:delivery,pickup'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],

            'cart' => ['required', 'array', 'min:1'],
            'cart.*.name' => ['required', 'string', 'max:255'],
            'cart.*.quantity' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $tenant = $this->findActiveTenant($tenant_domain);

            $sale = app(WebOrderService::class)
                ->createFromStorefront($tenant, $validated);

            $numeroTicket = $sale->series . '-' . str_pad($sale->correlative, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'ticket_number' => $numeroTicket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function findActiveTenant(string $tenantDomain): Tenant
    {
        return Tenant::query()
            ->where('domain', $tenantDomain)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
