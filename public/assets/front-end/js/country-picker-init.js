"use strict";

function initializePhoneInput(selector, outputSelector) {
    const phoneInput = document.querySelector(selector);
    if (!phoneInput || phoneInput.hasAttribute("data-intl-initialized")) return;
    const phoneNumber = phoneInput.value;
    const countryCodeMatch = phoneNumber.replace(/[^0-9]/g, "");
    const initialCountry = countryCodeMatch
        ? `+${countryCodeMatch}`
        : ($(".system-default-country-code").data("default-country") || "pk").toLowerCase();

    let phoneInputInit = window.intlTelInput(phoneInput, {
        initialCountry: initialCountry.toLowerCase(),
        nationalMode: true,
        showSelectedDialCode: true,
        autoPlaceholder: "aggressive",
        customPlaceholder: (placeholder, countryData) => countryData?.iso2 === "pk" ? "3XXXXXXXXX" : placeholder,
        useFullscreenPopup: false,
    });
    if (!phoneInputInit.selectedCountryData.dialCode) {
        phoneInputInit.destroy();
        phoneInputInit = window.intlTelInput(phoneInput, {
            initialCountry: ($(".system-default-country-code").data("default-country") || "pk").toLowerCase(),
            nationalMode: true,
            showSelectedDialCode: true,
            autoPlaceholder: "aggressive",
            customPlaceholder: (placeholder, countryData) => countryData?.iso2 === "pk" ? "3XXXXXXXXX" : placeholder,
            useFullscreenPopup: false,
        });
    }
    $(outputSelector).val(
        "+" +
            phoneInputInit.selectedCountryData.dialCode +
            phoneInput.value.replace(/[^0-9]/g, "")
    );

    $(".iti__country").on("click", function () {
        $(outputSelector).val(
            "+" +
                $(this).data("dial-code") +
                phoneInput.value.replace(/[^0-9]/g, "")
        );
    });

    $(selector).on("keypress", function (event) {
        if (parseInt($(selector).val().length) > 15) {
            event.preventDefault();
        }
    });

    $(selector).on("keyup", function () {
        if (parseInt($(selector).val().length) < 4) {
            $(selector).addClass("border-danger");
        } else {
            $(selector).removeClass("border-danger");
        }
    });

    $(selector).on("keyup keypress change", function (event) {
        if (event.which < 48 || event.which > 57) {
            event.preventDefault();
        }
        $(outputSelector).val(
            "+" +
                phoneInputInit.selectedCountryData.dialCode +
                phoneInput.value.replace(/[^0-9]/g, "")
        );
        $(selector).val(phoneInput.value.replace(/[^0-9]/g, ""));
    });
}

$(document).ready(function () {
    try {
        initializePhoneInput(
            ".phone-input-with-country-picker",
            ".country-picker-phone-number"
        );
    } catch (error) {}
});
