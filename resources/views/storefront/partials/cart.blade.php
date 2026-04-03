<style>
    .toggle-radio:checked + div {
        border-color: {{ $tenant->primary_color ?? '#4f46e5' }} !important;
        background-color: {{ $tenant->primary_color ?? '#4f46e5' }}15 !important;
        color: {{ $tenant->primary_color ?? '#4f46e5' }} !important;
    }
</style>

<div id="cart-modal" class="fixed inset-0 bg-black/60 z-[60] hidden flex-col justify-end backdrop-blur-sm transition-all duration-300">
    <div class="bg-white w-full max-w-4xl mx-auto rounded-t-3xl h-[90vh] flex flex-col shadow-2xl">

        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-xl font-black text-gray-800">Tu Pedido</h2>
            <button onclick="toggleCart()" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center font-bold text-xl">&times;</button>
        </div>

        <div class="p-4 overflow-y-auto flex-1 bg-gray-50/50" id="cart-items"></div>

        <div class="p-4 border-t bg-white">

            <div class="space-y-1 mb-3 text-sm">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal:</span>
                    <span id="cart-subtotal">S/ 0.00</span>
                </div>
                {{-- Modificamos este ID para cambiar el precio con JS --}}
                <div id="delivery-fee-row" class="hidden">
                    <div class="flex justify-between text-gray-500">
                        <span>Costo de Envío:</span>
                        <span id="delivery-fee-display">S/ 0.00</span>
                    </div>
                </div>
                <div class="flex justify-between font-black text-xl text-brand pt-2 border-t border-gray-100">
                    <span class="text-gray-800">Total:</span>
                    <span id="cart-total">S/ 0.00</span>
                </div>
            </div>

            @if($tenant->yape_number)
                <div class="bg-purple-50 border border-purple-100 rounded-lg p-3 mb-4 flex items-center gap-3">
                    <div class="bg-purple-500 text-white font-bold p-2 rounded-lg text-xs">YAPE / PLIN</div>
                    <div>
                        <p class="text-xs text-purple-600 font-medium">Asegura tu pedido pagando al:</p>
                        <p class="font-black text-purple-800 tracking-wide">{{ $tenant->yape_number }}</p>
                    </div>
                </div>
            @endif

            <div class="flex gap-3 mb-4">
                <label class="flex-1 cursor-pointer">
                    <input type="radio" name="order_type" value="delivery" class="toggle-radio sr-only" checked onchange="updateCartUI()">
                    <div class="text-center p-3 border-2 rounded-xl border-gray-200 text-gray-500 font-bold text-sm transition-all">
                        🛵 Delivery
                    </div>
                </label>
                <label class="flex-1 cursor-pointer">
                    <input type="radio" name="order_type" value="pickup" class="toggle-radio sr-only" onchange="updateCartUI()">
                    <div class="text-center p-3 border-2 rounded-xl border-gray-200 text-gray-500 font-bold text-sm transition-all">
                        🏪 Recojo en Tienda
                    </div>
                </label>
            </div>

            <div class="space-y-3 mb-4">
                <div class="flex gap-2">
                    <input type="text" id="cliente-nombre" placeholder="Nombre completo" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand">
                    <input type="number" id="cliente-dni" placeholder="DNI (Opcional)" class="w-1/3 p-3 border-gray-200 bg-gray-50 border rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand">
                </div>

                <div id="delivery-fields" class="space-y-3">
                    {{-- 🌟 SELECT DE DISTRITOS CON EVENTO ONCHANGE --}}
                    <div class="relative">
                        <select id="cliente-distrito" onchange="updateCartUI()" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand appearance-none text-gray-600">
                            <option value="" disabled selected>Selecciona tu distrito...</option>
                            <option value="La Esperanza">La Esperanza</option>
                            <option value="Florencia de Mora">Florencia de Mora</option>
                            <option value="El Milagro">El Milagro</option>
                            <option value="El Porvenir">El Porvenir</option>
                            <option value="Huanchaco">Huanchaco</option>
                            <option value="Alto Trujillo">Alto Trujillo</option>
                            <option value="Victor Larco">Victor Larco</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>

                    <input type="text" id="cliente-direccion" placeholder="Dirección exacta" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand">
                </div>

                <textarea id="cliente-notas" rows="2" placeholder="Notas (Ej: Sin cebolla, billete de 50)" class="w-full p-3 border-gray-200 bg-gray-50 border rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand"></textarea>
            </div>

            <button onclick="sendToWhatsApp('{{ $tenant->phone }}')" class="w-full bg-[#25D366] text-white font-bold py-4 rounded-xl flex justify-center items-center gap-2 hover:bg-[#128C7E] shadow-lg text-lg">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.883-.653-1.48-1.459-1.653-1.756-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Enviar Pedido
            </button>
        </div>
    </div>
</div>

<script>
    let cart = [];
    const baseDeliveryFee = parseFloat("{{ $tenant->delivery_fee ?? 0 }}");

    function decreaseQuantity(name) {
        let itemIndex = cart.findIndex(i => i.name === name);
        if (itemIndex !== -1) {
            if (cart[itemIndex].quantity > 1) {
                cart[itemIndex].quantity--;
            } else {
                cart.splice(itemIndex, 1);
            }
            updateCartUI();
        }
    }

    function addToCart(name, price, unit = 'NIU') {
        let item = cart.find(i => i.name === name);
        if (item) {
            item.quantity++;
        } else {
            // Guardamos la unidad en la memoria del carrito
            cart.push({ name: name, price: price, quantity: 1, unit: unit });
        }
        updateCartUI();
    }

    function updateCartUI() {
        let totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.getElementById('cart-count').innerText = totalItems;

        let isDelivery = document.querySelector('input[name="order_type"]:checked').value === 'delivery';
        document.getElementById('delivery-fields').style.display = isDelivery ? 'block' : 'none';

        let cartHtml = '';
        let subtotal = 0;

        cart.forEach((item) => {
            let itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            cartHtml += `
                <div class="flex justify-between items-center mb-3 bg-white p-3 rounded-xl shadow-sm">
                    <div class="flex-1">
                        <p class="font-bold text-sm text-gray-800">${item.name}</p>
                        {{-- 🌟 Mostramos la unidad en el desglose --}}
                        <p class="text-xs text-gray-500 font-medium">S/ ${item.price.toFixed(2)} x ${item.unit}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center bg-gray-100 rounded-lg p-1">
                            <button onclick="decreaseQuantity('${item.name}')" class="w-7 h-7 font-bold text-gray-600">-</button>
                            <span class="w-8 text-center font-bold text-sm">${item.quantity}</span>
                            {{-- 🌟 IMPORTANTE: Pasarle la unidad al botón de '+' del carrito también --}}
                            <button onclick="addToCart('${item.name}', ${item.price}, '${item.unit}')" class="w-7 h-7 font-bold text-gray-600">+</button>
                        </div>
                        <span class="font-black text-brand w-16 text-right">S/ ${itemTotal.toFixed(2)}</span>
                    </div>
                </div>
            `;
        });

        document.getElementById('cart-items').innerHTML = cartHtml || '<p class="text-center text-gray-500 mt-10">Tu carrito está vacío.</p>';
        document.getElementById('cart-subtotal').innerText = 'S/ ' + subtotal.toFixed(2);

        // 🌟 LÓGICA DE PRECIO DINÁMICO POR DISTRITO
        let currentDeliveryFee = baseDeliveryFee;
        let selectedDistrict = document.getElementById('cliente-distrito').value;

        // Si es delivery, se seleccionó un distrito, y NO es La Esperanza, aumentamos 5 soles
        if (isDelivery && selectedDistrict && selectedDistrict !== 'La Esperanza') {
            currentDeliveryFee += 5.00;
        }

        const deliveryFeeRow = document.getElementById('delivery-fee-row');
        if (isDelivery && subtotal > 0) {
            deliveryFeeRow.classList.remove('hidden');
            document.getElementById('delivery-fee-display').innerText = 'S/ ' + currentDeliveryFee.toFixed(2);
        } else {
            deliveryFeeRow.classList.add('hidden');
        }

        let finalTotal = subtotal;
        if (isDelivery && subtotal > 0) finalTotal += currentDeliveryFee;

        document.getElementById('cart-total').innerText = 'S/ ' + finalTotal.toFixed(2);
    }

    function toggleCart() {
        document.getElementById('cart-modal').classList.toggle('hidden');
        document.getElementById('cart-modal').classList.toggle('flex');
    }

    function sendToWhatsApp(phone) {
        if (cart.length === 0) { alert('Agrega productos a tu pedido.'); return; }

        const nombre = document.getElementById('cliente-nombre').value.trim();
        const dni = document.getElementById('cliente-dni').value.trim();
        const notas = document.getElementById('cliente-notas').value.trim();
        const orderType = document.querySelector('input[name="order_type"]:checked').value;

        if (!nombre) { alert('Ingresa tu nombre.'); document.getElementById('cliente-nombre').focus(); return; }

        // 🌟 DISEÑO TIPO TICKET DE VENTA (100% Seguro, Elegante y Profesional)
        let text = `========================\n`;
        text += `   NUEVO PEDIDO WEB   \n`;
        text += `========================\n\n`;

        text += `[ DATOS DEL CLIENTE ]\n`;
        text += `Nombre: ${nombre}\n`;
        if (dni) text += `DNI/RUC: ${dni}\n`;

        let finalTotal = 0;
        let currentDeliveryFee = baseDeliveryFee;

        text += `\n[ MÉTODO DE ENTREGA ]\n`;
        if (orderType === 'delivery') {
            const distrito = document.getElementById('cliente-distrito').value;
            const direccion = document.getElementById('cliente-direccion').value.trim();

            if (!distrito) { alert('Por favor, selecciona un distrito.'); document.getElementById('cliente-distrito').focus(); return; }
            if (!direccion) { alert('Por favor, ingresa tu dirección.'); document.getElementById('cliente-direccion').focus(); return; }

            // La lógica de los 5 soles extra
            if (distrito !== 'La Esperanza') {
                currentDeliveryFee += 5.00;
            }

            text += `=> DELIVERY\n`;
            text += `Distrito: ${distrito}\n`;
            text += `Dirección: ${direccion}\n`;
        } else {
            text += `=> RECOJO EN TIENDA\n`;
        }

        text += `\n[ DETALLE DEL PEDIDO ]\n`;
        cart.forEach(item => {
            let itemTotal = item.price * item.quantity;
            finalTotal += itemTotal;
            // 🌟 Agregamos la unidad al mensaje de WhatsApp
            text += `+ ${item.quantity} ${item.unit} x ${item.name} (S/ ${itemTotal.toFixed(2)})\n`;
        });

        if (orderType === 'delivery' && currentDeliveryFee > 0) {
            text += `+ Tarifa Delivery (S/ ${currentDeliveryFee.toFixed(2)})\n`;
            finalTotal += currentDeliveryFee;
        }

        text += `------------------------\n`;
        text += `*TOTAL A PAGAR: S/ ${finalTotal.toFixed(2)}*\n`;
        text += `------------------------\n`;

        if (notas) text += `\n[ NOTAS ADICIONALES ]\n${notas}`;

        let waPhone = phone ? phone.replace(/[^0-9]/g, '') : '';
        if(waPhone.length === 9) waPhone = '51' + waPhone;

        const encodedText = encodeURIComponent(text);

        window.open(`https://wa.me/${waPhone}?text=${encodedText}`, '_blank');
    }
</script>
