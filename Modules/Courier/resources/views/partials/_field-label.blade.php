@php($courierLabelBs = (int) ($courierBs ?? 5))
@php($courierLabelTooltip = $courierLabelBs === 5 ? 'data-bs-toggle' : 'data-toggle')
@php($courierLabelPlacement = $courierLabelBs === 5 ? 'data-bs-placement' : 'data-placement')

<label class="form-label d-flex align-items-center gap-1 mb-2"@isset($for) for="{{ $for }}"@endisset>
    <span class="courier-field-label @isset($levelFor)courier-level-label @endisset"
          @isset($levelFor)data-level="{{ $levelFor }}"@endisset>{{ $text }}</span>
    @if (!empty($required))<span class="text-danger">*</span>@endif
    @isset($requiredFor)<span class="text-danger courier-required-mark d-none" data-required-for="{{ $requiredFor }}">*</span>@endisset
    @if (!empty($help))
        
        <i class="fi fi-sr-info fs-12 text-muted d-flex" {{ $courierLabelTooltip }}="tooltip"
           {{ $courierLabelPlacement }}="top" title="{{ $help }}"></i>
    @endif
</label>
