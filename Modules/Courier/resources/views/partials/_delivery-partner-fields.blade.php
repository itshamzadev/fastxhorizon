@php($bs = (int) ($courierBs ?? 5))
@php($selectClass = $bs === 5 ? 'form-select' : 'form-control')
@php($panelBg = $bs === 5 ? 'bg-section2' : 'bg-light')

<style>
    .courier-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        align-items: end;
    }
</style>

<input type="hidden" name="host_order_reference" value="{{ $courierOrder['id'] }}">
<input type="hidden" name="provider" id="courier_provider">
<input type="hidden" name="order_value" value="{{ $courierOrder['amount'] ?? $courierOrder['cod'] ?? 0 }}">

<div class="{{ $panelBg }} rounded-10 p-3 p-sm-20 d-flex flex-column gap-3" id="courier-fields-panel">
    <div class="alert alert-warning mb-0 fs-12 d-none" id="courier-lookup-notice" role="alert"></div>

    <div>
        @include('courier::partials._field-label', [
            'for'  => 'courier_store',
            'text' => translate('Pickup_Store'),
            'help' => translate('Your_own_store_or_warehouse_the_rider_collects_this_parcel_from'),
        ])
        <select class="{{ $selectClass }}" name="store_id" id="courier_store"
                data-stores-url="{{ route($courierRoutes['stores']) }}"
                data-placeholder="{{ translate('Search_or_use_default_pickup_store') }}">
            <option value="">{{ translate('Default_pickup_store') }}</option>
        </select>
    </div>

    <div>
        @include('courier::partials._field-label', [
            'for'         => 'courier_recipient_name',
            'text'        => translate('Receivers_Name'),
            'requiredFor' => 'recipient_name',
        ])
        <input type="text" class="form-control" name="recipient_name" id="courier_recipient_name"
               value="{{ $courierOrder['name'] ?? '' }}" required>
    </div>

    <div class="courier-grid">
        <div>
            @include('courier::partials._field-label', [
                'for'         => 'courier_recipient_phone',
                'text'        => translate('Receivers_Phone'),
                'requiredFor' => 'recipient_phone',
            ])
            <input type="text" class="form-control" name="recipient_phone" id="courier_recipient_phone"
                   value="{{ $courierOrder['phone'] ?? '' }}" inputmode="tel" data-numeric-field="phone" required>
        </div>
        <div>
            @include('courier::partials._field-label', [
                'for'         => 'courier_cod',
                'text'        => translate('Cash_to_Collect'),
                'requiredFor' => 'cod_amount',
                'help'        => !empty($courierOrder['cod_locked'])
                    ? translate('Order_already_paid_nothing_to_collect')
                    : translate('Amount_the_rider_collects_from_the_customer_Enter_0_if_the_order_is_already_paid'),
            ])
            <input type="number" step="0.01" min="0" class="form-control" name="cod_amount" id="courier_cod"
                   value="{{ $courierOrder['cod'] ?? 0 }}" inputmode="decimal" data-numeric-field="decimal" required
                   {{ !empty($courierOrder['cod_locked']) ? 'readonly' : '' }}>
        </div>
    </div>

    <div>
        @include('courier::partials._field-label', [
            'for'         => 'courier_recipient_address',
            'text'        => translate('Delivery_Address'),
            'requiredFor' => 'recipient_address',
            'help'        => translate('House_road_and_landmark_the_rider_needs_to_find_the_customer'),
        ])
        <textarea class="form-control" name="recipient_address" id="courier_recipient_address" rows="2"
                  placeholder="{{ translate('Ex') }}: 1/B/3 Farmgate, Dhaka" required>{{ $courierOrder['address'] ?? '' }}</textarea>
    </div>

    <div class="courier-grid" id="courier-location-row">
        <div class="courier-loc-col" data-level="city">
            @include('courier::partials._field-label', [
                'for'         => 'courier_city',
                'text'        => translate('Delivery_City'),
                'levelFor'    => 'city',
                'requiredFor' => 'city_id',
            ])
            <select class="{{ $selectClass }}" name="city_id" id="courier_city"
                    data-cities-url="{{ route($courierRoutes['cities']) }}"
                    data-zones-url="{{ route($courierRoutes['zones'], ['cityId' => '__ID__']) }}">
                <option value="">{{ translate('Select') }}</option>
            </select>
        </div>
        <div class="courier-loc-col" data-level="zone">
            @include('courier::partials._field-label', [
                'for'         => 'courier_zone',
                'text'        => translate('Delivery_Zone'),
                'levelFor'    => 'zone',
                'requiredFor' => 'zone_id',
            ])
            <select class="{{ $selectClass }}" name="zone_id" id="courier_zone"
                    data-areas-url="{{ route($courierRoutes['areas'], ['zoneId' => '__ID__']) }}">
                <option value="">{{ translate('Select') }}</option>
            </select>
        </div>
        <div class="courier-loc-col" data-level="area">
            @include('courier::partials._field-label', [
                'for'         => 'courier_area',
                'text'        => translate('Delivery_Area'),
                'levelFor'    => 'area',
                'requiredFor' => 'area_id',
            ])
            <select class="{{ $selectClass }}" name="area_id" id="courier_area">
                <option value="">{{ translate('Select') }}</option>
            </select>
        </div>
    </div>

    <div class="d-none" id="courier-international-address">
        <div class="courier-grid">
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_country',
                    'text'        => translate('Delivery_Country'),
                    'requiredFor' => 'country_code',
                ])
                <select class="{{ $selectClass }}" name="country_code" id="courier_country">
                    <option value="">{{ translate('Select') }}</option>
                    @foreach ($courierCountries ?? [] as $country)
                        <option value="{{ $country['id'] }}">{{ $country['name'] }} ({{ $country['id'] }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_postal_code',
                    'text'        => translate('Delivery_Postal_Code'),
                    'requiredFor' => 'postal_code',
                    'help'        => translate('The_ZIP_or_PIN_code_of_the_delivery_address'),
                ])
                <input type="text" class="form-control" name="postal_code" id="courier_postal_code">
            </div>
        </div>
        <div class="courier-grid mt-3">
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_city_name',
                    'text'        => translate('Delivery_City'),
                    'requiredFor' => 'city_name',
                ])
                <input type="text" class="form-control" name="city_name" id="courier_city_name">
            </div>
            <div>
                @include('courier::partials._field-label', [
                    'for'  => 'courier_state_province',
                    'text' => translate('State_or_Province'),
                ])
                <input type="text" class="form-control" name="state_province" id="courier_state_province">
            </div>
        </div>
    </div>

    <div class="d-none" id="courier-geo-address">
        <div class="courier-grid">
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_latitude',
                    'text'        => translate('Delivery_Point_Latitude'),
                    'requiredFor' => 'latitude',
                    'help'        => translate('Taken_from_the_customers_saved_delivery_location_when_there_is_one_Otherwise_open_Google_Maps_right_click_the_delivery_address_and_click_the_two_numbers_that_appear_The_first_one_is_the_latitude'),
                ])
                <input type="text" class="form-control" name="latitude" id="courier_latitude"
                       value="{{ $courierOrder['latitude'] ?? '' }}" placeholder="{{ translate('Ex') }}: 23.7461"
                       inputmode="decimal" data-numeric-field="signed-decimal">
            </div>
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_longitude',
                    'text'        => translate('Delivery_Point_Longitude'),
                    'requiredFor' => 'longitude',
                    'help'        => translate('The_second_of_the_two_numbers_from_the_same_Google_Maps_click_The_rider_is_sent_to_this_exact_point'),
                ])
                <input type="text" class="form-control" name="longitude" id="courier_longitude"
                       value="{{ $courierOrder['longitude'] ?? '' }}" placeholder="{{ translate('Ex') }}: 90.3742"
                       inputmode="decimal" data-numeric-field="signed-decimal">
            </div>
        </div>
    </div>

    <div class="d-none" id="courier-branch-address">
        <div class="courier-grid">
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_source_branch',
                    'text'        => translate('Pickup_Branch'),
                    'requiredFor' => 'source_branch',
                    'help'        => translate('The_partners_branch_that_collects_this_parcel'),
                ])
                <select class="{{ $selectClass }}" name="source_branch" id="courier_source_branch"
                        data-branches-url="{{ route($courierRoutes['cities']) }}">
                    <option value="">{{ translate('Select') }}</option>
                </select>
                <input type="hidden" name="source_branch_id" id="courier_source_branch_id">
            </div>
            <div>
                @include('courier::partials._field-label', [
                    'for'         => 'courier_destination_branch',
                    'text'        => translate('Delivery_Branch'),
                    'requiredFor' => 'destination_branch',
                    'help'        => translate('The_partners_branch_nearest_to_the_customer'),
                ])
                <select class="{{ $selectClass }}" name="destination_branch" id="courier_destination_branch">
                    <option value="">{{ translate('Select') }}</option>
                </select>
                <input type="hidden" name="destination_branch_id" id="courier_destination_branch_id">
            </div>
        </div>
    </div>

    <div class="courier-grid">
        <div class="d-none" id="courier-delivery-type-col">
            @include('courier::partials._field-label', [
                'for'  => 'courier_delivery_type',
                'text' => translate('Delivery_Service'),
                'help' => translate('The_delivery_speed_or_vehicle_this_partner_uses_for_the_parcel'),
            ])
            <select class="{{ $selectClass }}" name="delivery_type" id="courier_delivery_type"></select>
        </div>
        <div>
            @include('courier::partials._field-label', [
                'for'         => 'courier_weight',
                'text'        => translate('Parcel_Weight'),
                'requiredFor' => 'weight',
            ])
            <div class="input-group">
                <input type="number" step="0.01" min="0" class="form-control" name="weight" id="courier_weight"
                       placeholder="{{ translate('Ex') }}: 0.5" inputmode="decimal" data-numeric-field="decimal" required>
                @if ($bs === 5)
                    <span class="input-group-text">{{ translate('Kg') }}</span>
                @else
                    <div class="input-group-append"><span class="input-group-text">{{ translate('Kg') }}</span></div>
                @endif
            </div>
        </div>
        <div>
            @include('courier::partials._field-label', [
                'for'  => 'courier_quantity',
                'text' => translate('Number_of_Items'),
            ])
            <input type="number" min="1" class="form-control" name="quantity" id="courier_quantity"
                   placeholder="{{ translate('Ex') }}: 1" inputmode="numeric" data-numeric-field="integer">
        </div>
    </div>

    <div>
        @include('courier::partials._field-label', [
            'for'  => 'courier_item_description',
            'text' => translate('Parcel_Contents'),
            'help' => translate('Printed_on_the_shipping_label_so_the_rider_knows_what_is_being_carried'),
        ])
        <input type="text" class="form-control" name="item_description" id="courier_item_description"
               placeholder="{{ translate('Ex') }}: {{ translate('Cotton_t-shirts') }}">
    </div>

    <div>
        @include('courier::partials._field-label', [
            'for'  => 'courier_note',
            'text' => translate('Rider_Instructions'),
            'help' => translate('Anything_the_rider_should_know_before_delivering_this_parcel'),
        ])
        <textarea class="form-control" name="note" id="courier_note" rows="2"
                  placeholder="{{ translate('Ex') }}: {{ translate('Handle_with_care') }}"></textarea>
    </div>
</div>

<div class="bg-section rounded-10 p-3 d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3">
    <div>
        <span class="fw-semibold d-block">{{ translate('Estimated_Delivery_Charge') }}</span>
        <span class="fs-12 text-muted" id="courier-estimate-result">{{ translate('Fill_weight_and_location,_then_estimate') }}</span>
    </div>
    <button type="button" class="btn btn-outline-primary" id="courier-estimate-btn"
            data-estimate-url="{{ route($courierRoutes['estimate']) }}">
        {{ translate('Estimate') }}
    </button>
</div>
