document.addEventListener('DOMContentLoaded', function () {
    var burger = document.querySelector('[data-burger]');
    var menu = document.querySelector('[data-menu]');
    var loading = document.querySelector('[data-loading]');

    if (burger && menu) {
        burger.addEventListener('click', function () {
            menu.classList.toggle('open');
        });
    }

    document.querySelectorAll('form[data-loading-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (loading) loading.classList.add('active');
        });
    });

    document.querySelectorAll('form[data-add-cart]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var name = form.getAttribute('data-product-name') || 'ce produit';
            if (!confirm('Ajouter "' + name + '" au panier ?')) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var email = form.querySelector('input[type="email"]');
            var password = form.querySelector('input[type="password"]');
            var validEmail = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
            var validPassword = password && password.value.length >= 6;

            if (!validEmail || !validPassword) {
                event.preventDefault();
                alert('Veuillez saisir un email valide et un mot de passe de 6 caracteres minimum.');
            }
        });
    });

    var filters = document.querySelector('[data-filters]');
    if (filters) {
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-product-card]'));
        var inputs = filters.querySelectorAll('input, select');

        function applyFilters() {
            var category = (filters.querySelector('[name="filtre_categorie"]') || {}).value || '';
            var size = (filters.querySelector('[name="filtre_taille"]') || {}).value || '';
            var maxPrice = parseFloat((filters.querySelector('[name="filtre_prix"]') || {}).value || '0');

            cards.forEach(function (card) {
                var cardCategory = card.getAttribute('data-category') || '';
                var cardSize = card.getAttribute('data-size') || '';
                var cardPrice = parseFloat(card.getAttribute('data-price') || '0');
                var visible = true;

                if (category && cardCategory !== category) visible = false;
                if (size && cardSize !== size) visible = false;
                if (maxPrice > 0 && cardPrice > maxPrice) visible = false;

                card.style.display = visible ? '' : 'none';
            });
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', applyFilters);
            input.addEventListener('change', applyFilters);
        });
    }
});
