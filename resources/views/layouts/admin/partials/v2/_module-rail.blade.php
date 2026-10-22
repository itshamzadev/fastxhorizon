@php
    use App\Utils\Helpers;

    $v2RailModules = [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'short_label' => 'Home', 'permission' => true],
        ['id' => 'pos', 'label' => 'POS', 'short_label' => 'POS', 'permission' => Helpers::module_permission_check('pos')],
        ['id' => 'orders', 'label' => 'Orders', 'short_label' => 'Orders', 'permission' => Helpers::module_permission_check('orders')],
        ['id' => 'products', 'label' => 'Products', 'short_label' => 'Products', 'permission' => Helpers::module_permission_check('catalog')],
        ['id' => 'catalog', 'label' => 'Catalog', 'short_label' => 'Catalog', 'permission' => Helpers::module_permission_check('catalog')],
        ['id' => 'deals', 'label' => 'Deals', 'short_label' => 'Deals', 'permission' => Helpers::module_permission_check('marketing')],
        ['id' => 'promotions', 'label' => 'Promotions', 'short_label' => 'Promo', 'permission' => Helpers::module_permission_check('marketing')],
        ['id' => 'sellers', 'label' => 'Sellers', 'short_label' => 'Sellers', 'permission' => Helpers::module_permission_check('people')],
        ['id' => 'reseller-management', 'label' => 'Resellers', 'short_label' => 'Resellers', 'permission' => Helpers::module_permission_check('reseller_management')],
        ['id' => 'affiliate-management', 'label' => 'Affiliates', 'short_label' => 'Affiliates', 'permission' => Helpers::module_permission_check('affiliate_management')],
        ['id' => 'customers', 'label' => 'Customers', 'short_label' => 'Customers', 'permission' => Helpers::module_permission_check('people')],
        ['id' => 'marketing', 'label' => 'Marketing', 'short_label' => 'Marketing', 'permission' => Helpers::module_permission_check('marketing')],
        ['id' => 'notifications', 'label' => 'Notifications', 'short_label' => 'Alerts', 'permission' => Helpers::module_permission_check('marketing')],
        ['id' => 'newsletter', 'label' => 'Newsletter', 'short_label' => 'Newsletter', 'permission' => Helpers::module_permission_check('people')],
        ['id' => 'mail', 'label' => 'Mail', 'short_label' => 'Mail', 'permission' => Helpers::module_permission_check('system_settings')],
        ['id' => 'finance', 'label' => 'Finance', 'short_label' => 'Finance', 'permission' => Helpers::module_permission_check('reports')],
        ['id' => 'payouts', 'label' => 'Payouts', 'short_label' => 'Payouts', 'permission' => Helpers::module_permission_check('people')],
        ['id' => 'reports', 'label' => 'Reports & Analytics', 'short_label' => 'Reports', 'permission' => Helpers::module_permission_check('reports')],
        ['id' => 'content', 'label' => 'Content', 'short_label' => 'Content', 'permission' => Helpers::module_permission_check('marketing')],
        ['id' => 'support', 'label' => 'Support', 'short_label' => 'Support', 'permission' => Helpers::module_permission_check('people')],
        ['id' => 'reviews', 'label' => 'Reviews', 'short_label' => 'Reviews', 'permission' => Helpers::module_permission_check('people')],
        ['id' => 'integrations', 'label' => 'Integrations', 'short_label' => 'Integrations', 'permission' => Helpers::module_permission_check('3rd_party_setup')],
        ['id' => 'administration', 'label' => 'Administration', 'short_label' => 'Admin', 'permission' => Helpers::module_permission_check('system_settings') && (int) (auth('admin')->user()->admin_role_id ?? 0) === 1],
        ['id' => 'website-settings', 'label' => 'Website Settings', 'short_label' => 'Website', 'permission' => Helpers::module_permission_check('business_settings') || Helpers::module_permission_check('themes_and_addons') || Helpers::module_permission_check('marketing')],
        ['id' => 'commerce-settings', 'label' => 'Commerce Settings', 'short_label' => 'Commerce', 'permission' => Helpers::module_permission_check('business_settings')],
        ['id' => 'system-settings', 'label' => 'System Settings', 'short_label' => 'System', 'permission' => Helpers::module_permission_check('system_settings') || Helpers::module_permission_check('business_settings')],
    ];

    $v2RailModules = array_values(array_filter($v2RailModules, fn ($module) => $module['permission']));
@endphp

<aside class="v2-rail" aria-label="Admin departments">
    <div class="v2-rail-items">
        @foreach ($v2RailModules as $module)
            @php
                $icon = match ($module['id']) {
                    'dashboard' => '<path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-8.5Z"/>',
                    'pos' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h2M14 15h2M8 18h8"/>',
                    'orders' => '<path d="M6 8h12l-1 11.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
                    'products' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
                    'catalog' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
                    'deals' => '<path d="M20 12 12 4 4 12l8 8 8-8Z"/><path d="M12 8h.01M16 12h.01"/>',
                    'promotions', 'marketing' => '<path d="M3 13V9l13-5v16L3 15Z"/><path d="M16 9v6M7 15v2a2 2 0 0 0 4 0v-1"/>',
                    'sellers' => '<path d="M4 10h16l-1 10H5L4 10Z"/><path d="M6 10V7a6 6 0 0 1 12 0v3M3 10h18"/>',
                    'reseller-management' => '<path d="M4 10h16l-1 10H5L4 10Z"/><path d="M6 10V7a6 6 0 0 1 12 0v3M3 10h18"/><path d="M8 14h8"/>',
                    'affiliate-management' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 2.5-5 6-5s6 2 6 5M16 11l2 2 4-4"/>',
                    'customers' => '<circle cx="9" cy="9" r="3"/><path d="M3 20c0-3 2.5-5 6-5s6 2 6 5"/><circle cx="17" cy="10" r="2.5"/><path d="M15 20c.4-2.2 2-3.5 4-3.5s3.6 1.3 4 3.5"/>',
                    'newsletter' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
                    'mail' => '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>',
                    'notifications' => '<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/>',
                    'finance' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
                    'payouts' => '<path d="M4 7h16v10H4z"/><path d="M8 11h8M12 8v2M12 14v2"/><path d="M6 4h12"/>',
                    'reports' => '<path d="M5 20V11M11 20V5M17 20v-6"/><path d="M3 20h18"/>',
                    'content' => '<path d="M5 3h10l4 4v14H5z"/><path d="M15 3v5h5M8 12h8M8 16h6"/>',
                    'support' => '<path d="M4 13a8 8 0 0 1 16 0v3a2 2 0 0 1-2 2h-2v-6h4"/><path d="M4 12v6H3a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h1M9 21h6"/><path d="M15 18c0 2-1.3 3-3 3"/>',
                    'reviews' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
                    'integrations' => '<path d="M8 12h8M12 8v8"/><path d="M7 3v3M17 3v3M7 18v3M17 18v3"/><rect x="3" y="6" width="18" height="12" rx="2"/>',
                    'administration' => '<path d="M12 3 20 6v5c0 5-3.2 8.5-8 10-4.8-1.5-8-5-8-10V6l8-3Z"/><circle cx="12" cy="10" r="2.2"/><path d="M8.5 16c.7-2 2-3 3.5-3s2.8 1 3.5 3"/>',
                    'website-settings' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4M3 8h18"/>',
                    'commerce-settings' => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="11" cy="18" r="2" fill="currentColor" stroke="none"/>',
                    'system-settings' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="4"/>',
                    default => '<path d="M4 7h16M4 12h16M4 17h16"/><circle cx="9" cy="7" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="7" cy="17" r="2" fill="currentColor" stroke="none"/>',
                };
            @endphp
            <button class="v2-rail-btn" type="button" data-section="{{ $module['id'] }}"
                    data-label="{{ $module['label'] }}" aria-label="{{ $module['label'] }}"
                    title="{{ $module['label'] }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    {!! $icon !!}
                </svg>
                <span class="v2-rail-label">{{ $module['short_label'] }}</span>
            </button>
        @endforeach
    </div>

    @php
        $v2SetupRail = function_exists('checkSetupGuideRequirements') ? checkSetupGuideRequirements(panel: 'admin') : ['completePercent' => 100];
    @endphp
    @if (($v2SetupRail['completePercent'] ?? 100) < 100 && auth('admin')->user()->admin_role_id == 1)
        <div class="v2-rail-foot">
            <button type="button" class="v2-rail-setup" data-v2-setup-trigger
                    data-label="{{ translate('Setup_Guide') }} · {{ $v2SetupRail['completePercent'] ?? 0 }}%"
                    aria-label="{{ translate('Setup_Guide') }}">
                <img src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/setup_guide.png') }}" alt="">
                <span class="v2-rail-setup-badge">{{ $v2SetupRail['completePercent'] ?? 0 }}%</span>
            </button>
        </div>
    @endif
</aside>
