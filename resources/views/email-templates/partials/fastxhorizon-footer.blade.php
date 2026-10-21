@php
    $companyLogo = getWebConfig(name: 'company_web_logo');
    $logoUrl = getStorageImages(path: $companyLogo, type: 'backend-logo');
    $legalName = config('fastxhorizon.settings.legal_company_name', 'FASTXHORIZON (PRIVATE) LIMITED');
    $companyEmail = config('fastxhorizon.settings.company_public_email', 'info@fastxhorizon.com');
    $websiteUrl = 'https://fastxhorizon.com';
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;background:#ffffff;">
    <tr>
        <td align="center" style="padding:28px 16px 8px;text-align:center;border-top:1px solid #e8edf3;">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" width="180" alt="FastXHorizon" style="display:block;width:180px;max-width:70%;height:auto;margin:0 auto 18px;border:0;outline:none;text-decoration:none;">
            @endif
            <div style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;font-weight:700;color:#182e4b;">
                {{ $legalName }}
            </div>
            <div style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#5d6774;">
                E-Commerce Marketplace
            </div>
            <div style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#5d6774;">
                <a href="{{ $websiteUrl }}" style="color:#1455ac;text-decoration:none;">fastxhorizon.com</a>
                <span style="color:#9aa5b1;">&nbsp;|&nbsp;</span>
                <a href="mailto:{{ $companyEmail }}" style="color:#1455ac;text-decoration:none;">{{ $companyEmail }}</a>
            </div>
        </td>
    </tr>
</table>
