<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Percy\Core\Models\Category;
use Percy\Core\Models\DeliveryZone;
use Percy\Core\Models\Product;
use Percy\Core\Models\Tenant;
use Percy\Core\Services\Sales\WebOrderService;
use Illuminate\Validation\ValidationException;
use Percy\Core\Services\Tenants\TenantPlanService;

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
            ->where(function ($query) {
                $query->where('type', 'service')
                    ->orWhere('current_stock', '>', 0);
            })
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
        try {
            $tenant = $this->findActiveTenant($tenant_domain);

            $validated = $request->validate([
                'customer_name' => ['required', 'string', 'max:120'],
                'customer_phone' => ['required', 'string', 'max:20'],
                'customer_dni' => ['nullable', 'string', 'max:20'],

                'order_type' => ['required', 'in:delivery,pickup'],
                'delivery_zone_id' => ['nullable', 'integer'],
                'address' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:500'],

                'cart' => ['required', 'array', 'min:1'],
                'cart.*.product_id' => ['required', 'integer'],
                'cart.*.quantity' => ['required', 'numeric', 'min:1'],
            ]);

            if ($validated['order_type'] === 'delivery' && empty($validated['delivery_zone_id'])) {
                throw ValidationException::withMessages([
                    'delivery_zone_id' => 'Selecciona una zona de reparto.',
                ]);
            }

            if ($validated['order_type'] === 'delivery' && empty($validated['address'])) {
                throw ValidationException::withMessages([
                    'address' => 'Ingresa la dirección de entrega.',
                ]);
            }

            $sale = app(WebOrderService::class)
                ->createFromStorefront($tenant, $validated);

            $numeroTicket = $sale->series . '-' . str_pad($sale->correlative, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'ticket_number' => $numeroTicket,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Datos incompletos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function findActiveTenant(string $tenantDomain): Tenant
    {
        $tenant = Tenant::query()
            ->where('domain', $tenantDomain)
            ->where('is_active', true)
            ->firstOrFail();

        $planService = app(TenantPlanService::class);

        abort_unless(
            $planService->has('has_online_store', $tenant) || $planService->has('has_delivery', $tenant),
            404
        );

        return $tenant;
    }
}
