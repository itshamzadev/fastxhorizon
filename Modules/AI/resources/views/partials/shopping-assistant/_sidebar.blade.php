@php
    $aiActive = function_exists('getCheckAddonPublishedStatus')
        && getCheckAddonPublishedStatus(moduleName: 'AI')
        && getWebConfig(name: 'ai_shopping_assistant_status') == 1
        && \Modules\AI\app\Models\AISetting::where('status', 1)->exists();

    // Cache-bust assets by file mtime; no build hash exists.
    $hexaCssPath = public_path('assets/front-end/ai/ai-shopping-assistant.css');
    $hexaJsPath  = public_path('assets/front-end/ai/ai-shopping-assistant.js');
    $hexaCssVer  = is_file($hexaCssPath) ? filemtime($hexaCssPath) : null;
    $hexaJsVer   = is_file($hexaJsPath) ? filemtime($hexaJsPath) : null;

    $hexaSuggestedPrompts = [
        translate('Show_me_todays_best_deals'),
        translate('Suggest_a_good_phone_under_1000'),
        translate('What_brands_do_you_have'),
        translate('Show_me_trending_products'),
    ];
@endphp

@if($aiActive)
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/front-end/ai/ai-shopping-assistant.css') }}{{ $hexaCssVer ? '?v=' . $hexaCssVer : '' }}">

<span id="hexa-ai-config"
      data-route-sessions="{{ route('ai.assistant.sessions.start') }}"
      data-route-sessions-list="{{ route('ai.assistant.sessions.list') }}"
      data-route-session-base="{{ url('ai/assistant/sessions') }}"
      data-route-upload-image="{{ route('ai.assistant.upload-image') }}"
      data-route-cart-add="{{ route('cart.add') }}"
      data-route-checkout="{{ route('checkout-details') }}"
      data-auth="{{ auth('customer')->check() ? '1' : '0' }}"
      data-customer-id="{{ auth('customer')->id() ?? '' }}"
      data-user-avatar="{{ auth('customer')->check() ? getStorageImages(path: auth('customer')->user()->image_full_url, type: 'avatar') : '' }}"
      data-direction="{{ session('direction', 'ltr') }}"
      data-locale="{{ app()->getLocale() }}"
      data-txt-add-to-cart="{{ translate('Add_to_Cart') }}"
      data-txt-buy-now="{{ translate('Buy_Now') }}"
      data-txt-added-cart="{{ translate('Added_to_your_cart') }}"
      data-txt-cart-failed="{{ translate('Could_not_add_this_item_Please_try_again') }}"
      data-txt-checkout-confirm="{{ translate('Ready_to_place_your_order') }}"
      data-txt-proceed-checkout="{{ translate('Proceed_to_checkout') }}"
      data-txt-keep-shopping="{{ translate('Keep_shopping') }}"
      data-txt-adding-cart="{{ translate('Adding_to_your_cart') }}"
      data-txt-redirecting="{{ translate('Redirecting_to_checkout') }}"
      data-txt-color="{{ translate('Color') }}"
      data-txt-format="{{ translate('Format') }}"
      data-txt-qty="{{ translate('Qty') }}"
      data-txt-quantity="{{ translate('Quantity') }}"
      data-txt-close="{{ translate('Close') }}"
      data-txt-conversation="{{ translate('Conversation') }}"
      data-txt-only="{{ translate('Only') }}"
      data-txt-left="{{ translate('left') }}"
      data-txt-add="{{ translate('add') }}"
      data-txt-more="{{ translate('more') }}"
      data-txt-just-now="{{ translate('Just_now') }}"
      data-txt-minutes-ago="{{ translate('minutes_ago') }}"
      data-txt-hours-ago="{{ translate('hours_ago') }}"
      data-txt-days-ago="{{ translate('days_ago') }}"
      data-txt-login-buy="{{ translate('Please_log_in_to_buy') }}"
      data-txt-login-cart="{{ translate('Please_log_in_to_add_to_cart') }}"
      data-txt-no-convos="{{ translate('No_conversations_yet') }}"
      data-txt-gibberish="{{ translate('I_didnt_quite_catch_that_Could_you_describe_what_product_youre_looking_for') }}"
      data-txt-write-message="{{ translate('Please_write_a_message_before_sending') }}"
      data-txt-image-invalid="{{ translate('Only_JPG_JPEG_PNG_or_WEBP_images_are_allowed') }}"
      data-txt-image-too-large="{{ translate('Image_must_be_5_MB_or_smaller') }}"
      data-txt-rate-limited="{{ translate('Youre_sending_messages_a_bit_fast_Please_wait_a_moment_and_try_again') }}"
      data-txt-session-gone="{{ translate('This_conversation_is_no_longer_available_Starting_a_new_chat') }}"
      data-txt-session-expired="{{ translate('Your_session_has_expired_Please_refresh_the_page_and_try_again') }}"
      data-txt-generic-error="{{ translate('Something_went_wrong_Please_try_again') }}"
      data-txt-image-dropped="{{ translate('I_couldnt_attach_your_image_so_Ill_answer_based_on_your_message') }}"
      data-txt-out-of-stock="{{ translate('Out_of_stock') }}"
      data-txt-min-not-met="{{ translate('Minimum_order_not_met') }}"
      data-txt-delete="{{ translate('Delete_conversation') }}"
      data-login-url="{{ route('customer.auth.login') }}"
      data-product-url-base="{{ url('product') }}"
      data-intro-message="{{ translate('Hi_I_am_your_shopping_assistant_at') }} {{ $web_config['company_name'] ?? config('app.name') }}. {{ translate('I_can_help_you_find_products_compare_options_discover_todays_deals_and_add_items_to_your_cart_What_are_you_looking_for_today') }}"
      data-suggested-prompts="{{ json_encode($hexaSuggestedPrompts) }}"
      data-txt-suggested-heading="{{ translate('Try_asking') }}"
      data-txt-product-hint="{{ translate('Tip_click_a_products_name_on_its_card_to_view_full_details') }}"
      data-max-input-length="300"
      data-txt-input-limit="{{ translate('You_have_reached_the_300_character_limit') }}"
      data-txt-delete-confirm-title="{{ translate('Delete_this_conversation') }}"
      data-txt-delete-confirm-text="{{ translate('This_chat_will_be_permanently_removed_This_action_cannot_be_undone') }}"
      data-txt-delete-confirm-yes="{{ translate('Yes_delete_it') }}"
      data-txt-cancel="{{ translate('Cancel') }}"
      class="d-none"
></span>

<button class="hexa-ai-launcher" id="hexaAiLauncher" aria-label="{{ translate('Open_AI_Shopping_Assistant') }}" type="button">
    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M14 3.5L15.8 10.2L22.5 12L15.8 13.8L14 20.5L12.2 13.8L5.5 12L12.2 10.2L14 3.5Z" fill="white"/>
        <path d="M22 19L22.9 21.1L25 22L22.9 22.9L22 25L21.1 22.9L19 22L21.1 21.1L22 19Z" fill="white" opacity="0.7"/>
        <path d="M7 3L7.7 4.8L9.5 5.5L7.7 6.2L7 8L6.3 6.2L4.5 5.5L6.3 4.8L7 3Z" fill="white" opacity="0.5"/>
    </svg>
</button>

<div class="hexa-ai-panel" id="hexaAiPanel" role="dialog" aria-modal="true" aria-label="{{ translate('HexaAi_Shopping_Assistant') }}">

    <div class="hexa-ai-header">
        <div class="hexa-ai-brand">
            <span class="hexa-ai-brand-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 3.5L15.8 10.2L22.5 12L15.8 13.8L14 20.5L12.2 13.8L5.5 12L12.2 10.2L14 3.5Z" fill="currentColor"/>
                    <path d="M22 19L22.9 21.1L25 22L22.9 22.9L22 25L21.1 22.9L19 22L21.1 21.1L22 19Z" fill="currentColor" opacity="0.6"/>
                </svg>
            </span>
            <span class="hexa-ai-brand-name">{{ translate('HexaAi') }}</span>
        </div>
        <div class="hexa-ai-header-actions">
            <button class="hexa-ai-icon-btn" id="hexaAiHistoryToggle" aria-label="{{ translate('Chat_history') }}" title="{{ translate('Chat_history') }}" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="15" y2="18"/>
                </svg>
            </button>
            <button class="hexa-ai-icon-btn hexa-ai-close" id="hexaAiClose" aria-label="{{ translate('Close') }}" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="hexa-ai-body" id="hexaAiBody">

        <div class="hexa-ai-state hexa-ai-conversation" id="hexaAiStateConversation">
            <div class="hexa-ai-messages" id="hexaAiMessages" role="log" aria-live="polite" aria-label="{{ translate('Conversation') }}">
            </div>
            <div class="hexa-ai-typing" id="hexaAiTyping" aria-label="{{ translate('AI_is_thinking') }}" hidden>
                <span class="hexa-ai-avatar hexa-ai-typing-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 3.5L15.8 10.2L22.5 12L15.8 13.8L14 20.5L12.2 13.8L5.5 12L12.2 10.2L14 3.5Z" fill="currentColor"/>
                        <path d="M22 19L22.9 21.1L25 22L22.9 22.9L22 25L21.1 22.9L19 22L21.1 21.1L22 19Z" fill="currentColor" opacity="0.6"/>
                    </svg>
                </span>
                <div class="hexa-ai-typing-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>

        <div class="hexa-ai-state hexa-ai-history" id="hexaAiStateHistory">
            <div class="hexa-ai-history-list" id="hexaAiHistoryList" role="list">
            </div>
            <div class="hexa-ai-history-footer">
                <button class="hexa-ai-new-chat-btn" id="hexaAiNewChat" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    {{ translate('New_Chat') }}
                </button>
            </div>
        </div>

    </div>

    <div class="hexa-ai-footer">
        <div class="hexa-ai-input-wrap" id="hexaAiInputWrap">
            <textarea
                class="hexa-ai-input"
                id="hexaAiInput"
                rows="1"
                placeholder="{{ translate('Write_me_about_your_item') }}…"
                maxlength="300"
                aria-label="{{ translate('Message') }}"
            ></textarea>
            <div class="hexa-ai-input-actions">
<button class="hexa-ai-icon-btn hexa-ai-img-btn" id="hexaAiImgBtn" aria-label="{{ translate('Upload_image') }}" type="button" title="{{ translate('Upload_image') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                </button>
                <input type="file" id="hexaAiImgInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="d-none" aria-hidden="true">
                <button class="hexa-ai-send-btn" id="hexaAiSend" aria-label="{{ translate('Send') }}" type="button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="hexa-ai-char-count" id="hexaAiCharCount" aria-live="polite" hidden></div>
        <div class="hexa-ai-img-preview" id="hexaAiImgPreview" hidden>
            <img id="hexaAiImgThumb" src="" alt="{{ translate('Selected_image') }}">
            <button class="hexa-ai-img-remove" id="hexaAiImgRemove" type="button" aria-label="{{ translate('Remove_image') }}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

</div>

<div class="hexa-ai-overlay" id="hexaAiOverlay" aria-hidden="true"></div>

<script src="{{ dynamicAsset(path: 'public/assets/front-end/ai/ai-shopping-assistant.js') }}{{ $hexaJsVer ? '?v=' . $hexaJsVer : '' }}" defer></script>
@endif
