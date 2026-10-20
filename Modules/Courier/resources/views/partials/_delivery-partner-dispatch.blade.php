@php($bs = (int) ($courierBs ?? 5))
@php($courierProviderOptions = array_map(
    static fn (array $provider): array => ['level_labels' => array_map('translate', $provider['level_labels'] ?? [])] + $provider,
    $courierProviders ?? [],
))

@if ($courierDispatchAvailable && !empty($courierProviders))
    @if ($bs === 5)
        <div class="offcanvas offcanvas-end" tabindex="-1" id="sendToCourierOffcanvas"
             aria-labelledby="sendToCourierOffcanvasLabel" style="--bs-offcanvas-width: 620px;">
            <form action="{{ route($courierRoutes['dispatch']) }}" method="POST" class="d-flex flex-column h-100">
                @csrf
                <input type="hidden" name="replace_existing" id="courier_replace_existing" value="0">
                <div class="offcanvas-header bg-section border-bottom">
                    <h5 class="offcanvas-title fw-bold" id="sendToCourierOffcanvasLabel">
                        <span id="courier-offcanvas-heading">{{ translate('Required_Information') }}</span> - <span id="courier-offcanvas-provider-label" class="text-capitalize"></span>
                    </h5>
                    <button type="button" class="btn btn-circle border-0 fs-12 text-dark bg-section2 shadow-none"
                            data-bs-dismiss="offcanvas" aria-label="Close">
                        <i class="fi fi-rr-cross d-flex"></i>
                    </button>
                </div>
                <div class="offcanvas-body">
                    @include('courier::partials._delivery-partner-fields')
                </div>
                <div class="offcanvas-footer shadow-popup bg-white p-3 d-flex gap-3">
                    <button type="button" class="btn btn-secondary flex-fill courier-dispatch-reset">{{ translate('Reset') }}</button>
                    <button type="submit" class="btn btn-primary flex-fill courier-dispatch-save" disabled>{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    @else
        <div class="offcanvas-sidebar" id="sendToCourierOffcanvas" style="--bs-offcanvas-width: 620px;">
            <div class="offcanvas-overlay" data-dismiss="offcanvas"></div>
            <div class="offcanvas-content bg-white shadow d-flex flex-column">
                <form action="{{ route($courierRoutes['dispatch']) }}" method="POST" class="d-flex flex-column h-100">
                    @csrf
                    <input type="hidden" name="replace_existing" id="courier_replace_existing" value="0">
                    <div class="offcanvas-header bg-light border-bottom d-flex justify-content-between align-items-center p-3">
                        <h5 class="mb-0 font-weight-bold">
                            <span id="courier-offcanvas-heading">{{ translate('Required_Information') }}</span> - <span id="courier-offcanvas-provider-label" class="text-capitalize"></span>
                        </h5>
                        <button type="button" class="close" data-dismiss="offcanvas" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="offcanvas-body p-3 overflow-auto flex-grow-1">
                        @include('courier::partials._delivery-partner-fields')
                    </div>
                    <div class="offcanvas-footer offcanvas-footer-sticky p-3 border-top bg-white d-flex gap-3">
                        <button type="button" class="btn btn-secondary flex-fill courier-dispatch-reset">{{ translate('Reset') }}</button>
                        <button type="submit" class="btn btn-primary flex-fill courier-dispatch-save" disabled>{{ translate('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('courier::partials._delivery-partner-confirm-modals')

<script>
    (function () {
        const providers = @json($courierProviderOptions);
        const offcanvasEl = document.getElementById('sendToCourierOffcanvas');
        const openBtn = document.getElementById('courier-open-offcanvas');
        const reviseBtn = document.getElementById('courier-open-revise');
        if (!offcanvasEl) return;

        const activeProviderId = @json($courierActiveProvider);
        const activeProviderLabel = @json($courierShipment['delivery_partner'] ?? null);
        const storedDetails = @json((object) $courierDispatchDetails);
        const dispatchUrl = @json(route($courierRoutes['dispatch']));
        const reviseUrl = @json(route($courierRoutes['revise']));
        const replaceInput = document.getElementById('courier_replace_existing');

        const providerInput = document.getElementById('courier_provider');
        const providerLabel = document.getElementById('courier-offcanvas-provider-label');
        const store = document.getElementById('courier_store');
        const storeCol = store ? store.closest('div') : null;
        const city = document.getElementById('courier_city');
        const zone = document.getElementById('courier_zone');
        const area = document.getElementById('courier_area');
        const locationRow = document.getElementById('courier-location-row');
        const internationalAddress = document.getElementById('courier-international-address');
        const geoAddress = document.getElementById('courier-geo-address');
        const branchAddress = document.getElementById('courier-branch-address');
        const country = document.getElementById('courier_country');
        const postalCode = document.getElementById('courier_postal_code');
        const cityName = document.getElementById('courier_city_name');
        const latitude = document.getElementById('courier_latitude');
        const longitude = document.getElementById('courier_longitude');
        const sourceBranch = document.getElementById('courier_source_branch');
        const destinationBranch = document.getElementById('courier_destination_branch');
        const sourceBranchId = document.getElementById('courier_source_branch_id');
        const destinationBranchId = document.getElementById('courier_destination_branch_id');
        const columns = document.querySelectorAll('.courier-loc-col');
        const requiredMarks = document.querySelectorAll('.courier-required-mark');
        const levelLabels = document.querySelectorAll('.courier-level-label');
        const defaultLevelLabels = {};
        levelLabels.forEach(function (el) { defaultLevelLabels[el.dataset.level] = el.textContent; });
        const deliveryTypeCol = document.getElementById('courier-delivery-type-col');
        const deliveryType = document.getElementById('courier_delivery_type');
        const placeholder = @json(translate('Select'));
        const loadingText = @json(translate('Loading...'));
        const form = offcanvasEl.querySelector('form');
        const submitBtn = form ? form.querySelector('.courier-dispatch-save') : null;
        const resetBtn = form ? form.querySelector('.courier-dispatch-reset') : null;
        const recipientName = document.getElementById('courier_recipient_name');
        const recipientPhone = document.getElementById('courier_recipient_phone');
        const recipientAddress = document.getElementById('courier_recipient_address');
        const weight = document.getElementById('courier_weight');
        const cod = document.getElementById('courier_cod');
        const requiredFieldTemplate = @json(__('validation.required', ['attribute' => ':field']));
        const requiredFieldsText = @json(translate('Please_fill_all_the_required_fields'));
        const selectPartnerText = @json(translate('Please_select_a_delivery_partner_first'));
        const lookupFailedText = @json(translate('Could_not_load_options_from_the_delivery_partner'));
        const lookupUnavailableText = @json(translate('The_delivery_partner_did_not_return_any_options'));
        const lookupNotice = document.getElementById('courier-lookup-notice');
        const dispatchHeading = @json(translate('Required_Information'));
        const reviseHeading = @json(translate('Update_Delivery_Information'));
        const headingEl = document.getElementById('courier-offcanvas-heading');
        let lastLoadIssue = null;
        let requiredFields = [];
        let addressMode = 'catalog';
        let appliedProviderId = null;
        let intent = 'dispatch';
        let pending = {};
        let confirmedSwitchProviderId = null;

        const CATALOG_KEYS = ['store_id', 'city_id', 'zone_id', 'area_id', 'source_branch', 'destination_branch', 'delivery_type'];

        function providerById(id) {
            return providers.find(p => p.id === id) || null;
        }

        function selectedProvider() {
            const checked = document.querySelector('.courier-provider-radio:checked');
            return checked ? providerById(checked.value) : null;
        }

        function applyIntent(next) {
            intent = next;
            if (form) form.action = next === 'revise' ? reviseUrl : dispatchUrl;
            if (replaceInput) replaceInput.value = next === 'switch' ? '1' : '0';
            if (headingEl) headingEl.textContent = next === 'revise' ? reviseHeading : dispatchHeading;
        }

        function setValue(el, value) {
            if (!el || value === null || value === undefined || value === '') return;
            el.value = value;
        }

        function prefill(keepCatalog) {
            pending = {};
            Object.keys(storedDetails || {}).forEach(function (key) {
                const value = storedDetails[key];
                if (value === null || value === undefined || value === '') return;
                if (CATALOG_KEYS.indexOf(key) !== -1) {
                    if (keepCatalog) pending[key] = String(value);
                    return;
                }
                setValue(form.querySelector('[name="' + key + '"]'), value);
            });
        }

        function applyPending(select, key) {
            const value = pending[key];
            if (!value || !select) return;
            pending[key] = null;
            select.value = value;
            if (select.value !== value) return;
            if (window.jQuery) { window.jQuery(select).trigger('change'); }
            else { select.dispatchEvent(new Event('change')); }
            syncSaveState();
        }

        function applyRequiredFields(provider) {
            requiredFields = (provider && provider.required_fields) || [];
            requiredMarks.forEach(function (mark) {
                mark.classList.toggle('d-none', requiredFields.indexOf(mark.dataset.requiredFor) === -1);
            });
        }

        function isRequired(field) {
            return requiredFields.indexOf(field) !== -1;
        }

        const hasSelect2 = () => window.jQuery && typeof window.jQuery.fn.select2 === 'function';

        function enhanceSelect(el) {
            if (!hasSelect2() || !el) return;
            const $e = window.jQuery(el);

            if ($e.hasClass('select2-hidden-accessible')) {
                $e.trigger('change.select2');
                return;
            }

            const parent = $e.closest('.offcanvas-content, .offcanvas');
            $e.select2({
                width: '100%',
                allowClear: false,
                placeholder: el.dataset.placeholder || placeholder,
                minimumResultsForSearch: 7,
                dropdownParent: parent.length ? parent : undefined,
            });
        }

        function setDisabled(select, state) {
            if (!select) return;
            state ? select.setAttribute('disabled', 'disabled') : select.removeAttribute('disabled');
        }

        function markLoading(select) {
            if (!select) return;
            select.innerHTML = '<option value="">' + loadingText + '</option>';
            setDisabled(select, true);
            enhanceSelect(select);
        }

        function reset(select) {
            select.innerHTML = '<option value="">' + placeholder + '</option>';
            enhanceSelect(select);
            syncSaveState();
        }

        function fill(select, options) {
            select.innerHTML = '<option value="">' + placeholder + '</option>';
            (options || []).forEach(function (o) {
                const el = document.createElement('option');
                el.value = o.id; el.textContent = o.name;
                select.appendChild(el);
            });
            enhanceSelect(select);
            syncSaveState();
        }

        function withProvider(url) {
            const sep = url.indexOf('?') === -1 ? '?' : '&';
            return url + sep + 'provider=' + encodeURIComponent(providerInput.value);
        }

        function reportLoadIssue(payload) {
            const reason = payload && payload.message ? payload.message : null;
            const text = reason ? lookupFailedText + ': ' + reason : lookupUnavailableText;

            if (lastLoadIssue === text) return;
            lastLoadIssue = text;

            if (window.toastMagic && typeof window.toastMagic.error === 'function') {
                window.toastMagic.error(text);
            }
            if (lookupNotice) {
                lookupNotice.textContent = text;
                lookupNotice.classList.remove('d-none');
            }
        }

        function clearLoadIssue() {
            lastLoadIssue = null;
            if (lookupNotice) {
                lookupNotice.textContent = '';
                lookupNotice.classList.add('d-none');
            }
        }

        function load(url, onDone, opts) {
            const settings = opts || {};
            const targets = settings.targets || (settings.target ? [settings.target] : []);

            targets.forEach(markLoading);

            const finish = function (options) {
                targets.forEach(function (target) { setDisabled(target, false); });
                onDone(options);
            };

            fetch(withProvider(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(function (d) {
                    const options = (d && d.options) || [];
                    const unsupported = d && d.supported === false;

                    if (unsupported && settings.onUnsupported) settings.onUnsupported();
                    else if (!options.length && d && (d.message || !settings.optional)) reportLoadIssue(d);

                    finish(options);
                })
                .catch(function () {
                    reportLoadIssue(null);
                    finish([]);
                });
        }

        function loadStores() {
            if (!store) return;
            if (storeCol) storeCol.classList.remove('d-none');

            load(store.dataset.storesUrl, function (opts) {
                fill(store, opts);
                applyPending(store, 'store_id');
            }, {
                target: store,
                optional: true,
                onUnsupported: function () {
                    store.value = '';
                    if (storeCol) storeCol.classList.add('d-none');
                },
            });
        }

        function fillBranches(select, hidden, options) {
            select.innerHTML = '<option value="">' + placeholder + '</option>';
            (options || []).forEach(function (o) {
                const el = document.createElement('option');
                el.value = o.name; el.textContent = o.name;
                el.dataset.branchId = o.id;
                select.appendChild(el);
            });
            if (hidden) hidden.value = '';
            enhanceSelect(select);
            onChange(select, function () {
                const opt = select.options[select.selectedIndex];
                if (hidden) hidden.value = opt ? (opt.dataset.branchId || '') : '';
            });
            syncSaveState();
        }

        function loadBranches() {
            if (!sourceBranch || !destinationBranch) return;
            load(sourceBranch.dataset.branchesUrl, function (opts) {
                fillBranches(sourceBranch, sourceBranchId, opts);
                fillBranches(destinationBranch, destinationBranchId, opts);
                applyPending(sourceBranch, 'source_branch');
                applyPending(destinationBranch, 'destination_branch');
            }, {targets: [sourceBranch, destinationBranch]});
        }

        function applyDeliveryTypes(provider) {
            const types = provider.delivery_types || [];
            if (types.length) {
                deliveryType.innerHTML = '';
                types.forEach(function (t) {
                    const el = document.createElement('option');
                    el.value = t.id; el.textContent = t.label;
                    deliveryType.appendChild(el);
                });
                deliveryTypeCol.classList.remove('d-none');
                setDisabled(deliveryType, false);
                enhanceSelect(deliveryType);
                applyPending(deliveryType, 'delivery_type');
            } else {
                deliveryType.innerHTML = '';
                deliveryTypeCol.classList.add('d-none');
                setDisabled(deliveryType, true);
                enhanceSelect(deliveryType);
            }
        }

        function applyProvider(chosen) {
            const provider = chosen || selectedProvider();
            if (!provider) return;

            providerInput.value = provider.id;
            appliedProviderId = provider.id;
            if (providerLabel) providerLabel.textContent = provider.label;

            addressMode = provider.address_mode || 'catalog';
            clearLoadIssue();
            applyRequiredFields(provider);
            const catalog = addressMode === 'catalog';
            if (internationalAddress) internationalAddress.classList.toggle('d-none', addressMode !== 'postal');
            if (geoAddress) geoAddress.classList.toggle('d-none', addressMode !== 'geo');
            if (branchAddress) branchAddress.classList.toggle('d-none', addressMode !== 'branch');
            if (locationRow) locationRow.classList.toggle('d-none', !catalog);
            if (storeCol) storeCol.classList.toggle('d-none', !catalog);

            if (!catalog) {
                applyDeliveryTypes(provider);
                if (addressMode === 'postal') enhanceSelect(country);
                if (addressMode === 'branch') loadBranches();
                setEstimateIdle();
                syncSaveState();
                return;
            }

            loadStores();

            const levels = (provider.levels && provider.levels.length) ? provider.levels : ['city', 'zone', 'area'];
            columns.forEach(col => { col.style.display = levels.indexOf(col.dataset.level) === -1 ? 'none' : ''; });
            const overriddenLabels = provider.level_labels || {};
            levelLabels.forEach(function (el) {
                el.textContent = overriddenLabels[el.dataset.level] || defaultLevelLabels[el.dataset.level];
            });

            applyDeliveryTypes(provider);

            reset(city); reset(zone); reset(area);
            [city, zone, area].forEach(function (el) {
                if (el.closest('.courier-loc-col').style.display !== 'none') enhanceSelect(el);
            });
            if (levels.indexOf('city') !== -1) {
                load(city.dataset.citiesUrl, function (opts) { fill(city, opts); applyPending(city, 'city_id'); }, {target: city});
            } else if (levels.indexOf('area') !== -1) {
                load(zone.dataset.areasUrl.replace('__ID__', 'all'), function (opts) { fill(area, opts); applyPending(area, 'area_id'); }, {target: area});
            }
            setEstimateIdle();
            syncSaveState();
        }

        function showOffcanvas() {
            if (window.bootstrap && window.bootstrap.Offcanvas && typeof window.bootstrap.Offcanvas.getOrCreateInstance === 'function') {
                window.bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
            } else {
                offcanvasEl.classList.add('active');
                if (typeof window.updateBodyScrollState === 'function') window.updateBodyScrollState();
            }
        }

        function toggleModal(id, show) {
            const el = document.getElementById(id);
            if (!el) return;

            if (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                const instance = window.bootstrap.Modal.getOrCreateInstance(el);
                show ? instance.show() : instance.hide();
            } else if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery(el).modal(show ? 'show' : 'hide');
            }
        }

        function openDispatchForm(provider, nextIntent) {
            if (!provider) {
                if (window.toastMagic && typeof window.toastMagic.warning === 'function') {
                    window.toastMagic.warning(selectPartnerText);
                }
                return;
            }

            applyIntent(nextIntent);
            prefill(nextIntent === 'revise');
            applyProvider(provider);
            showOffcanvas();
        }

        function requestSwitch(provider) {
            const switchModal = document.getElementById('courierSwitchModal');
            if (switchModal && provider) {
                switchModal.querySelectorAll('.courier-switch-current')
                    .forEach(el => { el.textContent = activeProviderLabel || ''; });
                switchModal.querySelectorAll('.courier-switch-next')
                    .forEach(el => { el.textContent = provider.label; });
            }
            toggleModal('courierSwitchModal', true);
        }

        function openOffcanvas() {
            const provider = selectedProvider();

            if (activeProviderId && provider && provider.id !== confirmedSwitchProviderId) {
                requestSwitch(provider);
                return;
            }

            if (provider && provider.id === appliedProviderId && intent !== 'revise') {
                showOffcanvas();
                return;
            }

            openDispatchForm(provider, activeProviderId ? 'switch' : 'dispatch');
        }

        document.querySelectorAll('.courier-provider-radio').forEach(function (radio) {
            radio.addEventListener('click', function () {
                document.querySelectorAll('.courier-provider-option').forEach(o => o.classList.remove('border-primary'));
                radio.closest('.courier-provider-option').classList.add('border-primary');
                openOffcanvas();
            });
        });

        const switchConfirmBtn = document.getElementById('courier-switch-confirm');
        if (switchConfirmBtn) {
            switchConfirmBtn.addEventListener('click', function () {
                const provider = selectedProvider();
                confirmedSwitchProviderId = provider ? provider.id : null;
                toggleModal('courierSwitchModal', false);
                openDispatchForm(provider, 'switch');
            });
        }

        if (reviseBtn) {
            reviseBtn.addEventListener('click', function () {
                openDispatchForm(providerById(activeProviderId), 'revise');
            });
        }

        if (openBtn) openBtn.addEventListener('click', openOffcanvas);

        function fieldLabel(el) {
            const label = el && el.id
                ? document.querySelector('label[for="' + el.id + '"] .courier-field-label')
                : null;

            return label ? label.textContent.trim() : '';
        }

        function requiredMessage(el) {
            const label = fieldLabel(el);

            return label ? requiredFieldTemplate.replace(':field', label) : requiredFieldsText;
        }

        function firstEmpty(elements) {
            const el = elements.find(candidate => candidate && !candidate.value);

            return el ? {el: el, message: requiredMessage(el)} : null;
        }

        function isLevelVisible(el) {
            const column = el.closest('.courier-loc-col');

            return !column || column.style.display !== 'none';
        }

        function missingRequirement() {
            const base = firstEmpty([recipientName, recipientPhone, recipientAddress, cod, weight]);
            if (base) return base;

            if (addressMode === 'postal') return firstEmpty([country, postalCode, cityName]);
            if (addressMode === 'geo') return firstEmpty([latitude, longitude]);
            if (addressMode === 'branch') return firstEmpty([sourceBranch, destinationBranch]);

            const missingLevel = [
                {field: 'city_id', el: city},
                {field: 'zone_id', el: zone},
                {field: 'area_id', el: area},
            ].find(entry => isRequired(entry.field) && entry.el && !entry.el.value && isLevelVisible(entry.el));

            return missingLevel ? {el: missingLevel.el, message: requiredMessage(missingLevel.el)} : null;
        }

        function syncSaveState() {
            if (submitBtn) submitBtn.disabled = missingRequirement() !== null;
        }

        function bindSelect2SaveState() {
            window.jQuery(form).on('change', syncSaveState);
        }

        function focusMissing(el) {
            if (hasSelect2() && window.jQuery(el).hasClass('select2-hidden-accessible')) {
                window.jQuery(el).select2('open');
            } else {
                el.focus();
            }
        }

        const NUMERIC_SIGN = {phone: '+', 'signed-decimal': '-'};

        function acceptsDecimalPoint(mode) {
            return mode === 'decimal' || mode === 'signed-decimal';
        }

        function sanitizeNumericValue(value, mode) {
            const sign = NUMERIC_SIGN[mode];
            const prefix = (sign && value.charAt(0) === sign) ? sign : '';
            let digits = value.replace(acceptsDecimalPoint(mode) ? /[^0-9.]/g : /[^0-9]/g, '');

            if (acceptsDecimalPoint(mode)) {
                const parts = digits.split('.');
                if (parts.length > 2) digits = parts.shift() + '.' + parts.join('');
            }

            return prefix + digits;
        }

        function acceptsNumericKey(event, mode) {
            if (event.ctrlKey || event.metaKey || event.altKey || event.key.length > 1) return true;
            if (/[0-9]/.test(event.key)) return true;

            const field = event.target;
            if (event.key === '.' && acceptsDecimalPoint(mode)) return field.value.indexOf('.') === -1;
            if (event.key === NUMERIC_SIGN[mode]) {
                return field.value.indexOf(event.key) === -1 && field.selectionStart === 0;
            }

            return false;
        }

        // A number input reports an empty value once the browser rejects what was
        // typed, so letters have to be stopped at the key rather than cleaned after.
        function bindNumericFields() {
            form.querySelectorAll('[data-numeric-field]').forEach(function (field) {
                const mode = field.dataset.numericField;

                field.addEventListener('keydown', function (event) {
                    if (!acceptsNumericKey(event, mode)) event.preventDefault();
                });

                field.addEventListener('input', function () {
                    const cleaned = sanitizeNumericValue(field.value, mode);
                    if (cleaned !== field.value) field.value = cleaned;
                });
            });
        }

        function confirmModalId() {
            return intent === 'revise' ? 'courierReviseConfirmModal' : 'courierDispatchConfirmModal';
        }

        function requestSubmitConfirmation() {
            const provider = providerById(providerInput.value);
            const modal = document.getElementById(confirmModalId());

            if (modal && provider) {
                modal.querySelectorAll('.courier-confirm-partner')
                    .forEach(el => { el.textContent = provider.label; });
            }
            toggleModal(confirmModalId(), true);
        }

        if (form) {
            bindNumericFields();

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const missing = missingRequirement();
                if (missing) {
                    if (window.toastMagic && typeof window.toastMagic.error === 'function') {
                        window.toastMagic.error(missing.message);
                    }
                    focusMissing(missing.el);
                    return;
                }

                requestSubmitConfirmation();
            });

            ['courier-dispatch-confirm', 'courier-revise-confirm'].forEach(function (id) {
                const btn = document.getElementById(id);
                if (!btn) return;
                btn.addEventListener('click', function () {
                    toggleModal(confirmModalId(), false);
                    form.submit();
                });
            });

            form.addEventListener('input', syncSaveState);
            form.addEventListener('change', syncSaveState);

            if (window.jQuery) { bindSelect2SaveState(); } else { window.addEventListener('load', bindSelect2SaveState); }
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                const provider = providerById(providerInput.value);
                form.reset();
                applyIntent(intent);
                prefill(intent === 'revise');
                applyProvider(provider);
                if (hasSelect2()) {
                    window.jQuery(form).find('.select2-hidden-accessible').trigger('change.select2');
                }
                syncSaveState();
            });
        }

        function onChange(el, handler) {
            if (window.jQuery) { window.jQuery(el).on('change', handler); }
            else { el.addEventListener('change', handler); }
        }

        function closeOpenDropdowns() {
            window.jQuery(offcanvasEl).find('.select2-hidden-accessible').each(function () {
                const instance = window.jQuery(this).data('select2');
                if (instance && instance.isOpen()) window.jQuery(this).select2('close');
            });
        }

        function bindDropdownDismissal() {
            if (!hasSelect2()) return;

            window.jQuery(offcanvasEl).on('wheel touchmove', function (event) {
                if (window.jQuery(event.target).closest('.select2-dropdown').length) return;
                closeOpenDropdowns();
            });
        }
        if (window.jQuery) { bindDropdownDismissal(); } else { window.addEventListener('load', bindDropdownDismissal); }

        // The dropdown is appended to the offcanvas, outside the body that
        // scrolls, and select2 only repositions itself on window scroll. So
        // anything that moves the field under an open list — the lookup alert
        // appearing above it, a provider showing or hiding field groups, a
        // catalog list arriving over AJAX, a scrollbar drag that fires no
        // wheel event — leaves the list behind at its old coordinates.
        function repositionOpenDropdowns() {
            if (!hasSelect2()) return;

            window.jQuery(offcanvasEl).find('.select2-hidden-accessible').each(function () {
                const instance = window.jQuery(this).data('select2');
                if (instance && instance.isOpen() && instance.dropdown) {
                    instance.dropdown._positionDropdown();
                    instance.dropdown._resizeDropdown();
                }
            });
        }

        function bindDropdownRepositioning() {
            if (!hasSelect2()) return;

            const body = offcanvasEl.querySelector('.offcanvas-body');
            if (body) body.addEventListener('scroll', repositionOpenDropdowns);

            // The body's own box never changes size — the panel inside it is
            // what grows and shrinks, so that is what has to be observed.
            const panel = offcanvasEl.querySelector('#courier-fields-panel');
            if (panel && typeof ResizeObserver === 'function') {
                new ResizeObserver(repositionOpenDropdowns).observe(panel);
            }
        }
        if (window.jQuery) { bindDropdownRepositioning(); } else { window.addEventListener('load', bindDropdownRepositioning); }

        function bindCascade() {
            onChange(city, function () {
                reset(zone); reset(area);
                if (!city.value) return;
                load(city.dataset.zonesUrl.replace('__ID__', encodeURIComponent(city.value)), function (opts) {
                    fill(zone, opts);
                    applyPending(zone, 'zone_id');
                }, {target: zone});
            });
            onChange(zone, function () {
                reset(area);
                if (!zone.value) return;
                load(zone.dataset.areasUrl.replace('__ID__', encodeURIComponent(zone.value)), function (opts) {
                    fill(area, opts);
                    applyPending(area, 'area_id');
                }, {target: area});
            });
        }
        if (window.jQuery) { bindCascade(); } else { window.addEventListener('load', bindCascade); }

        window.addEventListener('load', function () {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                offcanvasEl.querySelectorAll('[data-bs-toggle="tooltip"]')
                    .forEach(el => window.bootstrap.Tooltip.getOrCreateInstance(el));
            } else if (window.jQuery && typeof window.jQuery.fn.tooltip === 'function') {
                window.jQuery(offcanvasEl).find('[data-toggle="tooltip"]').tooltip();
            }
        });

        const estimateBtn = document.getElementById('courier-estimate-btn');
        const estimateResult = document.getElementById('courier-estimate-result');
        const idleText = @json(translate('Fill_weight_and_location,_then_estimate'));
        const notSupportedText = @json(translate('Charge_estimate_is_not_available_for_this_provider'));
        const calcText = @json(translate('Calculating...'));

        function setEstimateIdle() { if (estimateResult) estimateResult.textContent = idleText; }

        function estimateBlockedMessage() {
            const missing = firstEmpty([weight])
                || (addressMode === 'geo' ? firstEmpty([recipientAddress, latitude, longitude]) : null)
                || (addressMode === 'postal' ? firstEmpty([country, postalCode, cityName]) : null)
                || (addressMode === 'branch' ? firstEmpty([sourceBranch, destinationBranch]) : null);

            return missing ? missing.message : '';
        }

        if (estimateBtn) {
            estimateBtn.addEventListener('click', function () {
                const blocked = estimateBlockedMessage();
                if (blocked) {
                    estimateResult.textContent = blocked;
                    if (window.toastMagic && typeof window.toastMagic.error === 'function') {
                        window.toastMagic.error(blocked);
                    }
                    return;
                }
                estimateBtn.disabled = true;
                estimateResult.textContent = calcText;
                const body = new URLSearchParams({
                    provider: providerInput.value,
                    store_id: store ? store.value : '',
                    weight: weight.value || '0',
                    cod_amount: cod.value || '0',
                    city_id: city.value || '',
                    zone_id: zone.value || '',
                    area_id: area.value || '',
                    delivery_type: (deliveryType && !deliveryType.disabled) ? deliveryType.value : '',
                    country_code: country ? country.value : '',
                    postal_code: postalCode ? postalCode.value : '',
                    city_name: cityName ? cityName.value : '',
                    latitude: latitude ? latitude.value : '',
                    longitude: longitude ? longitude.value : '',
                    source_branch: sourceBranch ? sourceBranch.value : '',
                    source_branch_id: sourceBranchId ? sourceBranchId.value : '',
                    destination_branch: destinationBranch ? destinationBranch.value : '',
                    destination_branch_id: destinationBranchId ? destinationBranchId.value : '',
                    recipient_address: recipientAddress ? recipientAddress.value : '',
                });
                fetch(estimateBtn.dataset.estimateUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body.toString(),
                }).then(r => r.json()).then(function (data) {
                    estimateBtn.disabled = false;
                    if (!data.ok) { estimateResult.textContent = data.message || notSupportedText; return; }
                    if (!data.supported) { estimateResult.textContent = notSupportedText; return; }
                    estimateResult.textContent = (data.total ?? 0) + ' ' + (data.currency || '');
                }).catch(function () {
                    estimateBtn.disabled = false;
                    estimateResult.textContent = notSupportedText;
                });
            });
        }

        syncSaveState();
    })();

</script>
@endif
