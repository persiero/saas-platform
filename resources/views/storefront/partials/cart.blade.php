{{-- 🌟 1. BACKDROP --}}
<div id="cart-backdrop" class="fixed inset-0 bg-black/50 z-40 hidden transition-opacity duration-300 opacity-0" onclick="toggleCart()"></div>

{{-- 🌟 2. PANEL LATERAL (Solo Productos) --}}
<div id="cart-drawer" class="fixed inset-y-0 right-0 z-50 w-full md:w-[400px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">

    <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-white z-10">
        <h2 class="text-xl font-black text-gray-800 flex items-center gap-2">
            <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            Tu Carrito
        </h2>
        <button onclick="toggleCart()" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center font-bold text-xl transition-colors">&times;</button>
    </div>

    {{-- ZONA SCROLL (Solo los items) --}}
    <div class="flex-1 overflow-y-auto bg-gray-50/50 p-4" id="cart-items"></div>

    {{-- PIE FIJO (Total y Botón de Checkout) --}}
    <div class="p-4 bg-white border-t border-gray-100 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-10">
        <div class="flex justify-between font-black text-xl text-brand mb-4">
            <span class="text-gray-800">Subtotal:</span>
            <span id="cart-drawer-total">S/ 0.00</span>
        </div>

        {{-- 🌟 Redirige a la nueva página de Checkout --}}
        <a href="/checkout" class="w-full bg-brand text-white font-bold py-3.5 rounded-xl flex justify-center items-center gap-2 hover:opacity-90 shadow-lg shadow-brand/30 transition-all active:scale-95 text-base">
            Continuar Compra
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </a>
    </div>
</div>

<script>
    let cart = JSON.parse(localStorage.getItem('carrito_{{ $tenant->id }}')) || [];

    // Limpieza por si antes había productos guardados sin product_id
    cart = cart.filter(item => item.product_id);
    saveCart();

    function saveCart() {
        localStorage.setItem('carrito_{{ $tenant->id }}', JSON.stringify(cart));
    }

    function decreaseQuantity(productId) {
        let itemIndex = cart.findIndex(i => Number(i.product_id) === Number(productId));

        if (itemIndex !== -1) {
            if (cart[itemIndex].quantity > 1) {
                cart[itemIndex].quantity--;
            } else {
                cart.splice(itemIndex, 1);
            }

            saveCart();
            updateCartUI();
        }
    }

    function addToCart(productId, name, price, unit = 'NIU') {
        let item = cart.find(i => Number(i.product_id) === Number(productId));

        if (item) {
            item.quantity++;
        } else {
            cart.push({
                product_id: productId,
                name: name,
                price: Number(price),
                quantity: 1,
                unit: unit
            });
        }

        saveCart();
        updateCartUI();

        const drawer = document.getElementById('cart-drawer');

        if (drawer.classList.contains('translate-x-full')) {
            toggleCart();
        }
    }

    function updateCartUI() {
        let totalItems = cart.reduce((sum, item) => sum + Number(item.quantity), 0);

        const countBadge = document.getElementById('cart-count');

        if (countBadge) {
            countBadge.innerText = totalItems;
        }

        let cartHtml = '';
        let subtotal = 0;

        cart.forEach((item) => {
            let itemTotal = Number(item.price) * Number(item.quantity);
            subtotal += itemTotal;

            cartHtml += `
                <div class="flex justify-between items-center mb-2 bg-white p-2.5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="flex-1 pr-2">
                        <p class="font-bold text-xs text-gray-800 leading-tight line-clamp-2">${item.name}</p>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mt-0.5">
                            S/ ${Number(item.price).toFixed(2)}
                            <span class="font-normal lowercase">x ${item.unit}</span>
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="font-black text-sm text-brand">S/ ${itemTotal.toFixed(2)}</span>
                        <div class="flex items-center bg-gray-50 border border-gray-200 rounded-lg">
                            <button onclick="decreaseQuantity(${item.product_id})" class="w-6 h-6 flex items-center justify-center font-bold text-gray-500 hover:text-brand transition">-</button>
                            <span class="w-5 text-center font-bold text-xs">${item.quantity}</span>
                            <button onclick='addToCart(${item.product_id}, ${JSON.stringify(item.name)}, ${Number(item.price)}, ${JSON.stringify(item.unit)})' class="w-6 h-6 flex items-center justify-center font-bold text-gray-500 hover:text-brand transition">+</button>
                        </div>
                    </div>
                </div>`;
        });

        const cartItemsDiv = document.getElementById('cart-items');

        if (cartItemsDiv) {
            cartItemsDiv.innerHTML = cartHtml || `
                <div class="text-center py-10 text-gray-400">
                    <p class="text-sm font-medium">Tu carrito está vacío</p>
                </div>`;
        }

        const totalDiv = document.getElementById('cart-drawer-total');

        if (totalDiv) {
            totalDiv.innerText = 'S/ ' + subtotal.toFixed(2);
        }
    }

    function toggleCart() {
        const drawer = document.getElementById('cart-drawer');
        const backdrop = document.getElementById('cart-backdrop');

        if (drawer.classList.contains('translate-x-full')) {
            backdrop.classList.remove('hidden');

            setTimeout(() => backdrop.classList.remove('opacity-0'), 10);

            drawer.classList.remove('translate-x-full');
            document.body.style.overflow = 'hidden';
        } else {
            backdrop.classList.add('opacity-0');
            drawer.classList.add('translate-x-full');

            setTimeout(() => {
                backdrop.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }, 300);
        }
    }

    document.addEventListener('DOMContentLoaded', () => updateCartUI());
</script>
