@php($bs = (int) ($courierBs ?? 5))
@php($radioClass = $bs === 5 ? 'form-check-input mt-0' : 'm-0')
@php($semiBoldClass = $bs === 5 ? 'fw-semibold' : 'font-weight-bold')

<div class="d-flex flex-column gap-2 mb-3" id="courier-provider-list">
    @foreach ($courierSelectableProviders as $courierProvider)
        <label class="border rounded p-3 d-flex justify-content-between align-items-center gap-2 m-0 cursor-pointer courier-provider-option"
               for="courier-provider-{{ $courierProvider['id'] }}">
            <span class="{{ $semiBoldClass }} text-capitalize">{{ $courierProvider['label'] }}</span>
            <input class="{{ $radioClass }} radio--input courier-provider-radio" type="radio"
                   name="courier_provider_choice" id="courier-provider-{{ $courierProvider['id'] }}"
                   value="{{ $courierProvider['id'] }}">
        </label>
    @endforeach
</div>
