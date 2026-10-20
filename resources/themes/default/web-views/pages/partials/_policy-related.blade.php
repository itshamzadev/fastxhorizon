@php
    $related = collect([
        ['slug' => 'terms-and-conditions', 'label' => 'Terms', 'text' => 'The marketplace agreement.'],
        ['slug' => 'privacy-policy', 'label' => 'Privacy', 'text' => 'How information is handled.'],
        ['slug' => 'refund-policy', 'label' => 'Refunds', 'text' => 'Resolution and wallet credits.'],
        ['slug' => 'return-policy', 'label' => 'Returns', 'text' => 'Eligibility and next steps.'],
        ['slug' => 'cancellation-policy', 'label' => 'Cancellation', 'text' => 'Order timing and control.'],
    ])->reject(fn($item) => $item['slug'] === $businessPage->slug)->take(3);
@endphp
<section class="container fxh-policy-related">
    <div><span class="fxh-eyebrow">Keep exploring</span><h2>Related policies</h2></div>
    <div class="fxh-policy-related__grid">@foreach($related as $item)<a href="{{ route('business-page.view', ['slug' => $item['slug']]) }}"><strong>{{ $item['label'] }}</strong><span>{{ $item['text'] }}</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>@endforeach</div>
</section>
