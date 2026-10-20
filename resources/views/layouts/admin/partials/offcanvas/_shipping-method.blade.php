@include("layouts.admin.partials.offcanvas._view-guideline-button")

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasSetupGuide" aria-labelledby="offcanvasSetupGuideLabel"
     data-status="{{ request('offcanvasShow') && request('offcanvasShow') == 'offcanvasSetupGuide' ? 'show' : '' }}">

    <div class="offcanvas-header bg-body">
        <h3 class="mb-0">{{ translate('Shipping_Method') }}</h3>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">

        <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
            <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                <button class="btn-collapse d-flex gap-3 align-items-center bg-transparent border-0 p-0" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseShippingMethod_01" aria-expanded="true">
                    <div class="btn-collapse-icon border bg-light icon-btn rounded-circle text-dark collapsed">
                        <i class="fi fi-sr-angle-right"></i>
                    </div>
                    <span class="fw-bold text-start">{{ translate('shipping_responsibility') }}</span>
                </button>

            </div>

            <div class="collapse mt-3 show" id="collapseShippingMethod_01">
                <div class="card card-body">
                    <p class="fs-12">
                        {{ translate('in-house_shipping_means_that_when_this_shipping_method_is_selected_for_orders,_the_platform_administrator_will_take_full_responsibility_for_the_shipping_process,_including_packaging_and_delivery.') }}
                    </p>
                    <p class="fs-12">
                        {{ translate('vendor_wise_shipping_means_that_when_this_shipping_method_is_selected_for_an_order,_the_individual_vendor_selling_the_product_will_be_responsible_for_handling_all_aspects_of_the_shipping_process_directly_to_the_customer.') }}
                    </p>
                </div>
            </div>
        </div>
        @if ($isCourierAddonPublished)
            <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
                <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapseShippingMethod_04" aria-expanded="true">
                        <div class="btn-collapse-icon border bg-light icon-btn rounded-circle text-dark collapsed">
                            <i class="fi fi-sr-angle-right"></i>
                        </div>
                        <span class="fw-bold text-start">{{ translate('Third_Party_Delivery_Service') }}</span>
                    </button>

                </div>

                <div class="collapse mt-3" id="collapseShippingMethod_04">
                    <div class="card card-body">
                        <p class="fs-12">
                            {{ translate('connects_your_store_to_the_courier_companies_you_have_set_up_under_delivery_partner_integration.') }}
                        </p>
                        <ul class="d-flex flex-column gap-12 fs-12">
                            <li>
                                <strong>{{ translate('when_on') }}:</strong>
                                {{ translate('delivery_partners_become_selectable_on_the_order_details_page,_so_an_order_can_be_booked_with_a_courier_instead_of_being_delivered_by_your_own_team.') }}
                                {{ translate('your_existing_shipping_methods_keep_working_exactly_as_before,_this_adds_a_way_to_ship_instead_of_replacing_one.') }}
                            </li>
                            <li>
                                <strong>{{ translate('when_off') }}:</strong>
                                {{ translate('delivery_partners_are_no_longer_offered_on_the_order_details_page_and_every_order_is_shipped_with_the_default_shipping_method.') }}
                                {{ translate('saved_partner_credentials_are_kept.') }}
                            </li>
                            <li>
                                <strong>{{ translate('who_selects_the_partner') }}:</strong>
                                {{ translate('follows_the_shipping_responsibility_setting,_with_inhouse_shipping_the_admin_books_the_shipment_and_with_vendor_wise_shipping_the_vendor_does.') }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
        @if ($isThirdPartyDeliveryEnabled)
            <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
                <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapseShippingMethod_05" aria-expanded="true">
                        <div class="btn-collapse-icon border bg-light icon-btn rounded-circle text-dark collapsed">
                            <i class="fi fi-sr-angle-right"></i>
                        </div>
                        <span class="fw-bold text-start">{{ translate('Vendor_Delivery_Partner_Setup') }}</span>
                    </button>

                </div>

                <div class="collapse mt-3" id="collapseShippingMethod_05">
                    <div class="card card-body">
                        <p class="fs-12">
                            {{ translate('decides_whether_vendors_manage_their_own_courier_accounts.') }}
                            {{ translate('this_option_is_available_only_while_third_party_delivery_mode_is_on.') }}
                        </p>
                        <ul class="d-flex flex-column gap-12 fs-12">
                            <li>
                                <strong>{{ translate('when_on') }}:</strong>
                                {{ translate('each_vendor_gets_a_delivery_partner_integration_page_and_ships_on_their_own_carrier_accounts_and_credentials.') }}
                            </li>
                            <li>
                                <strong>{{ translate('when_off') }}:</strong>
                                {{ translate('that_page_is_hidden_from_the_vendor_panel_and_vendor_app,_and_the_credentials_vendors_already_saved_are_kept_instead_of_being_deleted.') }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
        <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
            <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                <button class="btn-collapse d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseShippingMethod_02" aria-expanded="true">
                    <div class="btn-collapse-icon border bg-light icon-btn rounded-circle text-dark collapsed">
                        <i class="fi fi-sr-angle-right"></i>
                    </div>
                    <span class="fw-bold text-start">{{ translate('add_order_wise_shipping_method') }}</span>
                </button>

            </div>

            <div class="collapse mt-3" id="collapseShippingMethod_02">
                <div class="card card-body">
                    <p class="fs-12">
                        {{ translate('create_custom_shipping_options_by_setting_a_title_(what_customers_see),_shipping_duration_(estimated_delivery_time),_and_shipping_cost_(delivery_fee).') }}
                         {{ translate('this_lets_you_offer_various_delivery_speeds_and_prices_to_customers.') }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
            <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                <button class="btn-collapse d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseShippingMethod_03" aria-expanded="true">
                    <div class="btn-collapse-icon border bg-light icon-btn rounded-circle text-dark collapsed">
                        <i class="fi fi-sr-angle-right"></i>
                    </div>
                    <span class="fw-bold text-start">{{ translate('list_of_order_wise_shipping_method') }}</span>
                </button>

            </div>

            <div class="collapse mt-3" id="collapseShippingMethod_03">
                <div class="card card-body">
                    <p class="fs-12">
                        {{ translate('list_of_order-wise_shipping_methods') }}
                    </p>
                    <ul class="d-flex flex-column gap-12 fs-12">
                        <li>
                            <strong>{{ translate('title') }}:</strong>
                            {{ translate('name_of_the_shipping_option.') }}
                        </li>
                        <li>
                            <strong>{{ translate('shipping_duration') }}:</strong>
                            {{ translate('estimated_delivery_time.') }}
                        </li>
                        <li>
                            <strong>{{ translate('cost') }}:</strong>
                            {{ translate('shipping_fee.') }}
                        </li>
                        <li>
                            <strong>{{ translate('status') }}:</strong>
                            {{ translate('enabled_or_disabled.') }}
                        </li>
                        <li>
                            <strong>{{ translate('actions') }}:</strong>
                            {{ translate('edit_or_delete.') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
