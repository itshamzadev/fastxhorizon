@php($policyLinks = [
    ['label' => 'About Us', 'slug' => 'about-us'],
    ['label' => 'Terms', 'slug' => 'terms-and-conditions'],
    ['label' => 'Privacy', 'slug' => 'privacy-policy'],
    ['label' => 'Refunds', 'slug' => 'refund-policy'],
    ['label' => 'Returns', 'slug' => 'return-policy'],
    ['label' => 'Cancellation', 'slug' => 'cancellation-policy'],
])
<nav class="fxh-policy-nav" aria-label="Company and policy pages">
    @foreach($policyLinks as $link)
        <a href="{{ route('business-page.view', ['slug' => $link['slug']]) }}" class="{{ request('slug') === $link['slug'] ? 'is-active' : '' }}">{{ $link['label'] }}</a>
    @endforeach
</nav>
