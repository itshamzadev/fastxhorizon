@php($bs = (int) ($courierBs ?? 5))
@php($dismissAttribute = $bs === 5 ? 'data-bs-dismiss' : 'data-dismiss')
@php($titleClass = $bs === 5 ? 'fw-bold' : 'font-weight-bold')

<div class="modal fade" id="courierSelfDeliveryModal" tabindex="-1" aria-labelledby="courierSelfDeliveryModalLabel" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title {{ $titleClass }}" id="courierSelfDeliveryModalLabel">
                    {{ translate('Deliver_this_order_with_your_own_delivery_man') }}?
                </h5>
                @if ($bs === 5)
                    <button type="button" class="btn-close border-0 btn-circle bg-section2 shadow-none"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                @else
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                @endif
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    {{ translate('This_order_is_already_assigned_to') }}
                    <strong class="text-capitalize courier-self-delivery-partner">{{ $courierCurrentPartner }}</strong>.
                    {{ translate('Assigning_your_own_delivery_man_does_not_cancel_that_shipment_The_delivery_partner_may_still_collect_and_deliver_this_parcel') }}
                </p>
                <div class="alert alert-warning fs-12 mb-0">
                    {{ translate('Cancel_the_shipment_with_the_delivery_partner_directly_before_you_continue_otherwise_both_may_try_to_deliver_the_same_order') }}
                </div>
            </div>
            <div class="modal-footer border-0 d-flex gap-3">
                <button type="button" class="btn btn-secondary flex-fill" {{ $dismissAttribute }}="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="btn btn-primary flex-fill" id="courier-self-delivery-confirm">
                    {{ translate('Yes_Use_Own_Delivery_Man') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const select = document.getElementById('choose_delivery_type');
        const modal = document.getElementById('courierSelfDeliveryModal');
        const confirmBtn = document.getElementById('courier-self-delivery-confirm');
        if (!select || !modal || !confirmBtn) return;

        let confirmed = false;
        let lastValue = select.value;

        function toggleModal(show) {
            if (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                const instance = window.bootstrap.Modal.getOrCreateInstance(modal);
                show ? instance.show() : instance.hide();
            } else if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery(modal).modal(show ? 'show' : 'hide');
            }
        }

        document.addEventListener('change', function (event) {
            if (event.target !== select) return;

            if (select.value !== 'self_delivery') {
                confirmed = false;
                lastValue = select.value;
                return;
            }

            if (confirmed) {
                lastValue = select.value;
                return;
            }

            event.stopPropagation();
            select.value = lastValue;
            toggleModal(true);
        }, true);

        confirmBtn.addEventListener('click', function () {
            confirmed = true;
            toggleModal(false);
            select.value = 'self_delivery';
            select.dispatchEvent(new Event('change', {bubbles: true}));
        });
    })();
</script>
