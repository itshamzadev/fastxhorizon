@php($companyWhatsappDigits = preg_replace('/\D+/', '', (string) ($companyContact['phone'] ?? '923265680129')))
<section class="fxh-policy-contact container">
    <div><span class="fxh-eyebrow">Need help?</span><h2>We are here to make things clear.</h2></div>
    <div class="fxh-policy-contact__details"><a href="mailto:{{ $companyContact['email'] ?? 'info@fastxhorizon.com' }}">{{ $companyContact['email'] ?? 'info@fastxhorizon.com' }}</a><a href="tel:+{{ $companyWhatsappDigits }}">{{ $companyContact['phone'] ?? '+92 326 5680129' }}</a><a href="https://wa.me/{{ $companyWhatsappDigits }}" target="_blank" rel="noopener noreferrer">WhatsApp: {{ $companyContact['phone'] ?? '+92 326 5680129' }}</a><span>www.fastxhorizon.com</span></div>
</section>
