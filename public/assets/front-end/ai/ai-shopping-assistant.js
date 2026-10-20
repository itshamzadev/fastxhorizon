// HexaAi Shopping Assistant — vanilla JS, BS4 + BS5 safe. No jQuery, no Bootstrap modal dependency.
(function () {
    'use strict';

    const cfg = document.getElementById('hexa-ai-config');
    if (!cfg) return;

    const ROUTES = {
        sessions:     cfg.dataset.routeSessions,
        sessionsList: cfg.dataset.routeSessionsList,
        sessionBase:  cfg.dataset.routeSessionBase,
        uploadImage:  cfg.dataset.routeUploadImage,
        cartAdd:      cfg.dataset.routeCartAdd,
        checkout:     cfg.dataset.routeCheckout,
    };
    const CART_MUTATING_ACTIONS = ['added', 'updated', 'checkout', 'minimum_not_met'];
    const isAuth          = cfg.dataset.auth === '1';
    const direction       = cfg.dataset.direction || 'ltr';
    const productUrlBase  = cfg.dataset.productUrlBase || '';
    const userAvatar  = cfg.dataset.userAvatar || '';
    const TXT = {
        addToCart:    cfg.dataset.txtAddToCart    || 'Add to Cart',
        buyNow:       cfg.dataset.txtBuyNow       || 'Buy Now',
        addingCart:   cfg.dataset.txtAddingCart   || 'Adding to your cart…',
        redirecting:  cfg.dataset.txtRedirecting  || 'Redirecting to checkout…',
        addedToCart:  cfg.dataset.txtAddedCart    || 'Added to your cart.',
        cartFailed:   cfg.dataset.txtCartFailed   || 'Could not add this item. Please try again.',
        checkoutConfirm: cfg.dataset.txtCheckoutConfirm || 'Ready to place your order?',
        proceedCheckout: cfg.dataset.txtProceedCheckout || 'Proceed to checkout',
        keepShopping:    cfg.dataset.txtKeepShopping    || 'Keep shopping',
        noConvos:     cfg.dataset.txtNoConvos     || 'No conversations yet',
        delConvo:     cfg.dataset.txtDelete       || 'Delete conversation',
        gibberish:     cfg.dataset.txtGibberish     || "I didn't quite catch that. Could you describe what product you're looking for?",
        introMessage:  cfg.dataset.introMessage     || "Hi! I'm your Shopping Assistant. Tell me what you're looking for and I'll help you find the best products!",
        writeMessage:  cfg.dataset.txtWriteMessage  || 'Please write a message before sending.',
        imageInvalid:  cfg.dataset.txtImageInvalid  || 'Only JPG, JPEG, PNG or WEBP images are allowed.',
        imageTooLarge: cfg.dataset.txtImageTooLarge || 'Image must be 5 MB or smaller.',
        rateLimited:   cfg.dataset.txtRateLimited   || "You're sending messages a bit fast. Please wait a moment and try again.",
        sessionGone:   cfg.dataset.txtSessionGone   || 'This conversation is no longer available. Starting a new chat.',
        sessionExpired: cfg.dataset.txtSessionExpired || 'Your session has expired. Please refresh the page and try again.',
        genericError:  cfg.dataset.txtGenericError  || 'Something went wrong. Please try again.',
        imageDropped:  cfg.dataset.txtImageDropped  || "I couldn't attach your image, so I'll answer based on your message.",
        outOfStock:    cfg.dataset.txtOutOfStock    || 'Out of stock',
        minNotMetTitle: cfg.dataset.txtMinNotMet    || 'Minimum order not met',
        suggestedHeading: cfg.dataset.txtSuggestedHeading || 'Try asking',
        productHint:   cfg.dataset.txtProductHint   || 'Tip: click a product’s name on its card to view full details.',
        inputLimit:    cfg.dataset.txtInputLimit    || 'You’ve reached the 300 character limit.',
        deleteTitle:   cfg.dataset.txtDeleteConfirmTitle || 'Delete this conversation?',
        deleteText:    cfg.dataset.txtDeleteConfirmText  || 'This chat will be permanently removed. This action cannot be undone.',
        deleteYes:     cfg.dataset.txtDeleteConfirmYes   || 'Yes, delete it',
        cancel:        cfg.dataset.txtCancel         || 'Cancel',
        colorLabel:    cfg.dataset.txtColor          || 'Color',
        formatLabel:   cfg.dataset.txtFormat         || 'Format',
        qtyLabel:      cfg.dataset.txtQty            || 'Qty',
        quantity:      cfg.dataset.txtQuantity       || 'Quantity',
        close:         cfg.dataset.txtClose          || 'Close',
        conversation:  cfg.dataset.txtConversation   || 'Conversation',
        only:          cfg.dataset.txtOnly           || 'Only',
        left:          cfg.dataset.txtLeft           || 'left',
        add:           cfg.dataset.txtAdd            || 'add',
        more:          cfg.dataset.txtMore           || 'more',
        timeJustNow:    cfg.dataset.txtJustNow       || 'Just now',
        timeMinutesAgo: cfg.dataset.txtMinutesAgo    || 'Minutes ago',
        timeHoursAgo:   cfg.dataset.txtHoursAgo      || 'Hours ago',
        timeDaysAgo:    cfg.dataset.txtDaysAgo       || 'Days ago',
    };

    function onlyLeftText(count)  { return `${TXT.only} ${count} ${TXT.left}`; }
    function addMoreText(amount)  { return `${TXT.add} ${amount} ${TXT.more}`; }

    const MAX_INPUT_LENGTH = parseInt(cfg.dataset.maxInputLength, 10) || 300;

    let SUGGESTED_PROMPTS = [];
    try { SUGGESTED_PROMPTS = JSON.parse(cfg.dataset.suggestedPrompts || '[]'); }
    catch (_) { SUGGESTED_PROMPTS = []; }

    // Accepted image upload types — kept in sync with backend mimes validation.
    const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const ALLOWED_IMAGE_EXT   = ['jpg', 'jpeg', 'png', 'webp'];
    const MAX_IMAGE_BYTES     = 5 * 1024 * 1024;

    const cardSelections = {};

    const addedSelections = {};

    function clearCardSelections() {
        Object.keys(cardSelections).forEach(k => delete cardSelections[k]);
        Object.keys(addedSelections).forEach(k => delete addedSelections[k]);
    }

    const GUEST_KEY = 'hexa_ai_guest_id';

    function getGuestId() {
        if (isAuth) return null;
        return localStorage.getItem(GUEST_KEY);
    }

    function saveGuestId(id) {
        if (id) localStorage.setItem(GUEST_KEY, id);
    }

    const launcher       = document.getElementById('hexaAiLauncher');
    const panel          = document.getElementById('hexaAiPanel');
    const overlay        = document.getElementById('hexaAiOverlay');
    const closeBtn       = document.getElementById('hexaAiClose');
    const historyToggle  = document.getElementById('hexaAiHistoryToggle');
    const newChatBtn     = document.getElementById('hexaAiNewChat');
    const sendBtn        = document.getElementById('hexaAiSend');
    const input          = document.getElementById('hexaAiInput');
    const charCountEl     = document.getElementById('hexaAiCharCount');
    const imgBtn         = document.getElementById('hexaAiImgBtn');
    const imgInput       = document.getElementById('hexaAiImgInput');
    const imgPreview     = document.getElementById('hexaAiImgPreview');
    const imgThumb       = document.getElementById('hexaAiImgThumb');
    const imgRemove      = document.getElementById('hexaAiImgRemove');
    const typingEl       = document.getElementById('hexaAiTyping');
    const messagesEl     = document.getElementById('hexaAiMessages');
    const historyListEl  = document.getElementById('hexaAiHistoryList');

    const stateConversation = document.getElementById('hexaAiStateConversation');
    const stateHistory      = document.getElementById('hexaAiStateHistory');
    const footerEl          = panel.querySelector('.hexa-ai-footer');

    let currentSessionId  = null;
    let pendingImageUrl   = null;
    let pendingImageFile  = null;
    let isProcessing      = false;
    let introShown        = false;
    let hasHistory        = false;
    let sessionsChecked   = false;
    let sessionsPrefetch  = null;
    let typingTimers      = [];
    const MIN_TYPING_MS   = 650;   // minimum "thinking" dwell before a reply renders

    function lockPageScroll() {
        const sbw = window.innerWidth - document.documentElement.clientWidth;
        if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
        document.body.classList.add('hexa-ai-noscroll');
    }

    function unlockPageScroll() {
        document.body.classList.remove('hexa-ai-noscroll');
        document.body.style.paddingRight = '';
    }

    async function openPanel() {
        panel.classList.add('open');
        overlay.classList.add('visible');
        launcher.classList.add('hidden');
        panel.setAttribute('aria-hidden', 'false');
        lockPageScroll();

        if (currentSessionId) {
            showState(stateConversation);
        } else {
            showNewChatWithIntro();
        }
        input.focus();

        if (!sessionsChecked) {
            await (sessionsPrefetch || loadSessions());
        }
        updateToggleVisibility();
    }

    function prefetchSessions() {
        if (sessionsChecked || sessionsPrefetch) return;
        sessionsPrefetch = loadSessions();
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('visible');
        launcher.classList.remove('hidden');
        panel.setAttribute('aria-hidden', 'true');
        unlockPageScroll();
    }

    function updateToggleVisibility() {
        historyToggle.hidden = !hasHistory || stateHistory.classList.contains('active');
    }

    function showState(state) {
        [stateConversation, stateHistory].forEach(s => s.classList.remove('active'));
        state.classList.add('active');
        if (footerEl) footerEl.hidden = (state === stateHistory);
        updateToggleVisibility();
    }

    function withGuestParam(url) {
        const id = getGuestId();
        if (!id) return url;
        const sep = url.includes('?') ? '&' : '?';
        return `${url}${sep}guest_id=${encodeURIComponent(id)}`;
    }

    async function loadSessions() {
        try {
            const res  = await apiFetch(withGuestParam(ROUTES.sessionsList), 'GET');
            const data = await res.json();
            sessionsChecked = true;
            if (data.sessions && data.sessions.length > 0) {
                hasHistory = true;
                renderHistoryList(data.sessions);
            } else {
                hasHistory = false;
            }
            updateToggleVisibility();
        } catch (_) {
            sessionsChecked = true;
        }
    }

    async function openHistoryList() {
        await loadSessions();
        if (hasHistory) showState(stateHistory);
    }

    function showNewChatWithIntro() {
        clearTyping();
        messagesEl.innerHTML = '';
        clearCardSelections();
        introShown = false;
        showState(stateConversation);
        appendIntroBubble(true);
    }

    function appendIntroBubble(animate = false) {
        if (introShown || messagesEl.children.length) return;
        introShown = true;
        const row = document.createElement('div');
        row.className = 'hexa-ai-msg-intro';
        row.appendChild(createAiIcon());
        const bubble = document.createElement('div');
        bubble.className = 'hexa-ai-intro-bubble';
        row.appendChild(bubble);
        messagesEl.appendChild(row);

        if (animate) {
            playIntroTyping(bubble, renderSuggestedPrompts);
        } else {
            bubble.textContent = TXT.introMessage;
            renderSuggestedPrompts();
        }
    }

    function renderSuggestedPrompts() {
        if (!SUGGESTED_PROMPTS.length) return;
        if (messagesEl.querySelector('.hexa-ai-suggested')) return;

        const wrap = document.createElement('div');
        wrap.className = 'hexa-ai-suggested';

        const heading = document.createElement('div');
        heading.className = 'hexa-ai-suggested-heading';
        heading.textContent = TXT.suggestedHeading;
        wrap.appendChild(heading);

        const chips = document.createElement('div');
        chips.className = 'hexa-ai-suggested-chips';
        SUGGESTED_PROMPTS.forEach(prompt => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'hexa-ai-suggested-chip';
            chip.textContent = prompt;
            chip.addEventListener('click', () => {
                if (isProcessing) return;
                input.value = prompt.slice(0, MAX_INPUT_LENGTH);
                updateCharCount();
                sendMessage();
            });
            chips.appendChild(chip);
        });
        wrap.appendChild(chips);
        messagesEl.appendChild(wrap);
    }

    function removeSuggestedPrompts() {
        messagesEl.querySelectorAll('.hexa-ai-suggested').forEach(el => el.remove());
    }

    function playIntroTyping(bubble, onSettled = null) {
        clearTyping();
        const text = TXT.introMessage;
        if (prefersReducedMotion()) {
            bubble.textContent = text;
            if (onSettled) onSettled();
            return;
        }

        bubble.innerHTML = '<span class="hexa-ai-intro-typing">'
            + '<span class="hexa-ai-dot"></span><span class="hexa-ai-dot"></span><span class="hexa-ai-dot"></span></span>';
        scrollMessages();

        typingTimers.push(setTimeout(() => {
            bubble.textContent = text;
            if (onSettled) onSettled();
            scrollMessages();
        }, 750));
    }

    function clearTyping() {
        typingTimers.forEach(clearTimeout);
        typingTimers = [];
    }

    function finishIntroTyping() {
        clearTyping();
        const bubble = messagesEl.querySelector('.hexa-ai-intro-bubble');
        if (bubble) bubble.textContent = TXT.introMessage;
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    const wait = ms => new Promise(resolve => { typingTimers.push(setTimeout(resolve, ms)); });

    async function startNewSession() {
        currentSessionId = null;
        showNewChatWithIntro();
        input.focus();
    }

    async function createSession() {
        const res  = await apiFetch(ROUTES.sessions, 'POST');
        const data = await res.json();
        if (!data.status) throw new Error('Failed to create session');
        if (data.guest_id) saveGuestId(data.guest_id);
        currentSessionId = data.session.id;
        hasHistory = true;
        updateToggleVisibility();
        return currentSessionId;
    }

    async function loadSession(sessionId) {
        clearTyping();
        const res  = await apiFetch(withGuestParam(`${ROUTES.sessionBase}/${sessionId}`), 'GET');
        const data = await res.json();
        if (!data.status) return;
        currentSessionId = sessionId;
        messagesEl.innerHTML = '';
        clearCardSelections();
        (data.session.messages || []).forEach(msg => {
            if (msg.role === 'user') {
                appendUserBubble(msg.content);
            } else if (msg.role === 'assistant') {
                const meta = msg.meta || {};
                appendAiBubble(msg.content, meta.products || []);
            }
        });
        introShown = true;
        showState(stateConversation);
        scrollMessages();
    }

    function confirmDeleteSession(sessionId, itemEl) {
        if (itemEl.classList.contains('confirming')) return;
        itemEl.classList.add('confirming');

        const confirm = document.createElement('div');
        confirm.className = 'hexa-ai-history-confirm';
        confirm.addEventListener('click', e => e.stopPropagation());

        const msg = document.createElement('span');
        msg.className = 'hexa-ai-history-confirm-text';
        msg.textContent = TXT.deleteTitle;

        const actions = document.createElement('div');
        actions.className = 'hexa-ai-history-confirm-actions';

        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'hexa-ai-history-confirm-cancel';
        cancel.textContent = TXT.cancel;
        cancel.addEventListener('click', e => {
            e.stopPropagation();
            itemEl.classList.remove('confirming');
            confirm.remove();
        });

        const yes = document.createElement('button');
        yes.type = 'button';
        yes.className = 'hexa-ai-history-confirm-yes';
        yes.textContent = TXT.deleteYes;
        yes.addEventListener('click', e => {
            e.stopPropagation();
            deleteSession(sessionId, itemEl);
        });

        actions.appendChild(cancel);
        actions.appendChild(yes);
        confirm.appendChild(msg);
        confirm.appendChild(actions);
        itemEl.appendChild(confirm);
    }

    async function deleteSession(sessionId, itemEl) {
        try {
            const res  = await apiFetch(`${ROUTES.sessionBase}/${sessionId}`, 'DELETE');
            const data = await res.json();
            if (data.status) {
                itemEl.remove();
                if (sessionId === currentSessionId) {
                    currentSessionId = null;
                    messagesEl.innerHTML = '';
                }
                if (!historyListEl.children.length) {
                    hasHistory = false;
                    showNewChatWithIntro();
                }
            }
        } catch (_) {}
    }

    function showInputError(message = TXT.writeMessage) {
        const wrap = input.closest('.hexa-ai-input-wrap');
        wrap.classList.remove('input-error');
        void wrap.offsetWidth; // reflow to restart animation
        wrap.classList.add('input-error');

        let errEl = panel.querySelector('.hexa-ai-input-error-msg');
        if (!errEl) {
            errEl = document.createElement('p');
            errEl.className = 'hexa-ai-input-error-msg';
            wrap.parentElement.appendChild(errEl);
        }
        errEl.textContent = message;

        clearTimeout(showInputError._t);
        showInputError._t = setTimeout(() => {
            wrap.classList.remove('input-error');
            if (errEl) errEl.remove();
        }, 3000);

        input.focus();
    }

    async function sendMessage() {
        const text = input.value.trim();
        if (isProcessing) return;
        if (!text) { showInputError(); return; }

        const imagePreviewUrl = pendingImageUrl;
        const imageFile       = pendingImageFile;

        showState(stateConversation);
        finishIntroTyping();
        appendIntroBubble(false);
        removeSuggestedPrompts();
        appendUserBubble(text, imagePreviewUrl);
        input.value = '';
        resizeInput();
        updateCharCount();
        clearImagePreview();
        scrollMessages();

        isProcessing = true;
        sendBtn.disabled = true;

        if (!currentSessionId) {
            try {
                await createSession();
            } catch (_) {
                isProcessing = false;
                sendBtn.disabled = false;
                return;
            }
        }

        typingEl.hidden = false;
        messagesEl.appendChild(typingEl);
        scrollMessages();
        const typingStartedAt = Date.now();

        try {
            let imageUrl = null, imageFailed = false;
            if (imageFile) {
                try { imageUrl = (await uploadImage(imageFile)).url || null; }
                catch (_) { imageFailed = true; }
            }

            const selections = Object.values(cardSelections);
            const res  = await apiFetch(`${ROUTES.sessionBase}/${currentSessionId}/message`, 'POST', {
                message:         text,
                image_url:       imageUrl || undefined,
                cart_selections: selections.length ? selections : undefined,
            });
            const data = await res.json().catch(() => ({}));

            const elapsed = Date.now() - typingStartedAt;
            if (elapsed < MIN_TYPING_MS) await wait(MIN_TYPING_MS - elapsed);
            typingEl.hidden = true;

            if (imageFailed) appendAiBubble(TXT.imageDropped, []);

            if (!res.ok) {
                if (res.status === 404) currentSessionId = null; // stale session → next send starts fresh
                appendAiBubble(httpErrorMessage(res.status, data), []);
            } else if (data.status) {
                // The reply renders first so action cards land under it, but a render failure must
                // never swallow the actions — the cart was already changed server-side.
                try {
                    if (data.intent === 'off_topic') {
                        appendOutOfScopeBlock(data.reply);
                    } else {
                        await appendAiBubble(data.reply, data.products || []);
                    }
                } finally {
                    handleAgentActions(data.actions || [], data.products || []);
                }
            } else {
                await appendAiBubble(data.message || TXT.genericError, []);
            }
        } catch (_) {
            typingEl.hidden = true;
            appendAiBubble(TXT.genericError, []);
        }

        scrollMessages();
        isProcessing = false;
        sendBtn.disabled = false;
        input.focus();
    }

    // Map HTTP status to curated copy; never forward framework/server error text (CSRF, 5xx) to the customer.
    function httpErrorMessage(status, data) {
        if (status === 429) return TXT.rateLimited;
        if (status === 404) return TXT.sessionGone;
        if (status === 419 || status === 401) return TXT.sessionExpired;
        if (status === 422) {
            const first = data && Array.isArray(data.errors) && data.errors[0];
            return (first && first.message) || TXT.genericError;
        }
        return TXT.genericError;
    }

    function createAiIcon() {
        const icon = document.createElement('span');
        icon.className = 'hexa-ai-avatar';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = `<svg width="16" height="16" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 3.5L15.8 10.2L22.5 12L15.8 13.8L14 20.5L12.2 13.8L5.5 12L12.2 10.2L14 3.5Z" fill="currentColor"/><path d="M22 19L22.9 21.1L25 22L22.9 22.9L22 25L21.1 22.9L19 22L21.1 21.1L22 19Z" fill="currentColor" opacity="0.6"/></svg>`;
        return icon;
    }

    function createUserIcon() {
        const icon = document.createElement('span');
        icon.className = 'hexa-ai-user-avatar';
        icon.setAttribute('aria-hidden', 'true');
        if (userAvatar) {
            const img = document.createElement('img');
            img.src = userAvatar;
            img.alt = '';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
            icon.appendChild(img);
        } else {
            icon.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z"/></svg>`;
        }
        return icon;
    }

    function appendUserBubble(text, imageUrl) {
        const row = document.createElement('div');
        row.className = 'hexa-ai-msg-user-row';

        const bubble = document.createElement('div');
        bubble.className = 'hexa-ai-msg-user';
        if (imageUrl) {
            const img = document.createElement('img');
            img.src = imageUrl;
            img.alt = '';
            img.style.cssText = 'max-width:120px;border-radius:8px;display:block;margin-bottom:6px;';
            bubble.appendChild(img);
        }
        bubble.appendChild(document.createTextNode(text));

        row.appendChild(bubble);
        row.appendChild(createUserIcon());
        messagesEl.appendChild(row);
    }

    function appendOutOfScopeBlock(message) {
        const wrap = document.createElement('div');
        wrap.className = 'hexa-ai-out-of-scope';

        const icon = document.createElement('span');
        icon.className = 'hexa-ai-out-of-scope-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>`;

        const text = document.createElement('span');
        text.className = 'hexa-ai-out-of-scope-text';
        text.textContent = message;

        wrap.appendChild(icon);
        wrap.appendChild(text);
        messagesEl.appendChild(wrap);
    }

    function appendAiBubble(reply, products) {
        const row = document.createElement('div');
        row.className = 'hexa-ai-msg-ai';
        row.appendChild(createAiIcon());

        const bubble = document.createElement('div');
        bubble.className = 'hexa-ai-msg-ai-bubble';

        const parts    = reply.split('\n');
        const leadText = parts.length > 1 ? parts[0] : reply;
        const bodyText = parts.length > 1 ? parts.slice(1).join('\n').trim() : '';

        const lead = document.createElement('span');
        lead.className = 'hexa-ai-msg-ai-lead';
        bubble.appendChild(lead);

        let body = null;
        if (bodyText) {
            body = document.createElement('span');
            body.className = 'hexa-ai-msg-ai-body';
            bubble.appendChild(body);
        }

        row.appendChild(bubble);
        messagesEl.appendChild(row);

        lead.textContent = leadText;
        if (body) body.textContent = bodyText;
        if (products && products.length > 0) {
            bubble.appendChild(buildProductGrid(products));
            const hint = document.createElement('div');
            hint.className = 'hexa-ai-product-hint';
            hint.textContent = TXT.productHint;
            bubble.appendChild(hint);
        }
        return Promise.resolve();
    }

    function buildProductGrid(products) {
        const grid = document.createElement('div');
        grid.className = 'hexa-ai-products';

        products.forEach(p => {
            const card = document.createElement('div');
            card.className = 'hexa-ai-product-card';
            card.dataset.productId = p.id;

            // Only plain-physical products judge stock here; digital is unlimited and variants resolve on the action card.
            const isDigital   = p.product_type === 'digital'
                || (Array.isArray(p.digital_variations) && p.digital_variations.length > 0);
            const hasVariants = (Array.isArray(p.colors) && p.colors.length > 0)
                || (Array.isArray(p.choice_options) && p.choice_options.length > 0)
                || (Array.isArray(p.variation) && p.variation.length > 0);
            const outOfStock  = !isDigital && !hasVariants
                && p.current_stock != null && Number(p.current_stock) <= 0;

            if (outOfStock) {
                card.classList.add('is-out-of-stock');
            } else {
                card.setAttribute('role', 'button');
                card.setAttribute('tabindex', '0');
            }

            const img = document.createElement('img');
            img.className = 'hexa-ai-product-img';
            img.src = p.thumbnail_full_url || p.thumbnail || '';
            img.alt = p.name || '';
            img.loading = 'lazy';

            const info = document.createElement('div');
            info.className = 'hexa-ai-product-info';

            const nameEl = document.createElement('a');
            nameEl.className = 'hexa-ai-product-name';
            nameEl.textContent = p.name || '';
            if (productUrlBase && p.slug) {
                nameEl.href   = `${productUrlBase}/${p.slug}`;
                nameEl.target = '_blank';
                nameEl.rel    = 'noopener noreferrer';
                nameEl.addEventListener('click', e => e.stopPropagation());
            } else {
                nameEl.removeAttribute('href');
            }

            const priceRow = document.createElement('div');
            if (p.unit_price && p.discount > 0) {
                const old = document.createElement('span');
                old.className = 'hexa-ai-product-old-price';
                old.textContent = p.unit_price_formatted || '';
                priceRow.appendChild(old);
            }
            const price = document.createElement('span');
            price.className = 'hexa-ai-product-price';
            price.textContent = p.discounted_price_formatted || p.unit_price_formatted || '';
            priceRow.appendChild(price);

            info.appendChild(nameEl);
            info.appendChild(priceRow);

            if (outOfStock) {
                const badge = document.createElement('div');
                badge.className = 'hexa-ai-product-stock hexa-ai-product-stock--out';
                badge.textContent = TXT.outOfStock;
                info.appendChild(badge);
            }

            if (p.avg_rating > 0) {
                const ratingEl = document.createElement('div');
                ratingEl.className = 'hexa-ai-product-rating';
                const filled = Math.round(p.avg_rating);
                ratingEl.textContent = '★'.repeat(filled) + '☆'.repeat(5 - filled)
                    + (p.rating_count ? ` (${p.rating_count})` : '');
                info.appendChild(ratingEl);
            }

            if (Array.isArray(p.colors) && p.colors.length > 0) {
                const dotsWrap = document.createElement('div');
                dotsWrap.className = 'hexa-ai-product-colors';
                const MAX_DOTS = 5;
                p.colors.slice(0, MAX_DOTS).forEach(c => {
                    const dot = document.createElement('span');
                    dot.className = 'hexa-ai-product-color-dot';
                    dot.style.background = c.code;
                    dot.title = c.name;
                    dotsWrap.appendChild(dot);
                });
                if (p.colors.length > MAX_DOTS) {
                    const more = document.createElement('span');
                    more.className = 'hexa-ai-product-color-more';
                    more.textContent = '+' + (p.colors.length - MAX_DOTS);
                    dotsWrap.appendChild(more);
                }
                info.appendChild(dotsWrap);
            }

            if (Array.isArray(p.choice_options) && p.choice_options.length > 0) {
                p.choice_options.forEach(opt => {
                    if (opt.options?.length > 0) {
                        const hint = document.createElement('div');
                        hint.className = 'hexa-ai-product-options-hint';
                        hint.textContent = opt.options.length + ' ' + (opt.title || opt.name);
                        info.appendChild(hint);
                    }
                });
            }

            card.appendChild(img);
            card.appendChild(info);

            if (!outOfStock) {
                card.addEventListener('click', () => selectProduct(p, card));
                card.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') selectProduct(p, card);
                });
            }

            grid.appendChild(card);
        });

        return grid;
    }

    function selectProduct(product, cardEl) {
        document.querySelectorAll('.hexa-ai-product-card').forEach(c => c.classList.remove('selected'));
        cardEl.classList.add('selected');
        appendActionCard(product, addedSelections[product.id] || null);
        scrollMessages();
    }

    function appendActionCard(product, preselect) {
        messagesEl.querySelectorAll(`.hexa-ai-action-card[data-product-id="${product.id}"]`).forEach(c => c.remove());
        preselect = preselect || {};

        const hasColors  = Array.isArray(product.colors)             && product.colors.length > 0;
        const hasChoices = Array.isArray(product.choice_options)     && product.choice_options.length > 0;
        const hasVars    = Array.isArray(product.variation)          && product.variation.length > 0;
        // Digital variations are a flat variant_key list (editions/formats), not the physical color+choice composite.
        const hasDigitalVars = Array.isArray(product.digital_variations) && product.digital_variations.length > 0;

        let selColor = hasColors ? product.colors[0].code : null;
        if (hasColors && preselect.color) {
            const m = product.colors.find(c =>
                c.code === preselect.color || (c.name || '').toLowerCase() === String(preselect.color).toLowerCase());
            if (m) selColor = m.code;
        }
        const selChoices = {};
        if (hasChoices) {
            product.choice_options.forEach(opt => {
                if (!opt.options?.length) return;
                let val = opt.options[0];
                const pv = preselect.choices && preselect.choices[opt.name];
                if (pv) {
                    const m = opt.options.find(o => String(o).toLowerCase() === String(pv).toLowerCase());
                    if (m) val = m;
                }
                selChoices[opt.name] = val;
            });
        }
        let selVariantKey = hasDigitalVars ? product.digital_variations[0].variant_key : null;
        if (hasDigitalVars && preselect.variant_key) {
            const m = product.digital_variations.find(v => v.variant_key === preselect.variant_key);
            if (m) selVariantKey = m.variant_key;
        }
        let selQty           = Math.max(parseInt(preselect.qty, 10) || 0, product.minimum_order_qty || 1);
        let currentStock     = hasVars ? null : (product.current_stock ?? null);
        let currentUnitPrice = product.discounted_price || product.unit_price || 0;
        let cartBtn, buyBtn;

        const card = document.createElement('div');
        card.className = 'hexa-ai-action-card';
        card.dataset.productId = product.id;

        const closeBtn = document.createElement('button');
        closeBtn.className = 'hexa-ai-action-close';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', TXT.close);
        closeBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        closeBtn.addEventListener('click', () => {
            card.remove();
            delete cardSelections[product.id];
            document.querySelectorAll(`.hexa-ai-product-card[data-product-id="${product.id}"]`).forEach(c => c.classList.remove('selected'));
        });
        card.appendChild(closeBtn);

        const header = document.createElement('div');
        header.className = 'hexa-ai-action-header';

        const imgEl = document.createElement('img');
        imgEl.className = 'hexa-ai-action-img';
        imgEl.src = product.thumbnail_full_url || '';
        imgEl.alt = product.name || '';
        header.appendChild(imgEl);

        const infoEl = document.createElement('div');
        infoEl.className = 'hexa-ai-action-info';

        const nameEl = document.createElement('div');
        nameEl.className = 'hexa-ai-action-name';
        nameEl.textContent = product.name || '';
        infoEl.appendChild(nameEl);

        if (product.avg_rating > 0) {
            const rEl = document.createElement('div');
            rEl.className = 'hexa-ai-action-rating';
            const f = Math.round(product.avg_rating);
            rEl.innerHTML = '★'.repeat(f) + '☆'.repeat(5 - f)
                + ` <span class="hexa-ai-rating-text">(${product.avg_rating})</span>`
                + (product.rating_count ? ` · <span class="hexa-ai-reviews">${product.rating_count} Reviews</span>` : '');
            infoEl.appendChild(rEl);
        }

        const priceRow = document.createElement('div');
        priceRow.className = 'hexa-ai-action-price-row';
        renderPriceRow(priceRow, product.unit_price_formatted, product.discounted_price_formatted, product.discount);
        infoEl.appendChild(priceRow);

        const stockEl = document.createElement('div');
        stockEl.className = 'hexa-ai-action-stock';
        infoEl.appendChild(stockEl);

        header.appendChild(infoEl);
        card.appendChild(header);

        if (hasColors) {
            card.appendChild(buildVarSection(
                TXT.colorLabel,
                buildColorSwatches(product.colors, selColor, code => {
                    selColor = code;
                    swapColorImage(imgEl, product, code);
                    syncVariation();
                    publishCardSelection();
                })
            ));
            swapColorImage(imgEl, product, selColor);
        }

        if (hasChoices) {
            product.choice_options.forEach(opt => {
                card.appendChild(buildVarSection(
                    opt.title || opt.name,
                    buildOptionBtns(opt.options, selChoices[opt.name], val => {
                        selChoices[opt.name] = val;
                        syncVariation();
                        publishCardSelection();
                    })
                ));
            });
        }

        if (hasDigitalVars) {
            const labelFor = key => {
                const v = product.digital_variations.find(dv => dv.variant_key === key);
                return v ? (v.label || v.variant_key) : key;
            };
            card.appendChild(buildVarSection(
                TXT.formatLabel,
                buildOptionBtns(
                    product.digital_variations.map(v => v.label || v.variant_key),
                    labelFor(selVariantKey),
                    label => {
                        const m = product.digital_variations.find(v => (v.label || v.variant_key) === label);
                        selVariantKey = m ? m.variant_key : selVariantKey;
                        syncVariation();
                        publishCardSelection();
                    },
                )
            ));
        }

        card.appendChild(buildQtySelector(selQty, product.minimum_order_qty || 1, () => currentStock, qty => {
            selQty = qty;
            updateCardState();
            publishCardSelection();
        }));

        const btnRow = document.createElement('div');
        btnRow.className = 'hexa-ai-action-buttons';

        cartBtn = document.createElement('button');
        cartBtn.className = 'hexa-ai-btn-cart';
        cartBtn.textContent = TXT.addToCart;
        cartBtn.type = 'button';

        buyBtn = document.createElement('button');
        buyBtn.className = 'hexa-ai-btn-buy';
        buyBtn.textContent = TXT.buyNow;
        buyBtn.type = 'button';

        cartBtn.addEventListener('click', () => handleAddToCart(product, selColor, { ...selChoices }, selQty, cartBtn, selVariantKey));
        buyBtn.addEventListener('click',  () => handleBuyNow(product,  selColor, { ...selChoices }, selQty, buyBtn,  selVariantKey));

        btnRow.appendChild(cartBtn);
        btnRow.appendChild(buyBtn);
        card.appendChild(btnRow);
        messagesEl.appendChild(card);

        function publishCardSelection() {
            cardSelections[product.id] = {
                product_id:  product.id,
                color:       selColor,
                choices:     { ...selChoices },
                variant_key: selVariantKey,
                qty:         selQty,
            };
        }
        publishCardSelection();

        function updateCardState() {
            const sym = (product.unit_price_formatted || '').replace(/[\d.,\s]/g, '') || '';

            priceRow.innerHTML = '';
            if (selQty === 1 && !hasVars && !hasDigitalVars && product.discount > 0) {
                const old = document.createElement('span');
                old.className = 'hexa-ai-action-old-price';
                old.textContent = product.unit_price_formatted || '';
                priceRow.appendChild(old);
            }
            if (selQty > 1) {
                const note = document.createElement('span');
                note.className = 'hexa-ai-action-unit-note';
                note.textContent = fmtMoney(sym, currentUnitPrice) + ' × ' + selQty;
                priceRow.appendChild(note);
            }
            const priceEl = document.createElement('span');
            priceEl.className = 'hexa-ai-action-price';
            priceEl.textContent = fmtMoney(sym, currentUnitPrice * selQty);
            priceRow.appendChild(priceEl);

            const outOfStock = currentStock !== null && (currentStock <= 0 || selQty > currentStock);

            stockEl.textContent = '';
            delete stockEl.dataset.state;
            if (outOfStock) {
                stockEl.textContent = TXT.outOfStock;
                stockEl.dataset.state = 'out';
            } else if (currentStock !== null && currentStock > 0 && currentStock <= 5) {
                stockEl.textContent = onlyLeftText(currentStock);
                stockEl.dataset.state = 'low';
            }

            if (cartBtn) cartBtn.disabled = outOfStock;
            if (buyBtn)  buyBtn.disabled  = outOfStock;
        }

        function syncVariation() {
            if (hasDigitalVars) {
                const variant = product.digital_variations.find(v => v.variant_key === selVariantKey);
                currentUnitPrice = variant ? variant.price : (product.discounted_price || product.unit_price || 0);
                currentStock     = null;
                updateCardState();
                return;
            }
            if (!hasVars) return;
            const vType   = buildVariationType(product, selColor, selChoices);
            const variant = product.variation.find(v => v.type === vType);
            if (variant) {
                currentUnitPrice = variant.price;
                currentStock     = variant.qty;
            } else {
                currentUnitPrice = product.discounted_price || product.unit_price || 0;
                currentStock     = null;
            }
            updateCardState();
        }

        syncVariation();
        if (!hasVars && !hasDigitalVars) updateCardState();
    }

    // Build variation type like CartManager: colorName + '-' + choiceValues (spaces stripped, dash-joined).
    function buildVariationType(product, colorCode, choices) {
        const parts = [];
        if (colorCode && Array.isArray(product.colors)) {
            const c = product.colors.find(col => col.code === colorCode);
            if (c?.name) parts.push(c.name);
        }
        if (Array.isArray(product.choice_options)) {
            product.choice_options.forEach(opt => {
                const v = choices[opt.name];
                if (v) parts.push(v.replace(/\s+/g, ''));
            });
        }
        return parts.join('-');
    }

    function swapColorImage(imgEl, product, colorCode) {
        if (!colorCode || !Array.isArray(product.color_images) || product.color_images.length === 0) return;
        const entry = product.color_images.find(ci => ci.code === colorCode);
        if (entry && entry.url) imgEl.src = entry.url;
    }

    function renderPriceRow(el, unitFmt, discountedFmt, discount) {
        el.innerHTML = '';
        if (discount > 0 && unitFmt) {
            const old = document.createElement('span');
            old.className = 'hexa-ai-action-old-price';
            old.textContent = unitFmt;
            el.appendChild(old);
        }
        const cur = document.createElement('span');
        cur.className = 'hexa-ai-action-price';
        cur.textContent = discountedFmt || unitFmt || '';
        el.appendChild(cur);
    }

    function fmtMoney(sym, amount) {
        return sym + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderVariantPrice(el, product, variant) {
        el.innerHTML = '';
        const sym = (product.unit_price_formatted || '').replace(/[\d.,\s]/g, '') || '';
        const cur = document.createElement('span');
        cur.className = 'hexa-ai-action-price';
        cur.textContent = fmtMoney(sym, variant.price);
        el.appendChild(cur);
    }

    function updateStockEl(el, qty) {
        if (qty <= 0) {
            el.textContent = TXT.outOfStock;
            el.dataset.state = 'out';
        } else if (qty <= 5) {
            el.textContent = onlyLeftText(qty);
            el.dataset.state = 'low';
        } else {
            el.textContent = '';
            delete el.dataset.state;
        }
    }

    function buildVarSection(label, content) {
        const sec = document.createElement('div');
        sec.className = 'hexa-ai-var-section';
        const lbl = document.createElement('span');
        lbl.className = 'hexa-ai-var-label';
        lbl.textContent = label + ':';
        sec.appendChild(lbl);
        sec.appendChild(content);
        return sec;
    }

    function buildColorSwatches(colors, selectedCode, onChange) {
        const wrap = document.createElement('div');
        wrap.className = 'hexa-ai-color-swatches';
        colors.forEach(c => {
            const sw = document.createElement('button');
            sw.type = 'button';
            sw.className = 'hexa-ai-color-swatch' + (c.code === selectedCode ? ' active' : '');
            sw.style.background = c.code;
            sw.title = c.name;
            sw.addEventListener('click', () => {
                wrap.querySelectorAll('.hexa-ai-color-swatch').forEach(s => s.classList.remove('active'));
                sw.classList.add('active');
                onChange(c.code);
            });
            wrap.appendChild(sw);
        });
        return wrap;
    }

    function buildOptionBtns(options, selectedVal, onChange) {
        const wrap = document.createElement('div');
        wrap.className = 'hexa-ai-choice-options';
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'hexa-ai-choice-opt-btn' + (opt === selectedVal ? ' active' : '');
            btn.textContent = opt;
            btn.addEventListener('click', () => {
                wrap.querySelectorAll('.hexa-ai-choice-opt-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                onChange(opt);
            });
            wrap.appendChild(btn);
        });
        return wrap;
    }

    function buildQtySelector(initQty, minQty, getMax, onChange) {
        const min = minQty || 1;
        const row = document.createElement('div');
        row.className = 'hexa-ai-qty-row';
        const lbl = document.createElement('span');
        lbl.className = 'hexa-ai-var-label';
        lbl.textContent = TXT.qtyLabel + ':';
        const ctr = document.createElement('div');
        ctr.className = 'hexa-ai-qty-counter';

        const minBtn = document.createElement('button');
        minBtn.type = 'button';
        minBtn.className = 'hexa-ai-qty-btn';
        minBtn.textContent = '−';

        const input = document.createElement('input');
        input.type = 'text';
        input.inputMode = 'numeric';
        input.className = 'hexa-ai-qty-display';
        input.setAttribute('aria-label', TXT.quantity);
        input.value = initQty;

        const plusBtn = document.createElement('button');
        plusBtn.type = 'button';
        plusBtn.className = 'hexa-ai-qty-btn';
        plusBtn.textContent = '+';

        let qty = initQty;

        function clamp(n) {
            const max = (typeof getMax === 'function') ? getMax() : null;
            if (isNaN(n)) n = min;
            n = Math.max(min, Math.floor(n));
            if (max !== null && max > 0) n = Math.min(n, max);
            return n;
        }
        function setQty(n) {
            qty = clamp(n);
            input.value = qty;
            onChange(qty);
        }

        minBtn.addEventListener('click', () => setQty(qty - 1));
        plusBtn.addEventListener('click', () => setQty(qty + 1));

        input.addEventListener('input', () => {
            const digits = input.value.replace(/\D/g, '');
            if (digits !== input.value) input.value = digits;
            if (digits === '') return;
            qty = parseInt(digits, 10);
            onChange(qty);
        });
        input.addEventListener('blur', () => setQty(parseInt(input.value, 10)));
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        });

        ctr.appendChild(minBtn);
        ctr.appendChild(input);
        ctr.appendChild(plusBtn);
        row.appendChild(lbl);
        row.appendChild(ctr);
        return row;
    }

    function handleAddToCart(product, colorCode, choices, qty, btn, variantKey) {
        if (btn) btn.disabled = true;
        appendAiBubble(TXT.addingCart, []);
        scrollMessages();
        postCart(product, colorCode, choices, qty, false, variantKey).finally(() => { if (btn) btn.disabled = false; });
    }

    function handleBuyNow(product, colorCode, choices, qty, btn, variantKey) {
        if (btn) btn.disabled = true;
        appendAiBubble(TXT.redirecting, []);
        scrollMessages();
        postCart(product, colorCode, choices, qty, true, variantKey).finally(() => { if (btn) btn.disabled = false; });
    }

    function flashActionCard(card) {
        card.classList.remove('hexa-ai-action-card-flash');
        void card.offsetWidth; // reflow to restart the animation
        card.classList.add('hexa-ai-action-card-flash');
        setTimeout(() => card.classList.remove('hexa-ai-action-card-flash'), 1200);
    }

    function handleAgentActions(actions, products) {
        actions = actions || [];
        const checkingOut = actions.some(a => a && a.type === 'checkout');
        actions.forEach(action => {
            if (checkingOut && action && action.type === 'select_variation') return;
            handleAgentAction(action, products);
        });

        if (actions.some(a => a && CART_MUTATING_ACTIONS.includes(a.type))) {
            notifyCartUpdated();
        }
    }

    // Broadcast a theme-agnostic cart-changed event since the AI script is shared across themes.
    function notifyCartUpdated() {
        document.dispatchEvent(new CustomEvent('hexa-ai:cart-updated'));
    }

    function handleAgentAction(action, products) {
        if (!action || !action.type) return;

        if (action.type === 'added') {
            retireProductCard(action.product_id);
            if (action.product_id != null && action.preselect) {
                addedSelections[action.product_id] = action.preselect;
            }
            return;
        }
        if (action.type === 'updated') {
            retireProductCard(action.product_id);
            return;
        }
        if (action.type === 'checkout' && action.url) {
            messagesEl.querySelectorAll('.hexa-ai-action-card').forEach(c => c.remove());
            clearCardSelections();
            appendCheckoutConfirm(action.url, action.name);
            scrollMessages();
            return;
        }
        // Buy Now below a shop minimum: item is in the cart but checkout is blocked; show shortfall, no button.
        if (action.type === 'minimum_not_met') {
            retireProductCard(action.product_id);
            appendMinimumNotMet(action.shops || []);
            scrollMessages();
            return;
        }
        if (action.type === 'select_variation') {
            const product  = (products || []).find(p => String(p.id) === String(action.product_id));
            const preselect = action.preselect || null;
            const existing  = messagesEl.querySelector(`.hexa-ai-action-card[data-product-id="${action.product_id}"]`);

            if (existing && !preselect) {
                flashActionCard(existing);
                existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (product) {
                appendActionCard(product, mergePreselect(product, preselect));
                scrollMessages();
                const card = messagesEl.querySelector(`.hexa-ai-action-card[data-product-id="${action.product_id}"]`);
                if (card) flashActionCard(card);
            }
        }
    }

    function retireProductCard(productId) {
        if (productId == null) return;
        messagesEl.querySelectorAll(`.hexa-ai-action-card[data-product-id="${productId}"]`).forEach(c => c.remove());
        delete cardSelections[productId];
    }

    function mergePreselect(product, preselect) {
        const base = cardSelections[product.id] || null;
        if (!preselect && !base) return null;
        return {
            color:       (preselect && preselect.color) || (base && base.color) || null,
            choices:     Object.assign({}, base && base.choices, preselect && preselect.choices),
            variant_key: (preselect && preselect.variant_key) || (base && base.variant_key) || null,
            qty:         (preselect && preselect.qty) || (base && base.qty) || null,
        };
    }

    function appendCheckoutConfirm(url, productName) {
        document.querySelectorAll('.hexa-ai-checkout-confirm').forEach(e => e.remove());

        const wrap = document.createElement('div');
        wrap.className = 'hexa-ai-checkout-confirm';

        const text = document.createElement('p');
        text.className = 'hexa-ai-checkout-confirm-text';
        text.textContent = productName
            ? `${TXT.checkoutConfirm} (${productName})`
            : TXT.checkoutConfirm;
        wrap.appendChild(text);

        const row = document.createElement('div');
        row.className = 'hexa-ai-checkout-confirm-actions';

        const proceed = document.createElement('button');
        proceed.className = 'hexa-ai-btn-buy';
        proceed.type = 'button';
        proceed.textContent = TXT.proceedCheckout;

        const cancel = document.createElement('button');
        cancel.className = 'hexa-ai-btn-cart';
        cancel.type = 'button';
        cancel.textContent = TXT.keepShopping;

        proceed.addEventListener('click', () => {
            wrap.remove();
            goToCheckout(url);
        });
        cancel.addEventListener('click', () => wrap.remove());

        row.appendChild(proceed);
        row.appendChild(cancel);
        wrap.appendChild(row);
        messagesEl.appendChild(wrap);
    }

    function appendMinimumNotMet(shops) {
        const wrap = document.createElement('div');
        wrap.className = 'hexa-ai-min-not-met';

        const title = document.createElement('p');
        title.className = 'hexa-ai-min-not-met-title';
        title.textContent = TXT.minNotMetTitle;
        wrap.appendChild(title);

        (shops || []).forEach(shop => {
            const row = document.createElement('div');
            row.className = 'hexa-ai-min-not-met-shop';
            const name = shop.shop ? shop.shop + ': ' : '';
            row.textContent = `${name}${shop.current} / ${shop.required} — ${addMoreText(shop.shortfall)}`;
            wrap.appendChild(row);
        });

        messagesEl.appendChild(wrap);
    }

    function goToCheckout(url) {
        appendAiBubble(TXT.redirecting, []);
        scrollMessages();
        setTimeout(() => { window.location.href = url; }, 2500);
    }

    async function postCart(product, colorCode, choices, qty, buyNow, variantKey) {
        const isDigitalVariant = Array.isArray(product.digital_variations) && product.digital_variations.length > 0;

        const payload = {
            id:       product.id,
            quantity: qty,
            buy_now:  buyNow ? 1 : 0,
        };

        if (isDigitalVariant) {
            // Digital cart path reads only variant_key — no color/choice/composite code.
            payload.variant_key = variantKey || product.digital_variations[0].variant_key;
        } else {
            payload.product_variation_code = buildVariationType(product, colorCode, choices);
            if (colorCode) payload.color = colorCode;
            // Each choice option goes in as {optionName: value} — exactly what CartManager reads.
            Object.keys(choices || {}).forEach(name => { payload[name] = choices[name]; });
        }

        try {
            const res  = await apiFetch(ROUTES.cartAdd, 'POST', payload);
            const data = await res.json().catch(() => ({}));

            // Transport failure → show curated copy, never the framework's raw message.
            if (!res.ok) {
                appendAiBubble(httpErrorMessage(res.status, data), []);
                scrollMessages();
                return;
            }

            // CartManager status: 1 = added, 2 = added but a shipping method is needed at checkout.
            if (data.status === 1 || data.status === 2) {
                notifyCartUpdated();
            }

            if (data.redirect_to_url || (buyNow && data.status === 1)) {
                const url = data.redirect_to_url || ROUTES.checkout;
                setTimeout(() => { window.location.href = url; }, 2500);
                return;
            }
            if (data.status === 1) {
                appendAiBubble(data.message || TXT.addedToCart, []);
                retireProductCard(product.id);
            } else if (data.status === 2) {
                setTimeout(() => { window.location.href = ROUTES.checkout; }, 2500);
            } else {
                appendAiBubble(data.message || TXT.cartFailed, []);
            }
        } catch (_) {
            appendAiBubble(TXT.cartFailed, []);
        }
        scrollMessages();
    }

    function renderHistoryList(sessions) {
        historyListEl.innerHTML = '';
        if (!sessions.length) {
            const empty = document.createElement('div');
            empty.className = 'hexa-ai-history-empty';
            empty.textContent = TXT.noConvos;
            historyListEl.appendChild(empty);
            return;
        }
        sessions.forEach(session => {
            const item = document.createElement('div');
            item.className = 'hexa-ai-history-item';
            item.setAttribute('role', 'listitem');

            const info = document.createElement('div');
            info.className = 'hexa-ai-history-item-info';

            const title = document.createElement('div');
            title.className = 'hexa-ai-history-item-title';
            title.textContent = session.title || TXT.conversation;

            const time = document.createElement('div');
            time.className = 'hexa-ai-history-item-time';
            const msgCount = session.messages_count ? ` · ${Math.floor(session.messages_count / 2)} msg` : '';
            time.textContent = relativeTime(session.last_activity_at) + msgCount;

            info.appendChild(title);
            info.appendChild(time);

            const del = document.createElement('button');
            del.className = 'hexa-ai-history-delete';
            del.setAttribute('aria-label', TXT.delConvo);
            del.type = 'button';
            del.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>`;
            del.addEventListener('click', e => {
                e.stopPropagation();
                confirmDeleteSession(session.id, item);
            });

            item.appendChild(info);
            item.appendChild(del);
            item.addEventListener('click', () => {
                if (item.classList.contains('confirming')) return;
                loadSession(session.id);
            });
            historyListEl.appendChild(item);
        });
    }

    imgBtn.addEventListener('click', () => imgInput.click());
    imgInput.addEventListener('change', () => {
        const file = imgInput.files[0];
        if (!file) return;

        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const typeOk = file.type
            ? ALLOWED_IMAGE_TYPES.includes(file.type)
            : ALLOWED_IMAGE_EXT.includes(ext);
        if (!typeOk || !ALLOWED_IMAGE_EXT.includes(ext)) {
            showInputError(TXT.imageInvalid);
            imgInput.value = '';
            return;
        }
        if (file.size > MAX_IMAGE_BYTES) {
            showInputError(TXT.imageTooLarge);
            imgInput.value = '';
            return;
        }

        pendingImageFile = file;
        const reader = new FileReader();
        reader.onload = e => {
            pendingImageUrl = e.target.result; // base64 for thumbnail preview only
            imgThumb.src = pendingImageUrl;
            imgPreview.hidden = false;
        };
        reader.readAsDataURL(file);
        imgInput.value = '';
    });
    imgRemove.addEventListener('click', clearImagePreview);

    function clearImagePreview() {
        pendingImageUrl  = null;
        pendingImageFile = null;
        imgThumb.src = '';
        imgPreview.hidden = true;
    }

    function resizeInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    function updateCharCount() {
        if (!charCountEl) return;
        const len = input.value.length;
        if (len === 0) {
            charCountEl.hidden = true;
            charCountEl.textContent = '';
            charCountEl.classList.remove('is-limit');
            return;
        }
        charCountEl.hidden = false;
        if (len >= MAX_INPUT_LENGTH) {
            charCountEl.textContent = TXT.inputLimit;
            charCountEl.classList.add('is-limit');
        } else {
            charCountEl.textContent = len + '/' + MAX_INPUT_LENGTH;
            charCountEl.classList.remove('is-limit');
        }
    }
    input.addEventListener('input', resizeInput);
    input.addEventListener('input', updateCharCount);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function scrollMessages() {
        stateConversation.scrollTop = stateConversation.scrollHeight;
    }

    function relativeTime(dateStr) {
        if (!dateStr) return '';
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)        return TXT.timeJustNow;
        if (diff < 3600)      return Math.floor(diff / 60) + ' ' + TXT.timeMinutesAgo;
        if (diff < 86400)     return Math.floor(diff / 3600) + ' ' + TXT.timeHoursAgo;
        if (diff < 604800)    return Math.floor(diff / 86400) + ' ' + TXT.timeDaysAgo;
        return new Date(dateStr).toLocaleDateString();
    }

    async function uploadImage(file) {
        const formData = new FormData();
        formData.append('image', file);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                  || document.querySelector('meta[name="_token"]')?.content || '';
        const res = await fetch(ROUTES.uploadImage, {
            method:      'POST',
            headers:     { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body:        formData,
        });
        if (!res.ok) throw new Error('Image upload failed');
        return res.json();
    }

    async function apiFetch(url, method = 'GET', body = null) {
        const opts = {
            method,
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                             || document.querySelector('meta[name="_token"]')?.content
                             || '',
            },
            credentials: 'same-origin',
        };
        if (method !== 'GET') {
            // Always include guest_id for non-authenticated users so the server can resolve identity
            const guestId = getGuestId();
            const payload = { ...(body || {}), ...(guestId ? { guest_id: guestId } : {}) };
            opts.body = JSON.stringify(payload);
        }
        return fetch(url, opts);
    }

    launcher.addEventListener('click', e => {
        e.stopPropagation();
        openPanel();
    });
    launcher.addEventListener('mouseenter', prefetchSessions);
    launcher.addEventListener('focus', prefetchSessions);
    launcher.addEventListener('touchstart', prefetchSessions, { passive: true });
    closeBtn.addEventListener('click', closePanel);
    sendBtn.addEventListener('click', sendMessage);
    newChatBtn.addEventListener('click', startNewSession);

    panel.addEventListener('click', e => e.stopPropagation());

    document.addEventListener('click', () => {
        if (panel.classList.contains('open')) closePanel();
    });

    historyToggle.addEventListener('click', e => {
        e.stopPropagation();
        openHistoryList();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
    });

})();
