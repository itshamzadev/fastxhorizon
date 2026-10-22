@php
    use App\Utils\Helpers;

    $v2Auction = function_exists('getCheckAddonPublishedStatus') && getCheckAddonPublishedStatus(moduleName: 'Auction');
    $v2Tax = function_exists('getCheckAddonPublishedStatus') && getCheckAddonPublishedStatus(moduleName: 'TaxModule');
    $v2AuctionRoutes = $v2Auction ? include(base_path('Modules/Auction/Addon/admin_routes.php')) : [];
    $v2AuctionReportRoutes = $v2Auction ? include(base_path('Modules/Auction/Addon/auction_report_routes.php')) : [];
    $v2TaxReportRoutes = $v2Tax ? include(base_path('Modules/TaxModule/Addon/tax_report_routes.php')) : [];
    $v2ThemeRoutes = config('get_theme_routes') ?: [];
    $v2AddonAdminRoutes = config('addon_admin_routes') ?: [];

    $v2Patterns = function (array $patterns, array $query = [], array $excludeQuery = []): bool {
        if (!collect($patterns)->contains(fn ($pattern) => request()->is($pattern))) {
            return false;
        }
        foreach ($query as $key => $value) {
            if ((string) request()->query($key) !== (string) $value) {
                return false;
            }
        }
        foreach ($excludeQuery as $key => $value) {
            if ((string) request()->query($key) === (string) $value) {
                return false;
            }
        }
        return true;
    };
    $v2ItemAvailable = function (array $item): bool {
        if (!empty($item['module_permission']) && !Helpers::module_permission_check($item['module_permission'])) {
            return false;
        }
        if (!empty($item['addon']) && (!function_exists('getCheckAddonPublishedStatus') || !getCheckAddonPublishedStatus(moduleName: $item['addon']))) {
            return false;
        }
        if (!empty($item['super_admin']) && (int) (auth('admin')->user()->admin_role_id ?? 0) !== 1) {
            return false;
        }
        if (!empty($item['route']) && Route::has($item['route'])) {
            return true;
        }
        foreach (($item['children'] ?? []) as $child) {
            if (!empty($child['module_permission']) && !Helpers::module_permission_check($child['module_permission'])) {
                continue;
            }
            if (!empty($child['addon']) && (!function_exists('getCheckAddonPublishedStatus') || !getCheckAddonPublishedStatus(moduleName: $child['addon']))) {
                continue;
            }
            if (!empty($child['route']) && Route::has($child['route'])) {
                return true;
            }
        }
        return false;
    };

    $v2OrderChildren = [
        ['label' => 'All Orders', 'route' => 'admin.orders.list', 'params' => ['all'], 'patterns' => ['admin/orders/list/all'], 'badge' => $sidebarTotalOrders ?? 0],
        ['label' => 'Pending', 'route' => 'admin.orders.list', 'params' => ['pending'], 'patterns' => ['admin/orders/list/pending'], 'badge' => $sidebarOrderCounts['pending'] ?? 0],
        ['label' => 'Confirmed', 'route' => 'admin.orders.list', 'params' => ['confirmed'], 'patterns' => ['admin/orders/list/confirmed'], 'badge' => $sidebarOrderCounts['confirmed'] ?? 0],
        ['label' => 'Packaging / Processing', 'route' => 'admin.orders.list', 'params' => ['processing'], 'patterns' => ['admin/orders/list/processing'], 'badge' => $sidebarOrderCounts['processing'] ?? 0],
        ['label' => 'Out for Delivery', 'route' => 'admin.orders.list', 'params' => ['out_for_delivery'], 'patterns' => ['admin/orders/list/out_for_delivery'], 'badge' => $sidebarOrderCounts['out_for_delivery'] ?? 0],
        ['label' => 'Delivered', 'route' => 'admin.orders.list', 'params' => ['delivered'], 'patterns' => ['admin/orders/list/delivered'], 'badge' => $sidebarOrderCounts['delivered'] ?? 0],
        ['label' => 'Returned', 'route' => 'admin.orders.list', 'params' => ['returned'], 'patterns' => ['admin/orders/list/returned'], 'badge' => $sidebarOrderCounts['returned'] ?? 0],
        ['label' => 'Failed', 'route' => 'admin.orders.list', 'params' => ['failed'], 'patterns' => ['admin/orders/list/failed'], 'badge' => $sidebarOrderCounts['failed'] ?? 0],
        ['label' => 'Cancelled', 'route' => 'admin.orders.list', 'params' => ['canceled'], 'patterns' => ['admin/orders/list/canceled'], 'badge' => $sidebarOrderCounts['canceled'] ?? 0],
    ];

    $v2RefundChildren = [
        ['label' => 'Pending Refunds', 'route' => 'admin.refund-section.refund.list', 'params' => ['pending'], 'patterns' => ['admin/refund-section/refund/list/pending'], 'badge' => $sidebarRefundCounts['pending'] ?? 0],
        ['label' => 'Approved Refunds', 'route' => 'admin.refund-section.refund.list', 'params' => ['approved'], 'patterns' => ['admin/refund-section/refund/list/approved'], 'badge' => $sidebarRefundCounts['approved'] ?? 0],
        ['label' => 'Refunded', 'route' => 'admin.refund-section.refund.list', 'params' => ['refunded'], 'patterns' => ['admin/refund-section/refund/list/refunded'], 'badge' => $sidebarRefundCounts['refunded'] ?? 0],
        ['label' => 'Rejected Refunds', 'route' => 'admin.refund-section.refund.list', 'params' => ['rejected'], 'patterns' => ['admin/refund-section/refund/list/rejected'], 'badge' => $sidebarRefundCounts['rejected'] ?? 0],
    ];

    $v2NavModules = [
        [
            'id' => 'dashboard', 'title' => 'Dashboard', 'permission' => true,
            'groups' => [
                ['label' => 'Overview', 'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.dashboard.index', 'patterns' => ['admin/dashboard*']],
                ]],
            ],
        ],
        [
            'id' => 'pos', 'title' => 'POS', 'permission' => Helpers::module_permission_check('pos'),
            'groups' => [
                ['label' => 'Point of Sale', 'items' => [
                    ['label' => 'POS', 'route' => 'admin.pos.index', 'patterns' => ['admin/pos*']],
                ]],
            ],
        ],
        [
            'id' => 'orders', 'title' => 'Orders', 'permission' => Helpers::module_permission_check('orders'),
            'groups' => [
                ['label' => 'Orders', 'items' => [
                    ['label' => 'All Orders', 'route' => 'admin.orders.list', 'params' => ['all'], 'patterns' => ['admin/orders/list*'], 'children' => $v2OrderChildren],
                ]],
                ['label' => 'After Sales', 'items' => [
                    ['label' => 'Refund Requests', 'route' => 'admin.refund-section.refund.list', 'params' => ['pending'], 'patterns' => ['admin/refund-section/refund*'], 'children' => $v2RefundChildren],
                ]],
            ],
        ],
        [
            'id' => 'products', 'title' => 'Products', 'permission' => Helpers::module_permission_check('catalog'),
            'groups' => [
                ['label' => 'Product Management', 'items' => [
                    ['label' => 'All Products', 'route' => 'admin.products.list', 'params' => ['all'], 'patterns' => ['admin/products/list/all*', 'admin/products/view*', 'admin/products/update*']],
                    ['label' => 'Add New Product', 'route' => 'admin.products.add', 'patterns' => ['admin/products/add']],
                    ['label' => 'Seller Products', 'route' => 'admin.products.list', 'params' => ['vendor'], 'patterns' => ['admin/products/list/vendor*'], 'exclude_query' => ['request_status' => '0']],
                    ['label' => 'Seller Product Requests', 'route' => 'admin.products.list', 'params' => ['vendor', 'request_status' => '0'], 'query' => ['request_status' => '0'], 'patterns' => ['admin/products/list/vendor*']],
                    ['label' => 'Product Update Requests', 'route' => 'admin.products.updated-product-list', 'params' => ['type' => 'vendor'], 'patterns' => ['admin/products/updated-product-list*']],
                    ['label' => 'Bulk Import', 'route' => 'admin.products.bulk-import', 'patterns' => ['admin/products/bulk-import']],
                ]],
                ['label' => 'Inventory', 'items' => [
                    ['label' => 'Limited Stock', 'route' => 'admin.products.stock-limit-list', 'params' => ['in_house'], 'patterns' => ['admin/products/stock-limit-list*']],
                    ['label' => 'Restock Requests', 'route' => 'admin.products.request-restock-list', 'patterns' => ['admin/products/request-restock-list*']],
                ]],
            ],
        ],
        [
            'id' => 'catalog', 'title' => 'Catalog', 'permission' => Helpers::module_permission_check('catalog'),
            'groups' => [
                ['label' => 'Taxonomy', 'items' => [
                    ['label' => 'Categories', 'route' => 'admin.category.view', 'patterns' => ['admin/category*']],
                    ['label' => 'Subcategories', 'route' => 'admin.sub-category.view', 'patterns' => ['admin/sub-category*']],
                    ['label' => 'Sub-subcategories', 'route' => 'admin.sub-sub-category.view', 'patterns' => ['admin/sub-sub-category*']],
                    ['label' => 'Brands', 'route' => 'admin.brand.list', 'patterns' => ['admin/brand*']],
                    ['label' => 'Attributes', 'route' => 'admin.attribute.view', 'patterns' => ['admin/attribute*']],
                    ['label' => 'Product Gallery', 'route' => 'admin.products.product-gallery', 'patterns' => ['admin/products/product-gallery*']],
                ]],
                ['label' => 'Homepage Taxonomy', 'items' => [
                    // Contextual shortcut to the canonical Categories page. It must not
                    // compete with the primary taxonomy link for active-row styling.
                    ['label' => 'Homepage Categories', 'route' => 'admin.category.view', 'patterns' => ['admin/category*'], 'no_active' => true],
                ]],
            ],
        ],
        [
            'id' => 'deals', 'title' => 'Deals', 'permission' => Helpers::module_permission_check('marketing'),
            'groups' => [
                ['label' => 'Fast X Horizon Deals', 'items' => [
                    ['label' => 'All Deals', 'route' => 'admin.deal.flash', 'patterns' => ['admin/deal/flash', 'admin/deal/update*']],
                    ['label' => 'Create Deal', 'route' => 'admin.deal.flash-add', 'patterns' => ['admin/deal/flash/add']],
                ]],
            ],
        ],
        [
            'id' => 'promotions', 'title' => 'Promotions', 'permission' => Helpers::module_permission_check('marketing'),
            'groups' => [
                ['label' => 'Product Promotion', 'items' => [
                    ['label' => 'Featured Products', 'route' => 'admin.products.list', 'params' => ['in-house'], 'patterns' => ['admin/products/list/in-house*']],
                    ['label' => 'Coupons', 'route' => 'admin.coupon.add', 'patterns' => ['admin/coupon*']],
                    ['label' => 'Deal of the Day', 'route' => 'admin.deal.day', 'patterns' => ['admin/deal/day*']],
                    ['label' => 'Featured Deals', 'route' => 'admin.deal.feature', 'patterns' => ['admin/deal/feature*']],
                    ['label' => 'Clearance Sale', 'route' => 'admin.deal.clearance-sale.index', 'patterns' => ['admin/deal/clearance-sale*']],
                ]],
            ],
        ],
        [
            'id' => 'sellers', 'title' => 'Sellers', 'permission' => Helpers::module_permission_check('people'),
            'groups' => [
                ['label' => 'Seller Management', 'items' => [
                    ['label' => 'All Sellers', 'route' => 'admin.vendors.vendor-list', 'patterns' => ['admin/vendors/list', 'admin/vendors/view*']],
                    ['label' => 'Add Seller', 'route' => 'admin.vendors.add', 'patterns' => ['admin/vendors/add']],
                ]],
                ['label' => 'Seller Operations', 'items' => [
                    ['label' => 'Compliance Records', 'route' => 'admin.seller-compliance.index', 'patterns' => ['admin/seller-compliance*']],
                    ['label' => 'Delivery Men', 'route' => 'admin.delivery-man.list', 'patterns' => ['admin/delivery-man/list', 'admin/delivery-man/update*']],
                    ['label' => 'Add Delivery Man', 'route' => 'admin.delivery-man.add', 'patterns' => ['admin/delivery-man/add']],
                    ['label' => 'Delivery Withdrawals', 'route' => 'admin.delivery-man.withdraw-list', 'patterns' => ['admin/delivery-man/withdraw-list*', 'admin/delivery-man/withdraw-view*']],
                    ['label' => 'Emergency Contacts', 'route' => 'admin.delivery-man.emergency-contact.index', 'patterns' => ['admin/delivery-man/emergency-contact*']],
                ]],
            ],
        ],
        [
            'id' => 'customers', 'title' => 'Customers', 'permission' => Helpers::module_permission_check('people'),
            'groups' => [
                ['label' => 'Customers', 'items' => [
                    ['label' => 'All Customers', 'route' => 'admin.customer.list', 'patterns' => ['admin/customer/list', 'admin/customer/view*']],
                ]],
                ['label' => 'Customer Activity', 'items' => [
                    ['label' => 'Wallet Withdrawals', 'route' => 'admin.customer.wallet-withdrawals.index', 'patterns' => ['admin/customer/wallet-withdrawals*']],
                    ['label' => 'Wallet', 'route' => 'admin.customer.wallet.report', 'patterns' => ['admin/customer/wallet/report']],
                    ['label' => 'Wallet Bonus Setup', 'route' => 'admin.customer.wallet.bonus-setup', 'patterns' => ['admin/customer/wallet/bonus-setup*']],
                    ['label' => 'Loyalty Points', 'route' => 'admin.customer.loyalty.report', 'patterns' => ['admin/customer/loyalty/report']],
                ]],
            ],
        ],
        [
            'id' => 'marketing', 'title' => 'Marketing', 'permission' => Helpers::module_permission_check('marketing'),
            'groups' => [
                ['label' => 'Campaigns & Engagement', 'items' => [
                    ['label' => 'Banners & Popup Campaigns', 'route' => 'admin.banner.list', 'patterns' => ['admin/banner*']],
                    ['label' => 'Announcements', 'route' => 'admin.business-settings.announcement', 'patterns' => ['admin/business-settings/announcement*']],
                    ['label' => 'Marketing Tools', 'route' => 'admin.third-party.analytics-index', 'patterns' => ['admin/third-party/analytics-index*']],
                ]],
            ],
        ],
        [
            'id' => 'notifications', 'title' => 'Notifications', 'permission' => Helpers::module_permission_check('marketing'),
            'groups' => [
                ['label' => 'Push & Messaging', 'items' => [
                    ['label' => 'Customer Notifications', 'route' => 'admin.notification.index', 'patterns' => ['admin/notification*']],
                    ['label' => 'Push Notifications', 'route' => 'admin.push-notification.index', 'patterns' => ['admin/push-notification*']],
                ]],
            ],
        ],
        [
            'id' => 'newsletter', 'title' => 'Newsletter', 'permission' => Helpers::module_permission_check('people'),
            'groups' => [
                ['label' => 'Subscribers', 'items' => [
                    ['label' => 'Customer Subscribers', 'route' => 'admin.customer.subscriber-list', 'patterns' => ['admin/customer/subscriber-list*']],
                ]],
            ],
        ],
        [
            'id' => 'mail', 'title' => 'Mail', 'permission' => Helpers::module_permission_check('system_settings'),
            'groups' => [
                ['label' => 'Mail Center', 'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.mail.dashboard', 'patterns' => ['admin/mail/dashboard*']],
                    ['label' => 'Email Templates', 'route' => 'admin.mail.templates', 'patterns' => ['admin/mail/templates*']],
                    ['label' => 'Campaigns', 'route' => 'admin.mail.campaigns', 'patterns' => ['admin/mail/campaigns*']],
                    ['label' => 'Newsletter Subscribers', 'route' => 'admin.mail.subscribers', 'patterns' => ['admin/mail/subscribers*']],
                    ['label' => 'Delivery Logs', 'route' => 'admin.mail.logs', 'patterns' => ['admin/mail/logs*']],
                    ['label' => 'Mail Queue', 'route' => 'admin.mail.queue', 'patterns' => ['admin/mail/queue*']],
                    ['label' => 'Settings', 'route' => 'admin.mail.settings', 'patterns' => ['admin/mail/settings*']],
                ]],
            ],
        ],
        [
            'id' => 'finance', 'title' => 'Finance', 'permission' => Helpers::module_permission_check('reports'),
            'groups' => [
                ['label' => 'Transactions', 'items' => [
                    ['label' => 'Order Transactions', 'route' => 'admin.transaction.order-transaction-list', 'patterns' => ['admin/transaction/order-transaction-list*']],
                    ['label' => 'Expense Transactions', 'route' => 'admin.transaction.expense-transaction-list', 'patterns' => ['admin/transaction/expense-transaction-list*']],
                    ['label' => 'Refund Transactions', 'route' => 'admin.report.transaction.refund-transaction-list', 'patterns' => ['admin/report/transaction/refund-transaction-list*']],
                    ['label' => 'Wallet Bonus Transactions', 'route' => 'admin.transaction.wallet-bonus', 'patterns' => ['admin/transaction/wallet-bonus*']],
                ]],
            ],
        ],
        [
            'id' => 'payouts', 'title' => 'Payouts', 'permission' => Helpers::module_permission_check('people'),
            'groups' => [
                ['label' => 'Seller Payouts', 'items' => [
                    ['label' => 'Withdrawal Requests', 'route' => 'admin.vendors.withdraw_list', 'patterns' => ['admin/vendors/withdraw-list*', 'admin/vendors/withdraw-view*']],
                    ['label' => 'Seller Earnings', 'route' => 'admin.report.vendor-earning', 'patterns' => ['admin/report/vendor-earning*']],
                ]],
            ],
        ],
        [
            'id' => 'reports', 'title' => 'Reports & Analytics', 'permission' => Helpers::module_permission_check('reports'),
            'groups' => [
                ['label' => 'Sales Reports', 'items' => [
                    ['label' => 'Order Report', 'route' => 'admin.report.order', 'patterns' => ['admin/report/order*']],
                    ['label' => 'Admin Earnings', 'route' => 'admin.report.admin-earning', 'patterns' => ['admin/report/admin-earning*']],
                    ['label' => 'In-house Product Sales', 'route' => 'admin.report.inhouse-product-sale', 'patterns' => ['admin/report/inhouse-product-sale*']],
                ]],
                ['label' => 'Product & Seller Reports', 'items' => [
                    ['label' => 'Product Report', 'route' => 'admin.report.all-product', 'patterns' => ['admin/report/all-product*']],
                    ['label' => 'Stock Report', 'route' => 'admin.stock.product-stock', 'patterns' => ['admin/stock/product-stock*']],
                    ['label' => 'Wishlist Product Report', 'route' => 'admin.stock.product-in-wishlist', 'patterns' => ['admin/stock/product-in-wishlist*']],
                    ['label' => 'Seller Report', 'route' => 'admin.report.vendor-report', 'patterns' => ['admin/report/vendor-report*']],
                ]],
            ],
        ],
        [
            'id' => 'content', 'title' => 'Content', 'permission' => Helpers::module_permission_check('marketing'),
            'groups' => [
                ['label' => 'Website Content', 'items' => [
                    ['label' => 'Pages & Media', 'route' => 'admin.pages-and-media.list', 'patterns' => ['admin/pages-and-media/list', 'admin/pages-and-media/page*', 'admin/pages-and-media/about-us', 'admin/pages-and-media/privacy-policy']],
                    ['label' => 'Company Leadership', 'route' => 'admin.pages-and-media.leadership.index', 'patterns' => ['admin/pages-and-media/leadership*']],
                ]],
                ['label' => 'Blog', 'items' => [
                    ['label' => 'Blog Posts', 'route' => 'admin.blog.view', 'patterns' => ['admin/blog/view', 'admin/blog/edit', 'admin/blog/draft-edit*']],
                    ['label' => 'Add Blog Post', 'route' => 'admin.blog.add', 'patterns' => ['admin/blog/add']],
                ]],
            ],
        ],
        [
            'id' => 'support', 'title' => 'Support', 'permission' => Helpers::module_permission_check('people'),
            'groups' => [
                ['label' => 'Customer Support', 'items' => [
                    ['label' => 'Inbox', 'route' => 'admin.messages.index', 'params' => ['type' => 'customer'], 'patterns' => ['admin/messages*']],
                    ['label' => 'Support Tickets', 'route' => 'admin.support-ticket.view', 'patterns' => ['admin/support-ticket*']],
                    ['label' => 'Contact Messages', 'route' => 'admin.contact.list', 'patterns' => ['admin/contact*']],
                ]],
            ],
        ],
        [
            'id' => 'reviews', 'title' => 'Reviews', 'permission' => Helpers::module_permission_check('people'),
            'groups' => [
                ['label' => 'Reviews', 'items' => [
                    ['label' => 'Product Reviews', 'route' => 'admin.reviews.list', 'patterns' => ['admin/reviews*']],
                ]],
            ],
        ],
        [
            'id' => 'integrations', 'title' => 'Integrations', 'permission' => Helpers::module_permission_check('3rd_party_setup'),
            'groups' => [
                ['label' => 'Communication', 'items' => [
                    ['label' => 'Email / SMTP', 'route' => 'admin.third-party.mail.index', 'patterns' => ['admin/third-party/mail*']],
                    ['label' => 'SMS Configuration', 'route' => 'admin.third-party.sms-module', 'patterns' => ['admin/third-party/sms-module*']],
                ]],
                ['label' => 'Authentication', 'items' => [
                    ['label' => 'Social Login', 'route' => 'admin.third-party.social-login.view', 'patterns' => ['admin/third-party/social-login*']],
                ]],
                ['label' => 'Maps & Business', 'items' => [
                    ['label' => 'Map API', 'route' => 'admin.third-party.map-api', 'patterns' => ['admin/third-party/map-api*']],
                    ['label' => 'ERP Integration', 'route' => 'admin.third-party.erp.index', 'patterns' => ['admin/third-party/erp*']],
                    ['label' => 'Courier Integration', 'route' => 'admin.courier.config.index', 'patterns' => ['admin/courier*']],
                ]],
                ['label' => 'AI', 'items' => [
                    ['label' => 'AI Setup', 'route' => 'admin.third-party.ai-setting.index', 'addon' => 'AI', 'patterns' => ['admin/third-party/ai-setting*']],
                ]],
                ['label' => 'Payment Integrations', 'items' => [
                    ['label' => 'Payment Gateways', 'route' => 'admin.third-party.payment-method.index', 'patterns' => ['admin/third-party/payment-method*', 'admin/third-party/offline-payment-method*']],
                    ['label' => 'Withdrawal Methods', 'route' => 'admin.third-party.withdraw-method.list', 'patterns' => ['admin/third-party/withdraw-method*']],
                ]],
            ],
        ],
        [
            'id' => 'administration', 'title' => 'Administration', 'permission' => Helpers::module_permission_check('system_settings') && (int) (auth('admin')->user()->admin_role_id ?? 0) === 1,
            'groups' => [
                ['label' => 'Team', 'items' => [
                    ['label' => 'Admin Employees', 'route' => 'admin.employee.list', 'patterns' => ['admin/employee/list', 'admin/employee/view*', 'admin/employee/update*'], 'super_admin' => true],
                    ['label' => 'Add Employee', 'route' => 'admin.employee.add-new', 'patterns' => ['admin/employee/add*'], 'super_admin' => true],
                ]],
                ['label' => 'Access Control', 'items' => [
                    ['label' => 'Roles & Permissions', 'route' => 'admin.custom-role.create', 'patterns' => ['admin/custom-role*'], 'super_admin' => true],
                ]],
            ],
        ],
        [
            'id' => 'website-settings', 'title' => 'Website Settings', 'permission' => Helpers::module_permission_check('business_settings') || Helpers::module_permission_check('themes_and_addons') || Helpers::module_permission_check('marketing'),
            'groups' => [
                ['label' => 'Branding & Appearance', 'items' => [
                    ['label' => 'Website Setup', 'route' => 'admin.business-settings.website-setup', 'patterns' => ['admin/business-settings/website-setup*']],
                    ['label' => 'Theme Settings', 'route' => 'admin.system-setup.theme.setup', 'module_permission' => 'themes_and_addons', 'patterns' => ['admin/system-setup/theme/setup*']],
                    ['label' => 'Social Media Links', 'route' => 'admin.pages-and-media.social-media', 'patterns' => ['admin/pages-and-media/social-media*']],
                ]],
                ['label' => 'Homepage & SEO', 'items' => [
                    ['label' => 'Homepage Section Settings', 'route' => 'admin.business-settings.priority-setup.index', 'patterns' => ['admin/business-settings/priority-setup*']],
                    ['label' => 'SEO Settings', 'route' => 'admin.seo-settings.web-master-tool', 'patterns' => ['admin/seo-settings*']],
                ]],
            ],
        ],
        [
            'id' => 'commerce-settings', 'title' => 'Commerce Settings', 'permission' => Helpers::module_permission_check('business_settings'),
            'groups' => [
                ['label' => 'Business Operations', 'items' => [
                    ['label' => 'Product Settings', 'route' => 'admin.business-settings.product-settings.index', 'patterns' => ['admin/business-settings/product-settings*']],
                    ['label' => 'Order Settings', 'route' => 'admin.business-settings.order-settings.index', 'patterns' => ['admin/business-settings/order-settings*']],
                    ['label' => 'Seller Settings', 'route' => 'admin.business-settings.vendor-settings.index', 'patterns' => ['admin/business-settings/vendor-settings*']],
                    ['label' => 'Customer Settings', 'route' => 'admin.business-settings.customer-settings', 'patterns' => ['admin/business-settings/customer-settings*']],
                    ['label' => 'Seller Registration', 'route' => 'admin.pages-and-media.vendor-registration-settings.index', 'patterns' => ['admin/pages-and-media/vendor-registration-settings*']],
                ]],
                ['label' => 'Shipping', 'items' => [
                    ['label' => 'Shipping Methods', 'route' => 'admin.business-settings.shipping-method.index', 'patterns' => ['admin/business-settings/shipping-method*']],
                    ['label' => 'Delivery Settings', 'route' => 'admin.business-settings.delivery-man-settings.index', 'patterns' => ['admin/business-settings/delivery-man-settings*']],
                    ['label' => 'Delivery Zones', 'route' => 'admin.business-settings.delivery-zone.index', 'patterns' => ['admin/business-settings/delivery-zone*']],
                ]],
                ['label' => 'Tax & Pricing', 'items' => [
                    ['label' => 'VAT / Tax Setup', 'route' => 'admin.vat-tax.index', 'addon' => 'TaxModule', 'patterns' => ['admin/vat-tax/*']],
                    ['label' => 'Currency', 'route' => 'admin.system-setup.currency.view', 'patterns' => ['admin/system-setup/currency*']],
                ]],
            ],
        ],
        [
            'id' => 'reseller-management', 'title' => 'Reseller Management', 'permission' => Helpers::module_permission_check('reseller_management'),
            'groups' => [['label' => 'Reseller', 'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.reseller.dashboard', 'patterns' => ['admin/reseller']],
                ['label' => 'Applications', 'route' => 'admin.reseller.applications', 'patterns' => ['admin/reseller/applications*']],
                ['label' => 'Resellers', 'route' => 'admin.reseller.profiles', 'patterns' => ['admin/reseller/profiles*']],
                ['label' => 'Payout Requests', 'route' => 'admin.reseller.payouts', 'patterns' => ['admin/reseller/payouts*']],
                ['label' => 'Settings', 'route' => 'admin.reseller.settings', 'patterns' => ['admin/reseller/settings*']],
                ['label' => 'Audit Logs', 'route' => 'admin.reseller.audit', 'patterns' => ['admin/reseller/audit*']],
            ]]],
        ],
        [
            'id' => 'affiliate-management', 'title' => 'Affiliate Management', 'permission' => Helpers::module_permission_check('affiliate_management'),
            'groups' => [['label' => 'Affiliate', 'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.affiliate.dashboard', 'patterns' => ['admin/affiliate']],
                ['label' => 'Applications', 'route' => 'admin.affiliate.applications', 'patterns' => ['admin/affiliate/applications*']],
                ['label' => 'Affiliates', 'route' => 'admin.affiliate.profiles', 'patterns' => ['admin/affiliate/profiles*']],
                ['label' => 'Payout Requests', 'route' => 'admin.affiliate.payouts', 'patterns' => ['admin/affiliate/payouts*']],
                ['label' => 'Settings', 'route' => 'admin.affiliate.settings', 'patterns' => ['admin/affiliate/settings*']],
                ['label' => 'Audit Logs', 'route' => 'admin.affiliate.audit', 'patterns' => ['admin/affiliate/audit*']],
            ]]],
        ],
        [
            'id' => 'system-settings', 'title' => 'System Settings', 'permission' => Helpers::module_permission_check('system_settings') || Helpers::module_permission_check('business_settings'),
            'groups' => [
                ['label' => 'Business', 'items' => [
                    ['label' => 'Business Information', 'route' => 'admin.business-settings.web-config.index', 'patterns' => ['admin/business-settings/web-config*']],
                    ['label' => 'In-house Shop', 'route' => 'admin.business-settings.inhouse-shop', 'patterns' => ['admin/business-settings/inhouse-shop*']],
                    ['label' => 'Environment Setup', 'route' => 'admin.system-setup.environment-setup', 'patterns' => ['admin/system-setup/environment-setup*']],
                ]],
                ['label' => 'System', 'items' => [
                    ['label' => 'Maintenance Mode', 'route' => 'admin.business-settings.maintenance-mode', 'module_permission' => 'system_settings', 'patterns' => ['admin/business-settings/maintenance-mode*']],
                    ['label' => 'Storage Settings', 'route' => 'admin.third-party.storage-connection-settings.index', 'module_permission' => 'system_settings', 'patterns' => ['admin/third-party/storage-connection-settings*']],
                    ['label' => 'Login Settings', 'route' => 'admin.system-setup.login-settings.customer-login-setup', 'module_permission' => 'system_settings', 'patterns' => ['admin/system-setup/login-settings*']],
                    ['label' => 'Email Templates', 'route' => 'admin.system-setup.email-templates.index', 'module_permission' => 'system_settings', 'patterns' => ['admin/system-setup/email-templates*']],
                    ['label' => 'File Manager', 'route' => 'admin.system-setup.file-manager.index', 'module_permission' => 'system_settings', 'patterns' => ['admin/system-setup/file-manager*']],
                    ['label' => 'Optimize System', 'route' => 'admin.system-setup.optimize-system', 'module_permission' => 'system_settings', 'patterns' => ['admin/system-setup/optimize-system*']],
                    ['label' => 'System Addons', 'route' => 'admin.system-setup.addon.index', 'module_permission' => 'themes_and_addons', 'patterns' => ['admin/system-setup/addon*']],
                    ['label' => 'Addon Activation', 'route' => 'admin.system-setup.addon-activation.index', 'module_permission' => 'themes_and_addons', 'patterns' => ['admin/system-setup/addon-activation*']],
                ]],
            ],
        ],
    ];
@endphp

<aside class="v2-ctxpanel" id="v2-ctxpanel">
    <div class="v2-ctx-scroll">
        @foreach ($v2NavModules as $module)
            @if ($module['permission'])
                <div class="v2-ctx-section {{ $loop->first ? 'v2-is-on' : '' }}" data-section="{{ $module['id'] }}">
                    <div class="v2-ctx-head"><div class="v2-ctx-title">{{ $module['title'] }}</div></div>
                    <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                        <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                        <div class="v2-ctx-group-body"></div>
                    </div>

                    @foreach ($module['groups'] as $group)
                        @php
                            $availableItems = array_values(array_filter($group['items'], $v2ItemAvailable));
                        @endphp
                        @if (count($availableItems) > 0)
                            <div class="v2-ctx-group">
                                <div class="v2-ctx-group-head"><span>{{ $group['label'] }}</span></div>
                                @foreach ($availableItems as $item)
                                    @php
                                        $itemActive = empty($item['no_active'])
                                            && $v2Patterns($item['patterns'] ?? [], $item['query'] ?? [], $item['exclude_query'] ?? []);
                                        $itemUrl = Route::has($item['route']) ? route($item['route'], $item['params'] ?? []) : '#';
                                        $itemId = $module['id'] . '-' . preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($item['label']));
                                    @endphp
                                    @if (!empty($item['children']))
                                        <div class="v2-nav-item v2-has-children {{ $itemActive ? 'v2-is-active' : '' }}" data-item="{{ $itemId }}">
                                            <a class="v2-nav-btn" href="{{ $itemUrl }}">
                                                <span class="v2-nav-label">{{ $item['label'] }}</span>
                                            </a>
                                            <div class="v2-nav-right">
                                                <button class="v2-pin-btn" type="button" data-pin="{{ $itemId }}" aria-label="Pin"></button>
                                                <span class="v2-nav-chev" aria-label="Toggle submenu"><svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 5 5 5-5 5"/></svg></span>
                                            </div>
                                        </div>
                                        <div class="v2-nav-children v2-is-collapsed" data-children-for="{{ $itemId }}">
                                            @foreach ($item['children'] as $child)
                                                @if (Route::has($child['route']))
                                                    @php
                                                        $childActive = $v2Patterns($child['patterns'] ?? [], $child['query'] ?? []);
                                                    @endphp
                                                    <a class="v2-nav-child {{ $childActive ? 'v2-is-on' : '' }}" href="{{ route($child['route'], $child['params'] ?? []) }}">
                                                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                                                        <span class="v2-nav-child-label">{{ $child['label'] }}</span>
                                                        @if (isset($child['badge']))<span class="v2-nav-child-count" data-tone="primary">{{ $child['badge'] }}</span>@endif
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <a class="v2-nav-item {{ $itemActive ? 'v2-is-active' : '' }}" data-item="{{ $itemId }}" data-no-active="{{ !empty($item['no_active']) ? '1' : '0' }}" href="{{ $itemUrl }}">
                                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ $item['label'] }}</span></span>
                                            <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="{{ $itemId }}" aria-label="Pin"></button></div>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    @if ($module['id'] === 'deals' && $v2Auction)
                        @foreach ($v2AuctionRoutes as $auctionGroup)
                            <div class="v2-ctx-group">
                                <div class="v2-ctx-group-head"><span>{{ translate($auctionGroup['name']) }}</span></div>
                                @foreach ($auctionGroup['routes'] as $auctionRoute)
                                    @php
                                        $auctionSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($auctionGroup['name'] . '-' . $auctionRoute['name']));
                                    @endphp
                                    <a class="v2-nav-item {{ strstr(Request::url(), $auctionRoute['path']) ? 'v2-is-active' : '' }}" data-item="deals-auction-{{ $auctionSlug }}" href="{{ $auctionRoute['url'] }}">
                                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($auctionRoute['name']) }}</span></span>
                                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="deals-auction-{{ $auctionSlug }}" aria-label="Pin"></button></div>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    @endif

                    @if ($module['id'] === 'website-settings' && count($v2ThemeRoutes) > 0)
                        @php
                            $themeRoutes = $v2ThemeRoutes['route_list'] ?? [];
                        @endphp
                        @if (count($themeRoutes) > 0)
                            <div class="v2-ctx-group">
                                <div class="v2-ctx-group-head"><span>{{ ($v2ThemeRoutes['name'] ?? 'Theme') }} Menu</span></div>
                                @foreach ($themeRoutes as $themeRoute)
                                    @if (empty($themeRoute['module_permission']) || Helpers::module_permission_check($themeRoute['module_permission']))
                                        <a class="v2-nav-item {{ Request::is($themeRoute['path']) || Request::is($themeRoute['path'].'*') ? 'v2-is-active' : '' }}" data-item="settings-theme-{{ $loop->index }}" href="{{ $themeRoute['url'] }}">
                                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($themeRoute['name']) }}</span></span>
                                            <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="settings-theme-{{ $loop->index }}" aria-label="Pin"></button></div>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endif

                    @if ($module['id'] === 'system-settings' && count($v2AddonAdminRoutes) > 0)
                        <div class="v2-ctx-group">
                            <div class="v2-ctx-group-head"><span>Add-on Administration</span></div>
                            @foreach ($v2AddonAdminRoutes as $addonRouteGroup)
                                @foreach ($addonRouteGroup as $addonRoute)
                                    @if (empty($addonRoute['module_permission']) || Helpers::module_permission_check($addonRoute['module_permission']))
                                        @php
                                            $addonSlug = 'settings-addon-' . preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($addonRoute['name'] ?? ('route-' . $loop->index)));
                                            $addonActive = !empty($addonRoute['path']) && strstr(Request::url(), $addonRoute['path']);
                                        @endphp
                                        <a class="v2-nav-item {{ $addonActive ? 'v2-is-active' : '' }}" data-item="{{ $addonSlug }}" href="{{ $addonRoute['url'] ?? '#' }}">
                                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($addonRoute['name'] ?? 'Add-on') }}</span></span>
                                            <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="{{ $addonSlug }}" aria-label="Pin"></button></div>
                                        </a>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                    @endif

                    @if ($module['id'] === 'reports' && $v2Tax && count($v2TaxReportRoutes) > 0)
                        <div class="v2-ctx-group">
                            <div class="v2-ctx-group-head"><span>Tax</span></div>
                            @foreach ($v2TaxReportRoutes as $taxRoute)
                                @php
                                    $taxSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower('tax-' . $taxRoute['name']));
                                    $taxActive = strstr(Request::url(), $taxRoute['path']);
                                    if (!$taxActive && !empty($taxRoute['sub_routes'])) {
                                        foreach ($taxRoute['sub_routes'] as $taxSubRoute) {
                                            if (strstr(Request::url(), $taxSubRoute['path'])) { $taxActive = true; break; }
                                        }
                                    }
                                @endphp
                                <a class="v2-nav-item {{ $taxActive ? 'v2-is-active' : '' }}" data-item="reports-{{ $taxSlug }}" href="{{ $taxRoute['url'] }}">
                                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($taxRoute['name']) }}</span></span>
                                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="reports-{{ $taxSlug }}" aria-label="Pin"></button></div>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($module['id'] === 'reports' && $v2Auction && count($v2AuctionReportRoutes) > 0)
                        <div class="v2-ctx-group">
                            <div class="v2-ctx-group-head"><span>Auction Reports</span></div>
                            @foreach ($v2AuctionReportRoutes as $auctionReport)
                                @php
                                    $auctionReportSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower('auction-' . $auctionReport['name']));
                                    $auctionReportActive = strstr(Request::url(), $auctionReport['path']);
                                @endphp
                                <a class="v2-nav-item {{ $auctionReportActive ? 'v2-is-active' : '' }}" data-item="reports-{{ $auctionReportSlug }}" href="{{ $auctionReport['url'] }}">
                                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($auctionReport['name']) }}</span></span>
                                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="reports-{{ $auctionReportSlug }}" aria-label="Pin"></button></div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    @php
        $v2Setup = function_exists('checkSetupGuideRequirements') ? checkSetupGuideRequirements(panel: 'admin') : ['completePercent' => 100, 'totalSteps' => 0];
        $v2ShowSetupGuide = ($v2Setup['completePercent'] ?? 100) < 100 && auth('admin')->user()->admin_role_id == 1;
    @endphp
    @if ($v2ShowSetupGuide)
        <div class="v2-ctx-foot">
            <button type="button" class="v2-setup-guide" data-v2-setup-trigger aria-label="{{ translate('Setup_Guide') }}">
                <div class="v2-setup-guide-icon"><img src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/setup_guide.png') }}" width="18" height="18" alt=""></div>
                <div class="v2-setup-guide-body"><span class="v2-setup-guide-title">{{ translate('Setup_Guide') }}</span><span class="v2-setup-guide-sub">{{ $v2Setup['completePercent'] ?? 0 }}% {{ translate('complete') }}</span></div>
                <div class="v2-setup-guide-chev"><svg width="11" height="11" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 5 5 5-5 5-5 5"/></svg></div>
            </button>
        </div>
    @endif
</aside>

<aside class="v2-rail-flyout" id="v2-rail-flyout" aria-hidden="true"></aside>

<div class="v2-palette-scrim" id="v2-palette-scrim" hidden>
    <div class="v2-palette">
        <div class="v2-palette-head">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="9" r="5"/><path d="m13 13 3.5 3.5"/></svg>
            <input id="v2-palette-input" type="text" placeholder="Jump to anything — orders, sellers, settings…" autocomplete="off"/>
            <span class="v2-kbd">esc</span>
        </div>
        <div class="v2-palette-list" id="v2-palette-list"></div>
        <div class="v2-palette-foot"><span class="v2-palette-hint"><span class="v2-kbd">↑</span><span class="v2-kbd">↓</span> navigate</span><span class="v2-palette-hint"><span class="v2-kbd">↵</span> open</span><span class="v2-palette-hint"><span class="v2-kbd">esc</span> close</span></div>
    </div>
</div>
