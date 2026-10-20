<form action="{{ route('admin.courier.config.update') }}" method="post"
      id="courier-{{ $provider['id'] }}-form"
      class="form-advance-validation non-ajax-form-validate" novalidate>
    @csrf
    @method('PUT')
    <input type="hidden" name="provider" value="{{ $provider['id'] }}">
    <input type="hidden" name="is_enabled" value="0">

    <div class="offcanvas offcanvas-end" tabindex="-1" id="courier-offcanvas-{{ $provider['id'] }}"
         aria-labelledby="courier-offcanvas-{{ $provider['id'] }}-label">
        <div class="offcanvas-header bg-body">
            <h3 class="mb-0 text-capitalize">{{ translate('Setup') }} - {{ $provider['label'] }}</h3>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
                <h4 class="text-capitalize">{{ $provider['label'] }}</h4>
                <p class="fs-12">
                    {{ $provider['label'] }}
                    {{ translate('shipping_stops_when_you_turn_this_off._your_other_configured_delivery_partners_are_not_affected') }}.
                </p>

                <div class="border rounded px-3 py-2 d-flex justify-content-between align-items-center bg-white">
                    <h4 class="text-capitalize mb-0">{{ translate('Status') }}</h4>
                    <label class="switcher" for="courier-{{ $provider['id'] }}-canvas-status">
                        <input class="switcher_input" type="checkbox" value="1" name="is_enabled"
                               id="courier-{{ $provider['id'] }}-canvas-status"
                               {{ $provider['is_enabled'] ? 'checked' : '' }}>
                        <span class="switcher_control"></span>
                    </label>
                </div>
            </div>

            <div class="p-12 p-sm-20 bg-section rounded mb-3 mb-sm-20">
                <h4 class="mb-3">{{ translate('API_Credentials') }}</h4>

                @if (count($provider['countries']) > 1)
                    <div class="mb-4">
                        @include('courier::partials._field-label', [
                            'for'      => 'courier-'.$provider['id'].'-country',
                            'text'     => translate('Operating_Country'),
                            'required' => true,
                            'help'     => translate('the_delivery_charge_estimate_and_the_country_specific_options_below_follow_this_selection'),
                        ])
                        <select class="form-select courier-searchable-select" name="country"
                                id="courier-{{ $provider['id'] }}-country"
                                data-placeholder="{{ translate('select_country') }}"
                                data-required-msg="{{ translate('Operating_Country').' '.translate('is_required') }}" required>
                            <option value="" disabled {{ $provider['country'] === '' ? 'selected' : '' }}>
                                {{ translate('select_country') }}
                            </option>
                            @foreach ($provider['countries'] as $country)
                                <option value="{{ $country['id'] }}" {{ $provider['country'] === $country['id'] ? 'selected' : '' }}>
                                    {{ $country['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (count($provider['environments']) > 1)
                    <div class="mb-4">
                        @include('courier::partials._field-label', [
                            'text'     => translate('Environment'),
                            'required' => true,
                            'help'     => translate('sandbox_is_for_testing_with_test_credentials._live_connects_to_the_real_delivery_platform.'),
                        ])
                        <div class="min-h-40 d-flex align-items-center flex-wrap gap-4 border rounded mb-2 px-3 py-2 bg-white">
                            @foreach ($provider['environments'] as $environment)
                                <div class="form-check d-flex gap-1">
                                    <input class="form-check-input radio--input courier-environment-input" type="radio"
                                           name="environment" value="{{ $environment }}"
                                           id="courier-{{ $provider['id'] }}-env-{{ $environment }}"
                                           data-base-url="{{ $provider['base_urls'][$environment] ?? '' }}"
                                           data-target="#courier-{{ $provider['id'] }}-base-url"
                                           {{ $provider['environment'] === $environment ? 'checked' : '' }}>
                                    <label class="form-check-label text-capitalize"
                                           for="courier-{{ $provider['id'] }}-env-{{ $environment }}">
                                        {{ translate($environment) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($provider['base_urls']))
                    <div class="mb-4">
                        @include('courier::partials._field-label', [
                            'for'  => 'courier-'.$provider['id'].'-base-url',
                            'text' => translate('API_Base_URL'),
                            'help' => translate('the_base_url_switches_automatically_with_the_selected_environment'),
                        ])
                        <div class="input-group">
                            <input type="text" readonly class="form-control bg-white"
                                   id="courier-{{ $provider['id'] }}-base-url"
                                   value="{{ $provider['base_urls'][$provider['environment']] ?? '' }}">
                            <button type="button" class="btn btn-outline-secondary courier-copy-btn"
                                    data-copy-target="#courier-{{ $provider['id'] }}-base-url">
                                <i class="fi fi-rr-copy"></i>
                            </button>
                        </div>
                    </div>
                @endif

                <div class="mb-4">
                    @include('courier::partials._field-label', [
                        'for'  => 'courier-'.$provider['id'].'-webhook-url',
                        'text' => translate('Callback_URL'),
                        'help' => translate('give_this_url_to_the_delivery_platform_so_shipment_status_updates_reach_your_own_account'),
                    ])
                    <div class="input-group">
                        <input type="text" readonly class="form-control bg-white"
                               id="courier-{{ $provider['id'] }}-webhook-url"
                               value="{{ $provider['webhook_url'] }}">
                        <button type="button" class="btn btn-outline-secondary courier-copy-btn"
                                data-copy-target="#courier-{{ $provider['id'] }}-webhook-url">
                            <i class="fi fi-rr-copy"></i>
                        </button>
                    </div>
                </div>

                @forelse ($provider['fields'] as $field)
                    @php($fieldValue = $provider['credentials'][$field['key']] ?? '')
                    @php($isCopyable = str_contains($field['key'], 'url'))
                    <div class="mb-4">
                        @include('courier::partials._field-label', [
                            'for'      => 'courier-'.$provider['id'].'-'.$field['key'],
                            'text'     => translate($field['label']),
                            'required' => !empty($field['required']),
                            'help'     => !empty($field['help']) ? translate($field['help']) : null,
                        ])

                        <div class="{{ $isCopyable ? 'input-group' : '' }}">
                            @if (($field['type'] ?? 'text') === 'select')
                                <select class="form-select courier-searchable-select" name="credentials[{{ $field['key'] }}]"
                                        id="courier-{{ $provider['id'] }}-{{ $field['key'] }}"
                                        data-placeholder="{{ translate('select') }} {{ translate($field['label']) }}"
                                        @if (!empty($field['required'])) data-required-msg="{{ translate($field['label']).' '.translate('is_required') }}" required @endif>
                                    @php($translatableOptions = $field['translatable_options'] ?? true)
                                    @foreach ($field['options'] ?? [] as $option)
                                        <option value="{{ showDemoModeInputValue(value: $option['id']) }}" {{ (string) $fieldValue === (string) $option['id'] ? 'selected' : '' }}>
                                            {{ $translatableOptions ? translate($option['label']) : $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $field['type'] ?? 'text' }}" class="form-control"
                                       name="credentials[{{ $field['key'] }}]"
                                       id="courier-{{ $provider['id'] }}-{{ $field['key'] }}"
                                       value="{{ showDemoModeInputValue(value: $fieldValue) }}"
                                       @isset($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endisset
                                       autocomplete="off"
                                       @if (!empty($field['required'])) data-required-msg="{{ translate($field['label']).' '.translate('is_required') }}" required @endif>
                            @endif
                            @if ($isCopyable)
                                <button type="button" class="btn btn-outline-secondary courier-copy-btn"
                                        data-copy-target="#courier-{{ $provider['id'] }}-{{ $field['key'] }}">
                                    <i class="fi fi-rr-copy"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="fs-12 text-muted mb-0">{{ translate('this_provider_needs_no_credentials') }}</p>
                @endforelse
            </div>
        </div>

        <div class="offcanvas-footer shadow-lg">
            <div class="d-flex justify-content-center flex-wrap gap-3 bg-white px-3 py-2">
                <button type="button" class="btn btn-secondary px-3 px-sm-4 flex-grow-1 courier-config-reset"
                        data-form="#courier-{{ $provider['id'] }}-form">{{ translate('reset') }}</button>
                <button type="{{ getDemoModeFormButton(type: 'button') }}"
                        class="btn btn-primary px-3 px-sm-4 flex-grow-1 {{ getDemoModeFormButton(type: 'class') }}">
                    {{ translate('submit') }}
                </button>
            </div>
        </div>
    </div>
</form>
