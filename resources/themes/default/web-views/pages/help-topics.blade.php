@extends('layouts.front-end.app')

@section('title', translate('FAQ'))

@section('content')
<main class="fxh-help-center rtl" data-fxh-faq>
    <section class="fxh-help-center__hero">
        <div class="container text-center">
            <span class="text-uppercase small fw-bold">{{ translate('fast_x_horizon_help_center') }}</span>
            <h1>{{ translate('how_can_we_help') }}</h1>
            <p>{{ translate('find_answers_about_shopping_orders_sellers_refunds_payments_and_accounts') }}</p>
            <div class="fxh-help-center__search mx-auto">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="search" id="fxh-faq-search" class="form-control" placeholder="{{ translate('search_for_an_answer') }}" aria-label="{{ translate('search_for_an_answer') }}">
            </div>
        </div>
    </section>
    <section class="container py-5">
        @if(count($helps) > 0)
            <div class="row g-4">
                <aside class="col-lg-3"><div class="fxh-help-center__categories">
                    <h2>{{ translate('help_topics') }}</h2>
                    <p class="small text-muted">{{ translate('browse_common_questions') }}</p>
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button" class="fxh-faq-filter active" data-filter="all">{{ translate('all') }}</button>
                        @foreach(collect($helps)->pluck('type')->filter()->unique() as $type)
                            <button type="button" class="fxh-faq-filter" data-filter="{{ Str::slug($type) }}">{{ $type }}</button>
                        @endforeach
                    </div>
                </div></aside>
                <div class="col-lg-9"><div class="fxh-faq-list">
                    @foreach($helps as $help)
                        <article class="fxh-faq-item" data-faq-item data-category="{{ Str::slug($help->type ?? 'general') }}" data-search="{{ strtolower($help->question . ' ' . strip_tags($help->answer)) }}">
                            <button type="button" class="fxh-faq-question" data-toggle="collapse" data-target="#fxh-faq-{{ $help->id }}" aria-expanded="false" aria-controls="fxh-faq-{{ $help->id }}">
                                <span>{{ $help->question }}</span><i class="fa fa-plus" aria-hidden="true"></i>
                            </button>
                            <div id="fxh-faq-{{ $help->id }}" class="collapse"><div class="fxh-faq-answer">{!! $help->answer !!}</div></div>
                        </article>
                    @endforeach
                </div>
                <div class="fxh-faq-empty d-none text-center py-5" data-faq-empty><i class="fa fa-search mb-3" aria-hidden="true"></i><h3>{{ translate('no_results_found') }}</h3><p class="text-muted">{{ translate('try_different_keywords') }}</p></div></div>
            </div>
        @else
            <div class="fxh-faq-empty text-center py-5"><i class="fa fa-comments-o mb-3" aria-hidden="true"></i><h3>{{ translate('your_help_center_is_ready') }}</h3><p class="text-muted">{{ translate('faq_content_will_appear_here') }}</p></div>
        @endif
    </section>
</main>
@endsection

@push('script')
<script>
(function () {
    const root = document.querySelector('[data-fxh-faq]');
    if (!root) return;
    const input = root.querySelector('#fxh-faq-search');
    const items = Array.from(root.querySelectorAll('[data-faq-item]'));
    const empty = root.querySelector('[data-faq-empty']);
    let category = 'all';
    function filter() {
        const term = (input.value || '').trim().toLowerCase();
        let visible = 0;
        items.forEach(item => {
            const matches = (!term || item.dataset.search.includes(term)) && (category === 'all' || item.dataset.category === category);
            item.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        if (empty) empty.classList.toggle('d-none', visible !== 0);
    }
    input?.addEventListener('input', filter);
    root.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
        category = button.dataset.filter;
        root.querySelectorAll('[data-filter]').forEach(item => item.classList.toggle('active', item === button));
        filter();
    }));
})();
</script>
@endpush

