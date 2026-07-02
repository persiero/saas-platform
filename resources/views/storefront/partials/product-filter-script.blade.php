<script>
    let currentCategory = 'all';

    function normalizeText(text) {
        return (text || '')
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function filterProducts() {
        applyFilters();
    }

    function filterCategory(category, btn) {
        currentCategory = normalizeText(category);

        const categorySelect = document.getElementById('categorySelect');

        if (categorySelect) {
            categorySelect.value = category;
        }

        const categorySelectLabel = document.getElementById('categorySelectLabel');

        if (categorySelectLabel) {
            categorySelectLabel.innerText = category === 'all' ? 'Todas las categorías' : category;
        }

        const allButtons = document.querySelectorAll('.category-btn');

        allButtons.forEach(button => {
            button.classList.remove(
                'active-category',
                'bg-brand',
                'text-white',
                'shadow-md',
                'scale-[1.02]'
            );

            button.classList.add(
                'bg-slate-100',
                'text-slate-700'
            );
        });

        if (btn) {
            btn.classList.remove('bg-slate-100', 'text-slate-700');
            btn.classList.add(
                'active-category',
                'bg-brand',
                'text-white',
                'shadow-md',
                'scale-[1.02]'
            );
        }

        applyFilters();
    }

    function filterCategoryFromSelect(category) {
        currentCategory = normalizeText(category);

        const buttons = document.querySelectorAll('.category-btn');

        buttons.forEach(button => {
            button.classList.remove(
                'active-category',
                'bg-brand',
                'text-white',
                'shadow-md',
                'scale-[1.02]'
            );

            button.classList.add('bg-slate-100', 'text-slate-700');
        });

        if (currentCategory === 'all') {
            const firstButton = document.querySelector('.category-btn');

            if (firstButton) {
                firstButton.classList.remove('bg-slate-100', 'text-slate-700');
                firstButton.classList.add(
                    'active-category',
                    'bg-brand',
                    'text-white',
                    'shadow-md',
                    'scale-[1.02]'
                );
            }
        } else {
            const matchingButton = Array.from(buttons).find(button => {
                return normalizeText(button.textContent.trim()) === currentCategory;
            });

            if (matchingButton) {
                matchingButton.classList.remove('bg-slate-100', 'text-slate-700');
                matchingButton.classList.add(
                    'active-category',
                    'bg-brand',
                    'text-white',
                    'shadow-md',
                    'scale-[1.02]'
                );
            }
        }

        applyFilters();
    }

    function resetFilters() {
        currentCategory = 'all';

        const searchInput = document.getElementById('searchInput');
        const categorySelect = document.getElementById('categorySelect');
        const minPrice = document.getElementById('minPrice');
        const maxPrice = document.getElementById('maxPrice');
        const unitSelect = document.getElementById('unitSelect');
        const availableOnly = document.getElementById('availableOnly');
        const sortSelect = document.getElementById('sortSelect');

        if (searchInput) searchInput.value = '';
        if (categorySelect) categorySelect.value = 'all';

        const categorySelectLabel = document.getElementById('categorySelectLabel');

        if (categorySelectLabel) {
            categorySelectLabel.innerText = 'Todas las categorías';
        }

        if (minPrice) minPrice.value = '';
        if (maxPrice) maxPrice.value = '';
        if (unitSelect) unitSelect.value = 'all';
        if (availableOnly) availableOnly.checked = false;
        if (sortSelect) sortSelect.value = 'default';

        const buttons = document.querySelectorAll('.category-btn');

        buttons.forEach(button => {
            button.classList.remove(
                'active-category',
                'bg-brand',
                'text-white',
                'shadow-md',
                'scale-[1.02]'
            );

            button.classList.add('bg-slate-100', 'text-slate-700');
        });

        const firstButton = document.querySelector('.category-btn');

        if (firstButton) {
            firstButton.classList.remove('bg-slate-100', 'text-slate-700');
            firstButton.classList.add(
                'active-category',
                'bg-brand',
                'text-white',
                'shadow-md',
                'scale-[1.02]'
            );
        }

        applyFilters();
    }

    function applyFilters() {
        const searchInput = document.getElementById('searchInput');
        const categorySelect = document.getElementById('categorySelect');
        const minPriceInput = document.getElementById('minPrice');
        const maxPriceInput = document.getElementById('maxPrice');
        const unitSelect = document.getElementById('unitSelect');
        const availableOnly = document.getElementById('availableOnly');
        const sortSelect = document.getElementById('sortSelect');

        const searchTerm = searchInput ? normalizeText(searchInput.value) : '';
        const selectedCategory = categorySelect ? normalizeText(categorySelect.value) : currentCategory;

        if (categorySelect) {
            currentCategory = selectedCategory || 'all';
        }

        const minPrice = minPriceInput && minPriceInput.value !== ''
            ? parseFloat(minPriceInput.value)
            : null;

        const maxPrice = maxPriceInput && maxPriceInput.value !== ''
            ? parseFloat(maxPriceInput.value)
            : null;

        const selectedUnit = unitSelect ? unitSelect.value : 'all';
        const onlyAvailable = availableOnly ? availableOnly.checked : false;
        const sort = sortSelect ? sortSelect.value : 'default';

        const products = Array.from(document.querySelectorAll('.product-card'));
        let visibleCount = 0;

        products.forEach(card => {
            const productName = normalizeText(card.dataset.name);
            const productCategory = normalizeText(card.dataset.category);
            const productPrice = parseFloat(card.dataset.price || 0);
            const productUnit = card.dataset.unit || '';
            const productStock = parseFloat(card.dataset.stock || 0);

            const matchesSearch = productName.includes(searchTerm);
            const matchesCategory = currentCategory === 'all' || productCategory === currentCategory;
            const matchesMinPrice = minPrice === null || productPrice >= minPrice;
            const matchesMaxPrice = maxPrice === null || productPrice <= maxPrice;
            const matchesUnit = selectedUnit === 'all' || productUnit === selectedUnit;
            const matchesAvailability = !onlyAvailable || productStock > 0;

            const shouldShow =
                matchesSearch &&
                matchesCategory &&
                matchesMinPrice &&
                matchesMaxPrice &&
                matchesUnit &&
                matchesAvailability;

            card.style.display = shouldShow ? 'flex' : 'none';

            if (shouldShow) {
                visibleCount++;
            }
        });

        sortProducts(sort);

        const resultsCount = document.getElementById('results-count');

        if (resultsCount) {
            if (searchTerm || currentCategory !== 'all') {
                resultsCount.innerText = `${visibleCount} resultado${visibleCount !== 1 ? 's' : ''}`;
            } else {
                resultsCount.innerText = 'Mostrando todos';
            }
        }

        const productsCountLabel = document.getElementById('products-count-label');

        if (productsCountLabel) {
            productsCountLabel.innerText = `${visibleCount} producto${visibleCount !== 1 ? 's' : ''} encontrado${visibleCount !== 1 ? 's' : ''}`;
        }

        const emptyState = document.getElementById('empty-state');

        if (emptyState) {
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }
    }

    function sortProducts(sort) {
        const grid = document.getElementById('products-grid');

        if (!grid) {
            return;
        }

        const cards = Array.from(grid.querySelectorAll('.product-card'));

        cards.sort((a, b) => {
            const nameA = normalizeText(a.dataset.name);
            const nameB = normalizeText(b.dataset.name);
            const priceA = parseFloat(a.dataset.price || 0);
            const priceB = parseFloat(b.dataset.price || 0);

            switch (sort) {
                case 'name_asc':
                    return nameA.localeCompare(nameB);

                case 'name_desc':
                    return nameB.localeCompare(nameA);

                case 'price_asc':
                    return priceA - priceB;

                case 'price_desc':
                    return priceB - priceA;

                default:
                    return 0;
            }
        });

        cards.forEach(card => grid.appendChild(card));
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyFilters();
    });
</script>