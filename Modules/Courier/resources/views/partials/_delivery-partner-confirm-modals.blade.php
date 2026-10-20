@php($bs = (int) ($courierBs ?? 5))
@php($dismissAttribute = $bs === 5 ? 'data-bs-dismiss' : 'data-dismiss')
@php($titleClass = $bs === 5 ? 'fw-bold' : 'font-weight-bold')

<div class="modal fade" id="courierDispatchConfirmModal" tabindex="-1" aria-labelledby="courierDispatchConfirmLabel" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title {{ $titleClass }}" id="courierDispatchConfirmLabel">{{ translate('Send_this_order_to_the_delivery_partner') }}?</h5>
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
                    {{ translate('The_order_information_will_be_sent_to') }}
                    <strong class="text-capitalize courier-confirm-partner"></strong>.
                    {{ translate('Please_verify_the_delivery_address_recipient_information_contact_details_and_delivery_information_before_you_continue') }}
                </p>
                <div class="alert alert-warning fs-12 mb-0">
                    {{ translate('Incorrect_information_may_cause_delivery_issues_Correct_anything_that_is_wrong_before_confirming') }}
                </div>
            </div>
            <div class="modal-footer border-0 d-flex gap-3">
                <button type="button" class="btn btn-secondary flex-fill" {{ $dismissAttribute }}="modal">{{ translate('Review_Again') }}</button>
                <button type="button" class="btn btn-primary flex-fill" id="courier-dispatch-confirm">{{ translate('Confirm_and_Send') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="courierReviseConfirmModal" tabindex="-1" aria-labelledby="courierReviseConfirmLabel" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title {{ $titleClass }}" id="courierReviseConfirmLabel">{{ translate('Update_Delivery_Information') }}?</h5>
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
                    <strong class="text-capitalize courier-confirm-partner"></strong>.
                    {{ translate('Changing_this_information_may_create_a_mismatch_between_the_order_information_and_the_information_already_submitted_to_the_delivery_partner') }}
                </p>
                <div class="alert alert-warning fs-12 mb-0">
                    {{ translate('You_may_need_to_update_this_information_with_the_delivery_partner_manually_or_contact_them_if_the_shipment_has_already_been_processed') }}
                </div>
            </div>
            <div class="modal-footer border-0 d-flex gap-3">
                <button type="button" class="btn btn-secondary flex-fill" {{ $dismissAttribute }}="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="btn btn-primary flex-fill" id="courier-revise-confirm">{{ translate('Confirm_and_Update') }}</button>
            </div>
        </div>
    </div>
</div>
