(function ($) {
    "use strict";

    $(document).on("click", ".fxh-product-card__add-to-cart", function (event) {
        event.preventDefault();
        event.stopPropagation();

        var formSelector = $(this).data("form");
        if (formSelector && typeof addToCart === "function") {
            addToCart(formSelector);
        }
    });
})(jQuery);
