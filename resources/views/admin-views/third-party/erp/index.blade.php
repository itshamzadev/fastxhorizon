@extends('layouts.admin.app')

@section('title', translate('ERP_Integration'))

@push('css_or_js')
    <style>
        .icon-btn.custom-modal-plugin i {
            pointer-events: none;
        }
        .erp-step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            background-color: var(--bs-primary);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            line-height: 1;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                {{ translate('3rd_Party') }} - {{ translate('Other_Configurations') }}
            </h2>
        </div>
        @include('admin-views.third-party._third-party-others-menu')

        <div class="card mb-3">
            <div class="card-header px-20 py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="text-capitalize">{{ translate('Generate_Token') }}</h2>
                    <p class="mb-0 fs-12">
                        {{ translate('generate_an_API_token_to_connect_this_product_with_your_ERP._the_webhook_URL_is_required_for_receiving_real-time_updates_from_ERP') }}.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="javascript:" class="erp-copy fs-12 fw-semibold d-flex align-items-center gap-1"
                       data-id="#erp-base-url-global">
                        <i class="fi fi-rr-link-alt"></i>
                        {{ translate('Copy_Base_URL') }}
                    </a>
                    <a href="javascript:" class="fs-12 fw-semibold d-flex align-items-center gap-1"
                       data-bs-toggle="modal" data-bs-target="#erpSetupGuideModal">
                        <i class="fi fi-rr-info"></i>
                        {{ translate('Setup_Guide') }}
                    </a>
                </div>
                <span class="d-none" id="erp-base-url-global">{{ url('/') }}</span>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.third-party.erp.store') }}" method="post">
                    @csrf
                    <div class="p-12 p-sm-20 bg-section rounded-8">
                        <div class="row g-3 align-items-start">
                            <div class="col-lg-5">
                                <div class="form-group mb-0">
                                    <label class="form-label" for="erp-token-name">
                                        {{ translate('Token_Name') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="erp-token-name" name="name"
                                           value="{{ old('name') }}"
                                           placeholder="{{ translate('e.g._ERP_Production') }}" required>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="form-group mb-0">
                                    <label class="form-label" for="erp-webhook-url">
                                        {{ translate('Webhook_Url') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="url" class="form-control" id="erp-webhook-url" name="webhook_url"
                                           value="{{ old('webhook_url') }}"
                                           placeholder="https://erp.example.com/api/v1/integration/webhook" required>
                                    <p class="fs-12 mb-0 mt-2">
                                        {{ translate('get_this_URL_from_your_ERP_panel_—_settings_→_integration_→_copy_webhook_URL') }}.
                                    </p>
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label d-none d-lg-block">&nbsp;</label>
                                <button type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}"
                                        class="btn btn-primary w-100 {{ env('APP_MODE') != 'demo' ? '' : 'call-demo-alert' }}">
                                    {{ translate('Generate_Token') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                @if(session()->has('new_credentials'))
                    @php($newCredentials = session('new_credentials'))
                    <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded-8 p-12 p-sm-20 mt-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fi fi-sr-check-circle text-success fs-18"></i>
                            <h4 class="mb-0 text-success">{{ translate('Token_created_successfully') }}!</h4>
                        </div>
                        <p class="fs-12 mb-3">
                            {{ translate('copy_the_API_key_and_API_secret_now_—_the_secret_cannot_be_viewed_again_after_you_leave_this_page') }}.
                        </p>

                        <div class="form-group">
                            <label class="form-label text-uppercase">{{ translate('API_Key') }}</label>
                            <div class="form-control d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-white">
                                <span class="form-ellipsis d-flex" id="erp-api-key"
                                      data-value="{{ $newCredentials['api_key'] }}">{{ str_repeat('•', strlen($newCredentials['api_key'])) }}</span>
                                <div class="d-flex align-items-center gap-3">
                                    <a href="javascript:" class="erp-value-toggle d-flex align-items-center"
                                       data-target="#erp-api-key">
                                        <i class="fi fi-rr-eye-crossed"></i>
                                    </a>
                                    <a href="javascript:" class="erp-copy d-flex align-items-center gap-1 fs-12 fw-semibold"
                                       data-id="#erp-api-key">
                                        <i class="fi fi-rr-duplicate"></i>
                                        {{ translate('copy') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label text-uppercase">{{ translate('API_Secret') }}</label>
                            <div class="form-control d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-white">
                                <span class="form-ellipsis d-flex" id="erp-api-secret"
                                      data-value="{{ $newCredentials['api_secret'] }}">{{ str_repeat('•', strlen($newCredentials['api_secret'])) }}</span>
                                <div class="d-flex align-items-center gap-3">
                                    <a href="javascript:" class="erp-value-toggle d-flex align-items-center"
                                       data-target="#erp-api-secret">
                                        <i class="fi fi-rr-eye-crossed"></i>
                                    </a>
                                    <a href="javascript:" class="erp-copy d-flex align-items-center gap-1 fs-12 fw-semibold"
                                       data-id="#erp-api-secret">
                                        <i class="fi fi-rr-duplicate"></i>
                                        {{ translate('copy') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-2">
                            <label class="form-label text-uppercase">{{ translate('Base_URL') }}</label>
                            <div class="form-control d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-white">
                                <span class="form-ellipsis d-flex" id="erp-base-url">{{ url('/') }}</span>
                                <a href="javascript:" class="erp-copy d-flex align-items-center gap-1 fs-12 fw-semibold"
                                   data-id="#erp-base-url">
                                    <i class="fi fi-rr-duplicate"></i>
                                    {{ translate('copy') }}
                                </a>
                            </div>
                        </div>
                        <p class="fs-12 mb-0">
                            {{ translate('use_this_URL_as_the_base_URL_when_adding_a_connection_in_your_ERP_panel') }}.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header px-20 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="text-capitalize">{{ translate('Api_Tokens') }}</h2>
                <span class="badge badge-soft-primary">{{ $tokens->count() }} {{ translate('Total') }}</span>
            </div>
            <div class="card-body">
                @if($tokens->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless table-thead-bordered table-nowrap align-middle card-table w-100">
                            <thead class="thead-light thead-50 text-capitalize">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th>{{ translate('name') }}</th>
                                    <th>{{ translate('Webhook_Url') }}</th>
                                    <th class="text-center">{{ translate('status') }}</th>
                                    <th>{{ translate('Last_Used') }}</th>
                                    <th>{{ translate('Created_At') }}</th>
                                    <th class="text-center">{{ translate('action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tokens as $key => $token)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td class="fw-semibold">{{ $token->name }}</td>
                                        <td>
                                            <span class="form-ellipsis d-inline-block max-w-200" title="{{ $token->webhook_url }}">{{ $token->webhook_url }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if($token->is_active)
                                                <span class="badge badge-soft-success">{{ translate('Active') }}</span>
                                            @else
                                                <span class="badge badge-soft-danger">{{ translate('Revoked') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $token->last_used_at ? $token->last_used_at->format('M d, Y H:i') : translate('Never') }}</td>
                                        <td>{{ $token->created_at->format('M d, Y H:i') }}</td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                @if($token->is_active)
                                                    <button type="button"
                                                            class="btn btn-outline-warning icon-btn custom-modal-plugin"
                                                            title="{{ translate('revoke') }}"
                                                            data-modal-type="input-change-form"
                                                            data-modal-form="#erp-revoke-form-{{ $token->id }}"
                                                            data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/config-status-change.png') }}"
                                                            data-off-title="{{ translate('want_to_revoke_this_token').'?' }}"
                                                            data-off-message="<p>{{ translate('the_ERP_connection_using_this_token_will_stop_working_immediately') }}</p>"
                                                            data-off-button-text="{{ translate('revoke') }}">
                                                        <i class="fi fi-rr-ban"></i>
                                                    </button>
                                                @endif
                                                <button type="button"
                                                        class="btn btn-outline-danger icon-btn custom-modal-plugin"
                                                        title="{{ translate('delete') }}"
                                                        data-modal-type="input-change-form"
                                                        data-modal-form="#erp-delete-form-{{ $token->id }}"
                                                        data-off-image="{{ dynamicAsset(path: 'public/assets/new/back-end/img/modal/delete.png') }}"
                                                        data-off-title="{{ translate('want_to_delete_this_token').'?' }}"
                                                        data-off-message="<p>{{ translate('this_token_will_be_permanently_removed_and_cannot_be_recovered') }}</p>"
                                                        data-off-button-text="{{ translate('delete') }}">
                                                    <i class="fi fi-rr-trash"></i>
                                                </button>
                                            </div>

                                            @if($token->is_active)
                                                <form action="{{ route('admin.third-party.erp.revoke') }}" method="post"
                                                      id="erp-revoke-form-{{ $token->id }}" class="d-none">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $token->id }}">
                                                </form>
                                            @endif
                                            <form action="{{ route('admin.third-party.erp.delete') }}" method="post"
                                                  id="erp-delete-form-{{ $token->id }}" class="d-none">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $token->id }}">
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center p-4">
                        <p class="mb-0">{{ translate('no_tokens_yet') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="erpSetupGuideModal" tabindex="-1" aria-labelledby="erpSetupGuideModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="erpSetupGuideModalLabel">
                        <i class="fi fi-rr-info fs-18"></i>
                        {{ translate('How_to_generate_&_use_API_tokens') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('close') }}"></button>
                </div>
                <div class="modal-body p-20">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 border shadow-none">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="erp-step-badge">1</span>
                                        <h6 class="mb-0 fw-semibold">{{ translate('Generate_a_Token') }}</h6>
                                    </div>
                                    <p class="mb-0 text-muted fs-12">
                                        {{ translate('Enter_a_token_name_and_the_Webhook_URL._You_can_copy_the_Webhook_URL_from_your_ERP_panel_using_the_Copy_Webhook_URL_button_on_the_Integration_page._Then_click_Generate_Token') }}.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border shadow-none">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="erp-step-badge">2</span>
                                        <h6 class="mb-0 fw-semibold">{{ translate('Copy_Credentials') }}</h6>
                                    </div>
                                    <p class="mb-0 text-muted fs-12">
                                        {{ translate('Copy_the_API_Key_API_Secret_and_Base_URL_immediately_the_secret_cannot_be_viewed_again_after_you_leave_this_page') }}.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border shadow-none">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="erp-step-badge">3</span>
                                        <h6 class="mb-0 fw-semibold">{{ translate('Add_Connection_in_ERP') }}</h6>
                                    </div>
                                    <p class="mb-0 text-muted fs-12">
                                        {{ translate('Open_your_ERP_panel_→_Settings_→_Integration_→_Add_Connection._Paste_the_API_Key,_API_Secret,_and_Base_URL') }}.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 bg-section rounded-8 p-12 p-sm-20 mb-3">
                        <div>
                            <h6 class="mb-1 fw-semibold">{{ translate('Queue_Worker_Setup_Guide') }}</h6>
                            <p class="mb-0 text-muted fs-12">
                                {{ translate('the_ERP_integration_relies_on_a_running_queue_worker._download_this_guide_to_set_it_up_on_your_server') }}.
                            </p>
                        </div>
                        <a href="{{ dynamicAsset(path: 'public/assets/new/back-end/pdf/erp-queue-worker-setup-guide.pdf') }}"
                           class="btn btn-outline-primary d-flex align-items-center gap-1" download>
                            <i class="fi fi-rr-download"></i>
                            {{ translate('Download_PDF') }}
                        </a>
                    </div>
                    <div class="rounded-8 overflow-hidden border ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/L0imBakAkg8"
                                title="{{ translate('ERP_Integration_Setup_Guide') }}"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";

        $(document).on('click', '.erp-value-toggle', function () {
            let $field = $($(this).data('target'));
            let $icon = $(this).find('i');
            let value = $field.data('value');
            if ($field.data('visible')) {
                $field.text('•'.repeat(value.length)).data('visible', false);
                $icon.removeClass('fi-rr-eye').addClass('fi-rr-eye-crossed');
            } else {
                $field.text(value).data('visible', true);
                $icon.removeClass('fi-rr-eye-crossed').addClass('fi-rr-eye');
            }
        });

        $(document).on('click', '.erp-copy', function () {
            let $field = $($(this).data('id'));
            let value = $field.data('value') || $field.text();
            let $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(value).select();
            document.execCommand('copy');
            $temp.remove();
            toastMagic.success('Copied to the clipboard');
        });
    </script>
@endpush
