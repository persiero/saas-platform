<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Percy\Core\Models\Tenant;
use Percy\Core\Models\Product;
use Percy\Core\Models\Category;
use Percy\Core\Models\DeliveryZone;
use Percy\Core\Models\Sale;
use Percy\Core\Models\SaleItem;

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

    // 🌟 NUEVA FUNCIÓN PARA LA PÁGINA DE PAGO
    public function checkout($tenant_domain)
    {
        // 1. Buscamos al Tenant igual que en el index
        $tenant = Tenant::where('domain', $tenant_domain)
                        ->where('is_active', true)
                        ->firstOrFail();

        // 2. Traemos las zonas de reparto de este cliente (Solo las activas)
        $deliveryZones = DeliveryZone::with('district') // Cargamos la relación para tener el nombre
                                     ->where('tenant_id', $tenant->id)
                                     ->where('is_active', true)
                                     ->get();

        // 3. Mandamos la vista del checkout
        return view('storefront.checkout', compact('tenant', 'deliveryZones'));
    }

    // 🌟 LA NUEVA FUNCIÓN POST (Crea el ticket en la BD y descuenta el stock)
    public function processWebOrder(Request $request, $tenant_domain)
    {
        try {
            DB::beginTransaction();

            $tenant = Tenant::where('domain', $tenant_domain)->firstOrFail();

            // 🌟 NUEVO: Leer el IGV del Tenant (o usar 18% por defecto)
            $porcentajeIgv = $tenant->igv_percentage ?? 18;
            $factorIgv = 1 + ($porcentajeIgv / 100);

            // 1. Calcular Totales en el servidor (Por seguridad)
            $subtotal = 0;
            foreach ($request->cart as $item) {
                $subtotal += ($item['price'] * $item['quantity']);
            }

            $deliveryFee = $request->delivery_fee ?? 0;
            $total = $subtotal + $deliveryFee;

            // 🌟 NUEVO: Calculamos el IGV global usando el factor dinámico
            $igv = $total - ($total / $factorIgv);

            // 2. Construir la Nota para el Cajero
            $notasDelivery = "=== DATOS WEB ===\n";
            $notasDelivery .= "Nombre: " . $request->customer_name . "\n";
            if ($request->customer_dni) $notasDelivery .= "DNI: " . $request->customer_dni . "\n";

            if ($request->order_type === 'delivery') {
                $notasDelivery .= "Distrito: " . $request->district . "\n";
                $notasDelivery .= "Dirección: " . $request->address . "\n";
                if ($deliveryFee > 0) $notasDelivery .= "Tarifa Envío: S/ " . number_format($deliveryFee, 2) . "\n";
            } else {
                $notasDelivery .= "=> RECOJO EN TIENDA\n";
            }
            if ($request->notes) $notasDelivery .= "\nNotas: " . $request->notes;

            // 3. Crear el Ticket Interno (Borrador)
            $sale = new Sale();
            $sale->tenant_id = $tenant->id;
            $sale->user_id = null;
            $sale->customer_id = null;
            $sale->document_type = '00';
            $sale->series = 'N001';
            $sale->channel = 'ecommerce';
            $sale->payment_method = 'Pendiente';
            $sale->status = 'pending_payment';

            // 🌟 NUEVO: Usamos el factor dinámico para las operaciones gravadas
            $sale->op_gravadas = round($total / $factorIgv, 2);
            $sale->igv = round($igv, 2);
            $sale->total = round($total, 2);
            $sale->op_exoneradas = 0;
            $sale->op_inafectas = 0;

            $sale->sold_at = now();
            $sale->kitchen_notes = $notasDelivery;
            $sale->save();

            // 4. Crear los Items y Descontar Stock
            foreach ($request->cart as $cartItem) {
                $producto = Product::where('tenant_id', $tenant->id)
                                   ->where('name', $cartItem['name'])
                                   ->first();

                if ($producto) {
                    $itemTotal = $cartItem['price'] * $cartItem['quantity'];

                    // 🌟 NUEVO: Usamos el factor dinámico para los ítems
                    $base = $itemTotal / $factorIgv;

                    SaleItem::create([
                        'tenant_id' => $tenant->id,
                        'sale_id' => $sale->id,
                        'product_id' => $producto->id,
                        'item_name' => $producto->name,
                        'quantity' => $cartItem['quantity'],
                        'unit_price' => $cartItem['price'],
                        'unit_value' => round($base, 2),
                        'igv_amount' => round($itemTotal - $base, 2),
                        'total' => round($itemTotal, 2),
                        'afectacion_igv_id' => $producto->afectacion_igv_id ?? 1,
                    ]);

                    // Descuento de Stock Básico
                    $producto->decrement('current_stock', $cartItem['quantity']);
                }
            }

            // 🌟 5. LA MAGIA: Agregar el Costo de Envío como un ítem extra dinámico
            if ($deliveryFee > 0) {
                $deliveryBase = $deliveryFee / $factorIgv;
                $deliveryIgv = $deliveryFee - $deliveryBase;

                SaleItem::create([
                    'tenant_id' => $tenant->id,
                    'sale_id' => $sale->id,
                    'product_id' => null, // No está atado a un producto físico
                    'item_name' => 'Servicio de Delivery - ' . $request->district, // Le agregamos el distrito para que se vea más profesional
                    'quantity' => 1,
                    'unit_price' => $deliveryFee,
                    'unit_value' => round($deliveryBase, 2),
                    'igv_amount' => round($deliveryIgv, 2),
                    'total' => round($deliveryFee, 2),
                    'afectacion_igv_id' => 1, // 1 = Gravado Operación Onerosa
                ]);
            }

            DB::commit();

            // Devolvemos el N° de Ticket a la vista
            $numeroTicket = $sale->series . '-' . str_pad($sale->correlative, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'ticket_number' => $numeroTicket
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
