@extends('layouts.vendor.app')

@section('title', translate('Delivery_Partner_Integration'))

@php($enabledCount = collect($providers)->where('is_enabled', true)->count())

@push('css_or_js')
    <style>
        .courier-search-clear {
            position: absolute;
            inset-inline-end: 2.75rem;
            inset-block-start: 50%;
            transform: translateY(-50%);
            z-index: 9;
            display: flex;
            align-items: center;
            padding: 0.25rem;
            border: none;
            background: transparent;
            line-height: 1;
            cursor: pointer;
        }

        .courier-search-clear:hover {
            color: var(--bs-dark) !important;
        }

        #courier-provider-search::-webkit-search-cancel-button {
            -webkit-appearance: none;
        }

        .courier-offcanvas {
            --courier-offcanvas-width: 500px;
        }

        .courier-offcanvas-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.50);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }

        .courier-offcanvas-content {
            position: fixed;
            top: 0;
            inset-inline-end: calc(-1 * var(--courier-offcanvas-width));
            width: var(--courier-offcanvas-width);
            max-width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            z-index: 1051;
            transition: inset-inline-end 0.3s ease-in-out;
        }

        .courier-offcanvas.is-open .courier-offcanvas-overlay {
            opacity: 1;
            visibility: visible;
        }

        .courier-offcanvas.is-open .courier-offcanvas-content {
            inset-inline-end: 0;
        }

        .courier-offcanvas-header {
            flex-shrink: 0;
        }

        .courier-offcanvas-body {
            flex-grow: 1;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .courier-offcanvas-footer {
            flex-shrink: 0;
        }

        body.courier-offcanvas-open {
            overflow: hidden;
        }

        @media (max-width: 575.98px) {
            .courier-offcanvas {
                --courier-offcanvas-width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="mb-4 pb-2">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/3rd-party.png') }}" alt="">
                {{ translate('Delivery_Partner_Integration') }}
            </h2>
        </div>

        <div class="bg-warning bg-opacity-10 fz-12 px-3 py-2 text-dark rounded d-flex gap-2 align-items-center mb-3">
            <i class="fi fi-sr-info text-warning"></i>
            <span>
                {{ translate('here_you_can_configure_delivery_partner_by_obtaining_the_necessary_credentials') }}
                ({{ translate('e.g., _api_keys') }})
                {{ translate('from_each_respective_delivery_platform') }}.
            </span>
        </div>

        @if (!($deliveryPartnerServiceEnabled ?? true))
            <div class="bg-danger bg-opacity-10 fz-12 px-3 py-2 text-dark rounded d-flex gap-2 align-items-center mb-3">
                <i class="fi fi-sr-triangle-warning text-danger"></i>
                <span>
                    {{ translate('third_party_delivery_service_is_currently_turned_off_by_the_admin_you_can_still_configure_delivery_partners_here_but_they_cannot_be_assigned_to_orders') }}
                </span>
            </div>
        @endif

        @if ($enabledCount <= 0)
            <div class="bg-danger bg-opacity-10 fz-12 px-3 py-2 text-dark rounded d-flex gap-2 align-items-center mb-3">
                <i class="fi fi-sr-triangle-warning text-danger"></i>
                <span>
                    {{ translate('currently_no_delivery_partner_supported_your_delivery_system._active_at_least_one_partner_to_use_3rd_party_delivery_system.') }}
                </span>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">{{ translate('Delivery_Partner_List') }}</h3>

                    <div class="form-group mb-0 flex-grow-1 max-w-300 min-w-100-mobile">
                        <div class="input-group">
                            <input type="search" id="courier-provider-search" class="form-control"
                                   placeholder="{{ translate('search_by_delivery_partner_name') }}">
                            <button type="button" id="courier-provider-search-clear"
                                    class="courier-search-clear text-muted d-none" aria-label="{{ translate('Clear') }}">
                                <i class="fi fi-rr-cross-small"></i>
                            </button>
                            <div class="input-group-append search-submit">
                                <button type="submit">
                                    <i class="fi fi-rr-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row gy-3" id="courier-provider-cards">
                    @forelse ($providers as $provider)
                        <div class="col-md-6 mb-3 courier-provider-cards">
                            <div class="card h-100">
                                <div class="card-body d-flex gap-3 justify-content-between align-items-center">
                                    <h4 class="text-capitalize mb-0 d-flex flex-wrap gap-2 align-items-center">
                                        {{ $provider['label'] }}
                                        @if (!$provider['is_configured'])
                                            <span class="badge badge-danger">{{ translate('Not_Configured') }}</span>
                                        @elseif ($provider['is_enabled'])
                                            <span class="badge badge-success">{{ translate('Enabled') }}</span>
                                        @else
                                            <span class="badge badge-info">{{ translate('Disabled') }}</span>
                                        @endif
                                    </h4>

                                    <div class="d-flex gap-3 align-items-center">
                                        @if ($provider['is_configured'])
                                            <form action="{{ route('vendor.courier.config.update') }}" method="post"
                                                  id="courier-{{ $provider['id'] }}-status-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="provider" value="{{ $provider['id'] }}">
                                                <input type="hidden" name="is_enabled" value="0">
                                                <label class="switcher" for="courier-{{ $provider['id'] }}-status">
                                                    <input class="switcher_input custom-modal-plugin"
                                                           type="checkbox" value="1" name="is_enabled"
                                                           id="courier-{{ $provider['id'] }}-status"
                                                           {{ $provider['is_enabled'] ? 'checked' : '' }}
                                                           data-modal-type="input-change-form"
                                                           data-modal-form="#courier-{{ $provider['id'] }}-status-form"
                                                           data-on-image="{{ dynamicAsset(path: 'public/assets/back-end/img/modal/payment-gateway-on.png') }}"
                                                           data-off-image="{{ dynamicAsset(path: 'public/assets/back-end/img/modal/payment-gateway-off.png') }}"
                                                           data-on-title="{{ translate('want_to_enable') }} {{ $provider['label'] }} {{ translate('delivery').'?' }}"
                                                           data-off-title="{{ translate('want_to_disable') }} {{ $provider['label'] }} {{ translate('delivery').'?' }}"
                                                           data-on-message="<p>{{ translate('once_enabled_this_delivery_partner_becomes_available_in_the_third_party_delivery_service_and_can_be_used_to_ship_orders') . '.' }}</p>"
                                                           data-off-message="<p>{{ translate('once_disabled_this_delivery_partner_is_no_longer_available_in_the_third_party_delivery_service_and_cannot_be_used_to_ship_orders') . '.' }}</p>">
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </form>
                                        @else
                                            <label class="switcher"
                                                   data-courier-offcanvas-target="#courier-offcanvas-{{ $provider['id'] }}">
                                                <input class="switcher_input" type="checkbox" value="1" name="is_enabled" disabled>
                                                <span class="switcher_control"></span>
                                            </label>
                                        @endif

                                        <button type="button" class="btn btn-outline-warning icon-btn"
                                                data-courier-offcanvas-target="#courier-offcanvas-{{ $provider['id'] }}">
                                            <i class="fi fi-sr-settings"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="w-100">
                            @include('layouts.vendor.partials._empty-state', ['text' => 'no_delivery_partner_is_registered', 'image' => 'deliveryman', 'width' => 60])
                        </div>
                    @endforelse

                    <div class="empty-state-for-courier d-none w-100">
                        @include('layouts.vendor.partials._empty-state', ['text' => 'no_delivery_partner_found', 'image' => 'deliveryman', 'width' => 60])
                    </div>
                </div>
            </div>
        </div>

        @foreach ($providers as $provider)
            @include('courier::vendor.config._setup-offcanvas', ['provider' => $provider])
        @endforeach
    </div>
@endsection

@push('script')
    <script>
        'use strict';

        function filterCourierProviders() {
            let value = $("#courier-provider-search").val().toLowerCase();
            $(".courier-provider-cards").each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
            $('.empty-state-for-courier').toggleClass('d-none', $(".courier-provider-cards:visible").length > 0);
            $('#courier-provider-search-clear').toggleClass('d-none', value === '');
        }

        $("#courier-provider-search").on("input search", filterCourierProviders);

        $('#courier-provider-search-clear').on('click', function () {
            $("#courier-provider-search").val('').focus();
            filterCourierProviders();
        });

        const hasSelect2 = () => window.jQuery && typeof window.jQuery.fn.select2 === 'function';

        let courierOpener = null;

        function closeCourierPanel(panel) {
            if (!panel || !panel.classList.contains('is-open')) return;

            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('courier-offcanvas-open');
            panel.dispatchEvent(new CustomEvent('courier:offcanvas:hidden'));

            if (courierOpener) {
                courierOpener.focus();
                courierOpener = null;
            }
        }

        function openCourierPanel(panel, trigger) {
            if (!panel) return;

            document.querySelectorAll('.courier-offcanvas.is-open').forEach(closeCourierPanel);

            courierOpener = trigger;
            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            document.body.classList.add('courier-offcanvas-open');
            panel.dispatchEvent(new CustomEvent('courier:offcanvas:shown'));

            let focusable = panel.querySelector('.courier-offcanvas-body input:not([type="hidden"]):not([readonly]), .courier-offcanvas-body select, .courier-offcanvas-body textarea');
            if (focusable) focusable.focus();
        }

        $(document).on('click', '[data-courier-offcanvas-target]', function () {
            openCourierPanel(document.querySelector($(this).data('courier-offcanvas-target')), this);
        });

        $(document).on('click', '[data-courier-offcanvas-dismiss]', function () {
            closeCourierPanel(this.closest('.courier-offcanvas'));
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || document.querySelector('.select2-container--open')) return;
            closeCourierPanel(document.querySelector('.courier-offcanvas.is-open'));
        }, true);

        $('.courier-offcanvas')
            .on('courier:offcanvas:shown', function () {
                if (!hasSelect2()) return;
                let panel = $(this).find('.courier-offcanvas-content');
                $(this).find('.courier-searchable-select').each(function () {
                    let $select = $(this);
                    if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
                    $select.select2({
                        width: '100%',
                        placeholder: $select.data('placeholder') || '',
                        dropdownParent: panel,
                        minimumResultsForSearch: $select.find('option').length > 7 ? 0 : Infinity,
                    });
                });
            })
            .on('courier:offcanvas:hidden', function () {
                if (!hasSelect2()) return;
                $(this).find('.courier-searchable-select.select2-hidden-accessible').select2('destroy');
            });

        $('[data-toggle="tooltip"]').tooltip();

        function syncCourierBaseUrl(environmentInput) {
            let target = document.querySelector($(environmentInput).data('target'));
            if (target) target.value = $(environmentInput).data('base-url') || '';
        }

        $(document).on('change', '.courier-environment-input', function () {
            syncCourierBaseUrl(this);
        });

        $(document).on('click', '.courier-config-reset', function () {
            let form = document.querySelector($(this).data('form'));
            if (!form) return;

            form.reset();
            $(form).find('.courier-searchable-select.select2-hidden-accessible').trigger('change.select2');

            let environment = form.querySelector('.courier-environment-input:checked');
            if (environment) syncCourierBaseUrl(environment);
        });

        $(document).on('click', '.courier-copy-btn', function () {
            let input = document.querySelector($(this).data('copy-target'));
            if (!input) return;
            navigator.clipboard.writeText(input.value).then(function () {
                toastMagic.success('{{ translate('copied_to_clipboard') }}');
            });
        });
    </script>
@endpush
