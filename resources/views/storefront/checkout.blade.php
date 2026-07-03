@extends('storefront.layouts.app')

@section('content')
<style>
    .toggle-radio:checked + div {
        border-color: var(--brand-color) !important;
        background-color: color-mix(in srgb, var(--brand-color) 10%, white) !important;
        color: var(--brand-color) !important;
        box-shadow: 0 12px 28px color-mix(in srgb, var(--brand-color) 18%, transparent);
    }

    .checkout-card {
        background: white;
        border: 1px solid rgb(241 245 249);
        border-radius: 1.5rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .checkout-input {
        width: 100%;
        border: 1px solid rgb(226 232 240);
        background: rgb(248 250 252);
        border-radius: 1rem;
        padding: 0.85rem 1rem;
        outline: none;
        font-size: 0.95rem;
        transition: all .2s ease;
    }

    .checkout-input:focus {
        border-color: var(--brand-color) !important;
        background: white;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-color) 16%, transparent);
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-5 md:py-8">

    {{-- ENCABEZADO COMPACTO --}}
    <div class="mb-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs md:text-sm text-slate-500 font-bold mb-1">
                <a href="/" class="hover:text-brand transition">Tienda</a>
                <span>/</span>
                <a href="/productos" class="hover:text-brand transition">Productos</a>
                <span>/</span>
                <span class="text-slate-800">Finalizar pedido</span>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs md:text-sm font-black text-brand uppercase tracking-wider">
                    Checkout
                </span>

                <span class="text-slate-300 font-bold">|</span>

                <h1 class="text-xl md:text-2xl font-black text-slate-950 leading-none">
                    Finalizar compra
                </h1>
            </div>
        </div>

        <a
            href="/productos"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 font-black px-4 py-2.5 rounded-2xl hover:bg-slate-50 transition w-fit"
        >
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Seguir comprando
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 relative">

        {{-- COLUMNA IZQUIERDA --}}
        <div class="lg:col-span-7 space-y-5">

            {{-- MÉTODO DE ENTREGA --}}
            <section class="checkout-card p-5 md:p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="bg-brand text-white w-8 h-8 rounded-2xl flex items-center justify-center text-sm font-black shrink-0">
                        1
                    </div>

                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            Método de entrega
                        </h2>
                        <p class="text-sm text-slate-500 mt-0.5">
                            Elige cómo quieres recibir tu pedido.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input
                            type="radio"
                            name="order_type"
                            value="delivery"
                            class="toggle-radio sr-only"
                            checked
                            onchange="updateCheckoutTotal()"
                        >

                        <div class="border-2 border-slate-200 rounded-2xl p-4 text-slate-600 transition-all hover:border-brand hover:bg-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-slate-100 flex items-center justify-center text-xl">
                                    🛵
                                </div>

                                <div>
                                    <p class="font-black text-sm">Delivery</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Envío a domicilio</p>
                                </div>
                            </div>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input
                            type="radio"
                            name="order_type"
                            value="pickup"
                            class="toggle-radio sr-only"
                            onchange="updateCheckoutTotal()"
                        >

                        <div class="border-2 border-slate-200 rounded-2xl p-4 text-slate-600 transition-all hover:border-brand hover:bg-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-slate-100 flex items-center justify-center text-xl">
                                    🏪
                                </div>

                                <div>
                                    <p class="font-black text-sm">Recojo en tienda</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Recoge tu pedido</p>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
            </section>

            {{-- DATOS DEL CLIENTE --}}
            <section class="checkout-card p-5 md:p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="bg-brand text-white w-8 h-8 rounded-2xl flex items-center justify-center text-sm font-black shrink-0">
                        2
                    </div>

                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            Datos del cliente
                        </h2>
                        <p class="text-sm text-slate-500 mt-0.5">
                            Usaremos estos datos para confirmar tu pedido.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-black text-slate-500 mb-1.5 uppercase tracking-wider">
                            Nombre completo *
                        </label>

                        <input
                            type="text"
                            id="chk-nombre"
                            placeholder="Ej: Juan Pérez"
                            class="checkout-input"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 mb-1.5 uppercase tracking-wider">
                            Celular / WhatsApp *
                        </label>

                        <input
                            type="text"
                            id="chk-phone"
                            placeholder="Ej: 987654321"
                            class="checkout-input"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 mb-1.5 uppercase tracking-wider">
                            DNI / RUC opcional
                        </label>

                        <input
                            type="number"
                            id="chk-dni"
                            placeholder="Opcional"
                            class="checkout-input"
                        >
                    </div>
                </div>

                {{-- CAMPOS DELIVERY --}}
                <div id="checkout-delivery-fields" class="mt-5 space-y-4">
                    <div class="border-t border-dashed border-slate-200 pt-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-black text-slate-500 mb-1.5 uppercase tracking-wider">
                                    Distrito *
                                </label>

                                <div class="relative">
                                    <select
                                        id="chk-distrito"
                                        onchange="updateCheckoutTotal()"
                                        class="checkout-input appearance-none pr-10"
                                    >
                                        <option value="" disabled selected data-price="0">
                                            Selecciona tu distrito...
                                        </option>

                                        @forelse($deliveryZones as $zone)
                                            <option
                                                value="{{ $zone->id }}"
                                                data-price="{{ $zone->price }}"
                                                data-name="{{ $zone->district->name }}"
                                            >
                                                {{ $zone->district->name }} (S/ {{ number_format($zone->price, 2) }})
                                            </option>
                                        @empty
                                            <option value="" disabled>
                                                No hay zonas de reparto configuradas
                                            </option>
                                        @endforelse
                                    </select>

                                    <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                        <x-heroicon-o-chevron-down class="w-5 h-5 text-slate-400" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-slate-500 mb-1.5 uppercase tracking-wider">
                                    Dirección exacta *
                                </label>

                                <input
                                    type="text"
                                    id="chk-direccion"
                                    placeholder="Ej: Av. Principal 123"
                                    class="checkout-input"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-black text-slate-500 mb-1.5 uppercase tracking-wider">
                        Notas adicionales
                    </label>

                    <textarea
                        id="chk-notas"
                        rows="3"
                        placeholder="Ej: Sin cebolla, pagaré con billete de S/ 50"
                        class="checkout-input resize-none"
                    ></textarea>
                </div>
            </section>

            {{-- PAGO --}}
            <section class="checkout-card p-5 md:p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="bg-brand text-white w-8 h-8 rounded-2xl flex items-center justify-center text-sm font-black shrink-0">
                        3
                    </div>

                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            Pago
                        </h2>
                        <p class="text-sm text-slate-500 mt-0.5">
                            El pedido será confirmado por WhatsApp.
                        </p>
                    </div>
                </div>

                @if($tenant->yape_number)
                    <div class="rounded-3xl border border-[#74006E]/20 bg-[#74006E]/10 p-4 md:p-5">
                        <div class="flex items-center gap-4">
                            <img
                                src="https://upload.wikimedia.org/wikipedia/commons/d/d1/Yape_text_app_icon.png"
                                class="w-12 h-12 object-contain rounded-2xl bg-white shadow-sm"
                                alt="Yape"
                            >

                            <div>
                                <p class="text-sm text-slate-600 font-semibold">
                                    Puedes transferir por Yape al número:
                                </p>

                                <p class="font-black text-2xl text-[#74006E] tracking-wide">
                                    {{ $tenant->yape_number }}
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 mt-3">
                            Luego envía tu pedido por WhatsApp para confirmar la atención.
                        </p>
                    </div>
                @else
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        El pago se coordinará directamente por WhatsApp al finalizar el pedido.
                    </div>
                @endif
            </section>
        </div>

        {{-- COLUMNA DERECHA --}}
        <aside class="lg:col-span-5 relative">
            <div class="checkout-card p-5 md:p-6 lg:sticky lg:top-28">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            Resumen del pedido
                        </h2>
                        <p class="text-sm text-slate-500 mt-0.5">
                            Revisa tus productos antes de enviar.
                        </p>
                    </div>

                    <div class="w-11 h-11 rounded-2xl bg-brand-soft flex items-center justify-center text-brand">
                        <x-heroicon-o-shopping-cart class="w-6 h-6" />
                    </div>
                </div>

                <button
                    type="button"
                    onclick="clearCheckoutCart()"
                    class="w-full mb-4 border border-red-100 bg-red-50 text-red-600 font-black py-2.5 rounded-2xl hover:bg-red-100 transition text-sm"
                >
                    Vaciar carrito
                </button>

                <div
                    id="checkout-items"
                    class="space-y-3 mb-5 max-h-72 overflow-y-auto pr-1 hide-scrollbar"
                ></div>

                <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4">
                    <div class="space-y-2 text-sm text-slate-600 font-semibold">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span id="chk-subtotal" class="text-slate-800">S/ 0.00</span>
                        </div>

                        <div class="flex justify-between" id="chk-delivery-row">
                            <span>Envío</span>
                            <span id="chk-delivery-fee" class="text-slate-800">S/ 0.00</span>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 my-4"></div>

                    <div class="flex justify-between items-end">
                        <span class="text-slate-800 font-black text-lg">Total</span>
                        <span id="chk-total" class="font-black text-3xl text-brand leading-none">S/ 0.00</span>
                    </div>
                </div>

                <button
                    id="btn-submit-order"
                    type="button"
                    onclick="processCheckout('{{ $tenant->phone }}')"
                    class="mt-5 w-full bg-[#25D366] text-white font-black py-4 rounded-2xl flex justify-center items-center gap-2 hover:bg-[#128C7E] shadow-lg shadow-[#25D366]/25 transition-transform active:scale-95 text-base md:text-lg disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.883-.653-1.48-1.459-1.653-1.756-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>

                    Enviar pedido por WhatsApp
                </button>

                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700 leading-relaxed">
                    <strong>Importante:</strong> al enviar el pedido se abrirá WhatsApp para confirmar la compra con la tienda.
                </div>
            </div>
        </aside>
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

    function saveCheckoutCart() {
        localStorage.setItem('carrito_{{ $tenant->id }}', JSON.stringify(chkCart));
    }

    function removeCheckoutItem(productId) {
        chkCart = chkCart.filter(item => Number(item.product_id) !== Number(productId));

        saveCheckoutCart();

        if (chkCart.length === 0) {
            window.location.href = '/productos';
            return;
        }

        updateCheckoutTotal();
    }

    function clearCheckoutCart() {
        if (chkCart.length === 0) {
            return;
        }

        if (!confirm('¿Deseas vaciar todo el carrito?')) {
            return;
        }

        chkCart = [];
        localStorage.removeItem('carrito_{{ $tenant->id }}');
        window.location.href = '/productos';
    }

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
                <div class="flex justify-between items-start gap-3 bg-white p-3 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="bg-brand-soft text-brand font-black min-w-8 h-8 px-2 rounded-xl text-xs flex items-center justify-center">
                            ${item.quantity}
                        </span>

                        <div class="min-w-0">
                            <p class="font-black text-sm text-slate-800 leading-tight line-clamp-2">
                                ${item.name}
                            </p>

                            <p class="text-xs text-slate-400 font-bold mt-1">
                                S/ ${itemPrice.toFixed(2)} x ${item.unit}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <span class="font-black text-brand whitespace-nowrap">
                            S/ ${itemTotal.toFixed(2)}
                        </span>

                        <button
                            type="button"
                            onclick="removeCheckoutItem(${item.product_id})"
                            class="text-xs font-black text-red-500 bg-red-50 hover:bg-red-100 px-2.5 py-1 rounded-xl transition"
                        >
                            Eliminar
                        </button>
                    </div>
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
        const btnSubmit = document.getElementById('btn-submit-order');
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
