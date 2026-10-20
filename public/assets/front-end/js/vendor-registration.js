'use strict';
$(document).ready(function () {
    syncRegisteredBusinessFields();

    $('input[name="business_status"]').on('change', function () {
        syncRegisteredBusinessFields();
        $('.business-status-error, .license-type-error, .legal-documents-error').text('');
    });

    $('.proceed-to-next-btn').click(function () {
        let email = $('#email').val();
        let phone = $('.phone-input-with-country-picker').val();
        let password = $('#password').val();
        let confirmPassword = $('#confirm_password').val();
        let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        let getErrorMessages = $('#proceed-to-next-validation-message');
        if (email === '') {
            $('.mail-error').html(getErrorMessages.data('mail-error'));
            return;
        } else {
            $('.mail-error').html('');
        }
        if (!emailPattern.test(email)) {
            $('.mail-error').html(getErrorMessages.data('valid-mail'));
            return;
        } else {
            $('.mail-error').html('');
        }
        if (phone === '') {
            $('.phone-error').html(getErrorMessages.data('phone-error'));
            return;
        } else {
            $('.phone-error').html('');
        }
        if (password === '') {
            $('.password-error').html(getErrorMessages.data('enter-password'));
            return;
        } else {
            $('.password-error').html('');
        }
        if (confirmPassword === '') {
            $('.confirm-password-error').html(getErrorMessages.data('enter-confirm-password'));
            return;
        } else {
            $('.confirm-password-error').html('');
        }
        if (password.trim() !== confirmPassword.trim()) {
            $('.confirm-password-error').html(getErrorMessages.data('password-not-match'));
            return;
        } else {
            $('.confirm-password-error').html('');
        }
        $('.first-el').fadeOut(300);
        $('.second-el').fadeIn(300);
    });
});

function syncRegisteredBusinessFields() {
    const isRegistered = $('input[name="business_status"]:checked').val() === 'registered';
    const details = $('#registered-business-details');
    const fields = $('#license_type, #legal_documents');

    details.toggleClass('d-none', !isRegistered);
    fields.prop('disabled', !isRegistered).prop('required', isRegistered);
}

function validateBusinessDetails() {
    const messages = $('#seller-business-validation-messages');
    const isRegistered = $('input[name="business_status"]:checked').val() === 'registered';
    const shopName = $('#shop_name').val().trim();
    const legalName = $('#legal_name').val().trim();
    const licenseType = $('#license_type').val();
    const documents = $('#legal_documents')[0]?.files || [];
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    let valid = true;

    $('.legal-identity-error, .legal-name-error, .business-status-error, .license-type-error, .legal-documents-error').text('');

    if (!shopName) {
        $('.legal-identity-error').text(messages.data('store-name-required'));
        valid = false;
    }
    if (!legalName) {
        $('.legal-name-error').text(messages.data('legal-name-required'));
        valid = false;
    }
    if (!isRegistered && !$('input[name="business_status"]:checked').val()) {
        $('.business-status-error').text(messages.data('business-status-required'));
        valid = false;
    }

    if (isRegistered) {
        if (!licenseType) {
            $('.license-type-error').text(messages.data('license-type-required'));
            valid = false;
        }
        if (!documents.length) {
            $('.legal-documents-error').text(messages.data('legal-documents-required'));
            valid = false;
        } else {
            Array.from(documents).forEach(function (file) {
                if (!allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
                    $('.legal-documents-error').text(file.size > 5 * 1024 * 1024
                        ? messages.data('legal-document-size')
                        : messages.data('legal-document-invalid'));
                    valid = false;
                }
            });
        }
    }

    return valid;
}
$('.back-to-main-page').on('click', function () {
    $('.first-el').fadeIn(300);
    $('.second-el').fadeOut(300);
});

function submitRegistration() {
    if (!validateBusinessDetails()) {
        return;
    }

    let getText = $('#get-confirm-and-cancel-button-text');
    const getFormId = 'seller-registration';
    Swal.fire({
        title: getText.data('sure'),
        text: getText.data('message'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1455AC',
        cancelButtonColor: '#d33',
        cancelButtonText: getText.data('cancel'),
        confirmButtonText: getText.data('confirm'),
        reverseButtons: true
    }).then((result) => {
        if (result.value) {

            let formData = new FormData(document.getElementById(getFormId));
            $.ajaxSetup({
                headers: {
                    'X-XSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: $('#' + getFormId).attr('action'),
                data: formData,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $("#loading").addClass("d-grid");
                },
                success: function (data) {
                    if (data.errors) {
                        for (let index = 0; index < data.errors.length; index++) {
                            const error = data.errors[index];
                            if (!setRegistrationFieldError(error.error_code, error.message)) {
                                toastr.error(error.message, {
                                    CloseButton: true,
                                    ProgressBar: true
                                });
                            }
                        }
                    } else if (data.error) {
                        toastr.error(data.error, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    } else {
                        if (data.status) {
                            $('.registration-success-modal').modal('show');
                            setTimeout(function () {
                                location.href = data.redirectRoute;
                            }, 2000);
                        }
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON?.errors;
                        if (errors) {
                            Object.keys(errors).forEach(function (key) {
                                if (!setRegistrationFieldError(key, errors[key][0])) {
                                    toastr.error(errors[key][0]);
                                }
                            });
                        } else if (xhr.responseJSON?.error) {
                            toastr.error(xhr.responseJSON.error);
                        } else if (xhr.responseJSON?.message) {
                            toastr.error(xhr.responseJSON.message);
                        }
                    } else {
                        const errorMessage = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Something went wrong!';
                        toastr.error(errorMessage);
                    }
                }
                , complete: function () {
                    $("#loading").removeClass("d-grid");
                },
            })
        }
    })
}

function setRegistrationFieldError(field, message) {
    const fieldName = field.split('.')[0];
    const selectors = {
        shop_name: '.legal-identity-error',
        legal_name: '.legal-name-error',
        business_status: '.business-status-error',
        license_type: '.license-type-error',
        legal_documents: '.legal-documents-error'
    };

    if (!selectors[fieldName]) {
        return false;
    }

    $(selectors[fieldName]).text(message);
    return true;
}
$('#terms-checkbox').on('click', function () {
    if ($(this).is(':checked')) {
        $('#vendor-apply-submit').removeAttr('disabled');
    } else {
        $('#vendor-apply-submit').attr('disabled', 'disabled');
    }
});
