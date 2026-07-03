{{-- 🌟 1. BACKDROP --}}
<div id="cart-backdrop" class="fixed inset-0 bg-black/50 z-40 hidden transition-opacity duration-300 opacity-0" onclick="toggleCart()"></div>

{{-- 🌟 2. PANEL LATERAL (Solo Productos) --}}
<div id="cart-drawer" class="fixed inset-y-0 right-0 z-50 w-full sm:w-[430px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">

    <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-white z-10">
        <h2 class="text-xl font-black text-slate-900 flex items-center gap-2">
            <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            Tu Carrito
        </h2>
        <button onclick="toggleCart()" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center font-bold text-xl transition-colors">&times;</button>
    </div>

    {{-- ZONA SCROLL (Solo los items) --}}
    <div class="flex-1 overflow-y-auto bg-slate-50 p-4" id="cart-items"></div>

    {{-- PIE FIJO (Total y Botón de Checkout) --}}
    <div class="p-4 bg-white border-t border-gray-100 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-10">
        <div class="flex justify-between font-black text-xl text-brand mb-4">
            <span class="text-gray-800">Subtotal:</span>
            <span id="cart-drawer-total">S/ 0.00</span>
        </div>

        <button
            type="button"
            onclick="clearCart()"
            class="w-full mb-3 border border-red-100 bg-red-50 text-red-600 font-black py-2.5 rounded-2xl hover:bg-red-100 transition text-sm"
        >
            Vaciar carrito
        </button>

        {{-- 🌟 Redirige a la nueva página de Checkout --}}
        <a id="checkout-button" href="/checkout" class="w-full bg-brand text-white font-black py-3.5 rounded-2xl flex justify-center items-center gap-2 hover:opacity-90 shadow-brand transition-all active:scale-95 text-base">
            Continuar Compra
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </a>
    </div>
</div>

<script>
    let cart = [];

    try {
        cart = JSON.parse(localStorage.getItem('carrito_{{ $tenant->id }}')) || [];
    } catch (error) {
        cart = [];
        localStorage.removeItem('carrito_{{ $tenant->id }}');
    }

    cart = cart.filter(item => item.product_id);

    window.saveCart = function () {
        localStorage.setItem('carrito_{{ $tenant->id }}', JSON.stringify(cart));
    };

    window.decreaseQuantity = function (productId) {
        let itemIndex = cart.findIndex(i => Number(i.product_id) === Number(productId));

        if (itemIndex !== -1) {
            if (cart[itemIndex].quantity > 1) {
                cart[itemIndex].quantity--;
            } else {
                cart.splice(itemIndex, 1);
            }

            window.saveCart();
            window.updateCartUI();
        }
    };

    window.removeFromCart = function (productId) {
        cart = cart.filter(item => Number(item.product_id) !== Number(productId));

        window.saveCart();
        window.updateCartUI();
    };

    window.clearCart = function () {
        if (cart.length === 0) {
            return;
        }

        if (!confirm('¿Deseas vaciar todo el carrito?')) {
            return;
        }

        cart = [];

        window.saveCart();
        window.updateCartUI();
    };

    window.addToCart = function (productId, name, price, unit = 'NIU') {
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

        window.saveCart();
        window.updateCartUI();

        const drawer = document.getElementById('cart-drawer');

        if (drawer && drawer.classList.contains('translate-x-full')) {
            window.toggleCart();
        }
    };

    window.updateCartUI = function () {
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
                <div class="flex justify-between items-center mb-3 bg-white p-3 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex-1 pr-2">
                        <p class="font-bold text-xs text-slate-800 leading-tight line-clamp-2">${item.name}</p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase mt-0.5">
                            S/ ${Number(item.price).toFixed(2)}
                            <span class="font-normal lowercase">x ${item.unit}</span>
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-1">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm text-brand">S/ ${itemTotal.toFixed(2)}</span>

                            <button
                                onclick="removeFromCart(${item.product_id})"
                                class="w-7 h-7 flex items-center justify-center rounded-full bg-red-50 text-red-500 hover:bg-red-100 transition"
                                title="Eliminar producto"
                            >
                                ×
                            </button>
                        </div>

                        <div class="flex items-center bg-slate-50 border border-slate-200 rounded-xl">
                            <button onclick="decreaseQuantity(${item.product_id})" class="w-8 h-8 flex items-center justify-center font-black text-slate-500 hover:text-brand transition">-</button>
                            <span class="w-7 text-center font-black text-sm">${item.quantity}</span>
                            <button onclick='addToCart(${item.product_id}, ${JSON.stringify(item.name)}, ${Number(item.price)}, ${JSON.stringify(item.unit)})' class="w-8 h-8 flex items-center justify-center font-black text-slate-500 hover:text-brand transition">+</button>
                        </div>
                    </div>
                </div>`;
        });

        const cartItemsDiv = document.getElementById('cart-items');

        if (cartItemsDiv) {
            cartItemsDiv.innerHTML = cartHtml || `
                <div class="h-full flex flex-col items-center justify-center text-center py-14 text-slate-400">
                    <div class="w-20 h-20 rounded-full bg-white border border-slate-100 shadow-sm flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <p class="text-base font-black text-slate-600">Tu carrito está vacío</p>
                    <p class="text-sm text-slate-400 mt-1">Agrega productos para continuar.</p>
                </div>`;
        }

        const totalDiv = document.getElementById('cart-drawer-total');

        if (totalDiv) {
            totalDiv.innerText = 'S/ ' + subtotal.toFixed(2);
        }

        const checkoutButton = document.getElementById('checkout-button');

        if (checkoutButton) {
            if (totalItems === 0) {
                checkoutButton.classList.add('opacity-50', 'pointer-events-none');
            } else {
                checkoutButton.classList.remove('opacity-50', 'pointer-events-none');
            }
        }
    };

    window.toggleCart = function () {
        const drawer = document.getElementById('cart-drawer');
        const backdrop = document.getElementById('cart-backdrop');

        if (!drawer || !backdrop) {
            return;
        }

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
    };

    window.saveCart();

    document.addEventListener('DOMContentLoaded', () => {
        window.updateCartUI();
    });
</script>
