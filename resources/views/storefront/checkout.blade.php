@extends('storefront.layouts.app')

@section('content')
<style>
    .toggle-radio:checked + div {
        border-color: {{ $tenant->primary_color ?? '#4f46e5' }} !important;
        background-color: {{ $tenant->primary_color ?? '#4f46e5' }}15 !important;
        color: {{ $tenant->primary_color ?? '#4f46e5' }} !important;
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="mb-6 flex items-center gap-2 text-sm text-gray-500">
        <a href="/" class="hover:text-brand transition">Tienda</a>
        <span>/</span>
        <span class="font-bold text-gray-800">Finalizar Compra</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 relative">

        {{-- COLUMNA IZQUIERDA: Formulario de Datos --}}
        <div class="lg:col-span-7 space-y-6">

            {{-- 1. Método de Entrega --}}
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-black text-gray-800 mb-4 flex items-center gap-2">
                    <span class="bg-brand text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">1</span>
                    ¿Cómo quieres recibir tu pedido?
                </h3>
                <div class="flex gap-3">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="order_type" value="delivery" class="toggle-radio sr-only" checked onchange="updateCheckoutTotal()">
                        <div class="text-center py-4 border-2 rounded-xl border-gray-200 text-gray-500 font-bold text-sm transition-all shadow-sm">
                            🛵 Delivery
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="order_type" value="pickup" class="toggle-radio sr-only" onchange="updateCheckoutTotal()">
                        <div class="text-center py-4 border-2 rounded-xl border-gray-200 text-gray-500 font-bold text-sm transition-all shadow-sm">
                            🏪 Recojo en Tienda
                        </div>
                    </label>
                </div>
            </div>

            {{-- 2. Datos del Cliente --}}
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-black text-gray-800 mb-4 flex items-center gap-2">
                    <span class="bg-brand text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">2</span>
                    Tus Datos
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Nombre Completo *</label>
                        <input type="text" id="chk-nombre" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl outline-none focus:ring-2 focus:ring-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Celular / WhatsApp *</label>
                        <input type="text" id="chk-phone" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl outline-none focus:ring-2 focus:ring-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">DNI / RUC (Opcional)</label>
                        <input type="number" id="chk-dni" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl outline-none focus:ring-2 focus:ring-brand">
                    </div>
                </div>

                <div id="checkout-delivery-fields" class="mt-4 space-y-4">
                    <hr class="border-dashed border-gray-200">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Distrito *</label>
                        <div>
                        <select id="chk-distrito" onchange="updateCheckoutTotal()" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl outline-none focus:ring-2 focus:ring-brand appearance-none">
                            <option value="" disabled selected data-price="0">Selecciona tu distrito...</option>

                            {{-- 🌟 MAGIA: Leemos las zonas desde la Base de Datos --}}
                            @forelse($deliveryZones as $zone)
                                <option value="{{ $zone->id }}"
                                        data-price="{{ $zone->price }}"
                                        data-name="{{ $zone->district->name }}">
                                    {{ $zone->district->name }} (S/ {{ number_format($zone->price, 2) }})
                                </option>
                            @empty
                                <option value="" disabled>No hay zonas de reparto configuradas</option>
                            @endforelse

                        </select>
                    </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Dirección Exacta *</label>
                        <input type="text" id="chk-direccion" placeholder="Ej: Av. Principal 123, Mz A Lote 4" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl outline-none focus:ring-2 focus:ring-brand">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Notas Adicionales</label>
                    <textarea id="chk-notas" rows="2" placeholder="Ej: Sin cebolla, pagaré con billete de S/ 50" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl outline-none focus:ring-2 focus:ring-brand"></textarea>
                </div>
            </div>

            {{-- 3. Método de Pago --}}
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-black text-gray-800 mb-4 flex items-center gap-2">
                    <span class="bg-brand text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">3</span>
                    Pago
                </h3>

                @if($tenant->yape_number)
                    <div class="bg-[#74006E]/10 border border-[#74006E]/20 rounded-xl p-4 flex items-center gap-4">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/d/d1/Yape_text_app_icon.png" class="w-12 h-12 object-contain rounded-xl">
                        <div>
                            <p class="text-sm text-gray-600 font-medium">Transfiere seguro a nombre del negocio al número:</p>
                            <p class="font-black text-2xl text-[#74006E] tracking-wide">{{ $tenant->yape_number }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-gray-500">El pago se coordinará por WhatsApp.</p>
                @endif
            </div>

        </div>

        {{-- COLUMNA DERECHA: Resumen (Sticky) --}}
        <div class="lg:col-span-5 relative">
            <div class="bg-gray-50 p-6 rounded-3xl border border-gray-200 lg:sticky lg:top-24">
                <h3 class="text-lg font-black text-gray-800 mb-4">Resumen de tu pedido</h3>

                {{-- Aquí se inyectan los productos por JS --}}
                <div id="checkout-items" class="space-y-3 mb-6 max-h-60 overflow-y-auto pr-2 hide-scrollbar"></div>

                <hr class="border-gray-200 mb-4">

                <div class="space-y-2 text-sm text-gray-600 font-medium">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span id="chk-subtotal">S/ 0.00</span>
                    </div>
                    <div class="flex justify-between" id="chk-delivery-row">
                        <span>Envío</span>
                        <span id="chk-delivery-fee">S/ 0.00</span>
                    </div>
                </div>

                <hr class="border-gray-200 my-4">

                <div class="flex justify-between items-center mb-6">
                    <span class="text-gray-800 font-bold text-lg">Total</span>
                    <span id="chk-total" class="font-black text-2xl text-brand">S/ 0.00</span>
                </div>

                <button onclick="processCheckout('{{ $tenant->phone }}')" class="w-full bg-[#25D366] text-white font-black py-4 rounded-xl flex justify-center items-center gap-2 hover:bg-[#128C7E] shadow-lg transition-transform active:scale-95 text-lg">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.883-.653-1.48-1.459-1.653-1.756-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Enviar Pedido por WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. Leemos el carrito de la memoria
    let chkCart = JSON.parse(localStorage.getItem('carrito_{{ $tenant->id }}')) || [];

    chkCart = chkCart
        .filter(item => item.product_id && Number(item.quantity) > 0)
        .map(item => ({
            product_id: Number(item.product_id),
            name: item.name || 'Producto',
            price: Number(item.price || 0),
            quantity: Number(item.quantity || 1),
            unit: item.unit || 'Und.'
        }));

    // 🌟 2. FUNCIÓN PARA PINTAR LOS PRODUCTOS Y TOTALES
    function updateCheckoutTotal() {
        if (chkCart.length === 0) {
            window.location.href = "/"; // Si borró todo mágicamente, lo regresamos
            return;
        }

        let isDelivery = document.querySelector('input[name="order_type"]:checked').value === 'delivery';
        document.getElementById('checkout-delivery-fields').style.display = isDelivery ? 'block' : 'none';

        let cartHtml = '';
        let subtotal = 0;

        // Dibujamos cada producto en el panel derecho
        chkCart.forEach((item) => {
            let itemPrice = Number(item.price || 0);
            let itemQuantity = Number(item.quantity || 0);
            let itemTotal = itemPrice * itemQuantity;

            subtotal += itemTotal;
            cartHtml += `
                <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="bg-gray-100 text-gray-600 font-bold px-2 py-1 rounded-md text-xs">${item.quantity}</span>
                        <div>
                            <p class="font-bold text-sm text-gray-800 leading-tight">${item.name}</p>
                            <p class="text-xs text-gray-400">S/ ${itemPrice.toFixed(2)} x ${item.unit}</p>
                        </div>
                    </div>
                    <span class="font-bold text-brand">S/ ${itemTotal.toFixed(2)}</span>
                </div>
            `;
        });

        // Inyectamos el HTML en la vista
        document.getElementById('checkout-items').innerHTML = cartHtml;
        document.getElementById('chk-subtotal').innerText = 'S/ ' + subtotal.toFixed(2);

        // Calculamos el costo de envío dinámico
        let currentDeliveryFee = 0;
        let distritoSelect = document.getElementById('chk-distrito');

        if (isDelivery && distritoSelect.selectedIndex > 0) {
            let selectedOption = distritoSelect.options[distritoSelect.selectedIndex];
            currentDeliveryFee = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        }

        if (isDelivery) {
            document.getElementById('chk-delivery-row').style.display = 'flex';
            document.getElementById('chk-delivery-fee').innerText = 'S/ ' + currentDeliveryFee.toFixed(2);
        } else {
            document.getElementById('chk-delivery-row').style.display = 'none';
        }

        // Total final
        let finalTotal = subtotal + currentDeliveryFee;
        document.getElementById('chk-total').innerText = 'S/ ' + finalTotal.toFixed(2);
    }

    // 🌟 3. FUNCIÓN PARA GUARDAR EN BASE DE DATOS Y WHATSAPP
    async function processCheckout(storePhone) {
        if (chkCart.length === 0) return;

        const nombre = document.getElementById('chk-nombre').value.trim();
        const customerPhone = document.getElementById('chk-phone').value.trim();
        const dni = document.getElementById('chk-dni').value.trim();
        const notas = document.getElementById('chk-notas').value.trim();
        const orderType = document.querySelector('input[name="order_type"]:checked').value;

        if (!nombre) { alert('Ingresa tu nombre.'); document.getElementById('chk-nombre').focus(); return; }

        if (!customerPhone) {
            alert('Ingresa tu celular o WhatsApp.');
            document.getElementById('chk-phone').focus();
            return;
        }
        let distrito = '';
        let deliveryZoneId = '';
        let direccion = '';
        let currentDeliveryFee = 0;

        if (orderType === 'delivery') {
            const distritoSelect = document.getElementById('chk-distrito');

            deliveryZoneId = distritoSelect.value;
            direccion = document.getElementById('chk-direccion').value.trim();

            if (!deliveryZoneId) {
                alert('Por favor, selecciona un distrito.');
                distritoSelect.focus();
                return;
            }

            if (!direccion) {
                alert('Por favor, ingresa tu dirección.');
                document.getElementById('chk-direccion').focus();
                return;
            }

            let selectedOption = distritoSelect.options[distritoSelect.selectedIndex];
            currentDeliveryFee = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            distrito = selectedOption.getAttribute('data-name') || selectedOption.textContent.trim();
        }

        // Protegemos el botón para evitar doble clic
        const btnSubmit = document.querySelector('button[onclick^="processCheckout"]');
        const textOriginal = btnSubmit.innerHTML;
        btnSubmit.innerHTML = 'Procesando pedido... ⏳';
        btnSubmit.disabled = true;

        // Preparamos los datos para enviar a Laravel
        const payload = {
            cart: chkCart.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity
            })),
            customer_name: nombre,
            customer_phone: customerPhone,
            customer_dni: dni,
            order_type: orderType,
            delivery_zone_id: deliveryZoneId,
            address: direccion,
            notes: notas
        };

        try {
            // 🌟 2. ENVIAMOS LA VENTA A LA BASE DE DATOS
            const response = await fetch('/checkout/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!data.success) {
                alert('Hubo un error al procesar tu pedido: ' + data.message);
                btnSubmit.innerHTML = textOriginal;
                btnSubmit.disabled = false;
                return;
            }

            // Construimos el mensaje de WhatsApp con el número de Ticket Real
            let text = `========================\n   NUEVO PEDIDO WEB   \n========================\n`;
            text += `*Orden Ref: ${data.ticket_number}*\n\n`;

            text += `[ DATOS DEL CLIENTE ]\nNombre: ${nombre}\n`;
            text += `Celular: ${customerPhone}\n`;
            if (dni) text += `DNI/RUC: ${dni}\n`;

            let finalTotal = 0;
            text += `\n[ MÉTODO DE ENTREGA ]\n`;
            if (orderType === 'delivery') {
                text += `=> DELIVERY\nDistrito: ${distrito}\nDirección: ${direccion}\n`;
            } else {
                text += `=> RECOJO EN TIENDA\n`;
            }

            text += `\n[ DETALLE DEL PEDIDO ]\n`;
            chkCart.forEach(item => {
                let itemTotal = item.price * item.quantity;
                finalTotal += itemTotal;
                text += `+ ${item.quantity} ${item.unit} x ${item.name} (S/ ${itemTotal.toFixed(2)})\n`;
            });

            if (orderType === 'delivery' && currentDeliveryFee > 0) {
                text += `+ Tarifa Delivery (S/ ${currentDeliveryFee.toFixed(2)})\n`;
                finalTotal += currentDeliveryFee;
            }

            text += `------------------------\n*TOTAL A PAGAR: S/ ${finalTotal.toFixed(2)}*\n------------------------\n`;
            if (notas) text += `\n[ NOTAS ADICIONALES ]\n${notas}`;

            let waPhone = storePhone ? storePhone.replace(/[^0-9]/g, '') : '';
            if(waPhone.length === 9) waPhone = '51' + waPhone;

            const encodedText = encodeURIComponent(text);

            // Limpiamos el carrito y abrimos WhatsApp
            localStorage.removeItem('carrito_{{ $tenant->id }}');
            window.open(`https://wa.me/${waPhone}?text=${encodedText}`, '_blank');
            window.location.href = "/"; // Regresamos al catálogo

        } catch (error) {
            alert('Error de conexión con el servidor. Intenta nuevamente.');
            btnSubmit.innerHTML = textOriginal;
            btnSubmit.disabled = false;
        }
    }

    // 🌟 4. ARRANCAR EL MOTOR AL CARGAR LA PÁGINA
    document.addEventListener('DOMContentLoaded', () => updateCheckoutTotal());
</script>
@endsection
