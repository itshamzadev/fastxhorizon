(function ($) {
    "use strict";

    function initialiseProductTitleTooltips() {
        $('.fxh-product-card__title').each(function () {
            const title = this;
            const link = title.querySelector('.fxh-product-card__title-link');
            if (!link) return;

            const truncated = link.scrollHeight > link.clientHeight + 1;
            title.classList.toggle('is-truncated', truncated);
            if (truncated) {
                const tooltip = title.querySelector('.fxh-product-card__tooltip');
                if (tooltip && !tooltip.id) {
                    tooltip.id = 'fxh-product-tooltip-' + Math.random().toString(36).slice(2);
                }
                if (tooltip) link.setAttribute('aria-describedby', tooltip.id);
            } else {
                link.removeAttribute('aria-describedby');
            }
        });
    }

    $(window).on('load resize', initialiseProductTitleTooltips);
    $(function () { setTimeout(initialiseProductTitleTooltips, 50); });

    $(document).on("click", ".fxh-product-card__add-to-cart", function (event) {
        event.preventDefault();
        event.stopPropagation();

        var formSelector = $(this).data("form");
        if (formSelector && typeof addToCart === "function") {
            addToCart(formSelector);
        }
    });
})(jQuery);
