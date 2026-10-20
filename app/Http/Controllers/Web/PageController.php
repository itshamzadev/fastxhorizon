<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\BusinessPageRepositoryInterface;
use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Contracts\Repositories\HelpTopicRepositoryInterface;
use App\Contracts\Repositories\RobotsMetaContentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request;
use App\Models\BusinessPage;
use App\Models\CompanyLeadershipProfile;
use App\Models\OrderDetail;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function __construct(
        private readonly BusinessSettingRepositoryInterface   $businessSettingRepo,
        private readonly HelpTopicRepositoryInterface         $helpTopicRepo,
        private readonly RobotsMetaContentRepositoryInterface $robotsMetaContentRepo,
        private readonly BusinessPageRepositoryInterface      $businessPageRepo,
    )
    {
    }

    public function getPageView(Request $request): View|RedirectResponse
    {
        $filter = ['slug' => $request['slug']];
        if (!($request->has('source') && $request->source == 'admin')) {
            $filter += ['status' => 1];
            if (in_array($request['slug'], ['terms-and-conditions', 'privacy-policy', 'refund-policy', 'return-policy', 'cancellation-policy'])) {
                $filter += ['policy_status' => 'published'];
            }
        }
        $businessPage = $this->businessPageRepo->getFirstWhere(params: $filter, relations: ['banner']);
        if (!$businessPage) {
            Toastr::error(translate('Page_not_found'));
            return redirect()->route('home');
        }
        $robotsMetaContentData = $this->robotsMetaContentRepo->getFirstWhere(params: ['page_name' => $request['slug']]);
        if (!$robotsMetaContentData) {
            $robotsMetaContentData = $this->robotsMetaContentRepo->getFirstWhere(params: ['page_name' => 'default']);
        }

        if ($request['slug'] === 'about-us') {
            $robotsMetaContentData = (object)[
                'meta_title' => 'About ' . getCompanyIdentity('brand') . ' | ' . getCompanyIdentity('formal'),
                'meta_description' => getCompanyIdentity('brand') . ' is a modern ecommerce company connecting customers and sellers through a trusted, technology-led marketplace.',
                'index' => 'index',
                'no_follow' => false,
                'no_image_index' => false,
                'no_archive' => false,
                'no_snippet' => false,
                'meta_max_snippet' => false,
                'max_snippet_value' => null,
                'max_video_preview' => false,
                'max_video_preview_value' => null,
                'max_image_preview' => false,
                'max_image_preview_value' => null,
                'canonicals_url' => route('business-page.view', ['slug' => 'about-us']),
            ];
        }
        if ($request['slug'] === 'about-us') {
            $companyProfile = [
                'brand_name' => getCompanyIdentity('brand'),
                'legal_name' => getCompanyIdentity('formal'),
                'legal_name_short' => getCompanyIdentity('short'),
                'company_status' => getCompanyIdentity('status'),
                'founded_year' => getWebConfig('company_founded_year') ?: '2026',
                'registration_type' => getWebConfig('company_registration_type'),
                'registration_number' => getWebConfig('company_registration_number'),
                'registration_date' => getWebConfig('company_registration_date'),
                'email' => getWebConfig('company_public_email') ?: getWebConfig('company_email'),
                'phone' => \App\Utils\Helpers::formatCompanyPhone(getWebConfig('company_public_phone') ?: getWebConfig('company_phone')),
                'website' => getWebConfig('company_public_website') ?: 'www.fastxhorizon.com',
                'office_address' => getWebConfig('company_show_office_address') == '1' ? getWebConfig('company_public_office_address') : null,
            ];
            $leadership = CompanyLeadershipProfile::query()->where('is_active', true)->orderBy('display_order')->get();
            return view(VIEW_FILE_NAMES['business_page'], compact('businessPage', 'robotsMetaContentData', 'companyProfile', 'leadership'));
        }

        $relatedPolicies = BusinessPage::query()
            ->whereIn('slug', ['about-us', 'terms-and-conditions', 'privacy-policy', 'refund-policy', 'return-policy', 'cancellation-policy'])
            ->where('status', 1)->where('policy_status', 'published')->where('slug', '!=', 'about-us')->where('id', '!=', $businessPage->id)
            ->orderBy('id')->get(['title', 'slug']);
        $companyContact = [
            'email' => getWebConfig('company_public_email') ?: getWebConfig('company_email'),
            'phone' => \App\Utils\Helpers::formatCompanyPhone(getWebConfig('company_public_phone') ?: getWebConfig('company_phone')),
        ];
        $eligibleReturnItems = collect();
        if (auth('customer')->check() && in_array($request['slug'], ['refund-policy', 'return-policy'])) {
            $days = (int) (getWebConfig('return_window_days') ?: 7);
            $eligibleReturnItems = OrderDetail::query()
                ->with(['product', 'order'])
                ->where('refund_request', 0)
                ->whereHas('order', function ($query) use ($days) {
                    $query->where('customer_id', auth('customer')->id())
                        ->whereIn('order_status', ['delivered', 'completed'])
                        ->where('updated_at', '>=', now()->subDays($days));
                })->latest()->limit(12)->get();
        }
        return view('web-views.pages.legal-policy', compact('businessPage', 'robotsMetaContentData', 'relatedPolicies', 'companyContact', 'eligibleReturnItems'));
    }

    public function getContactView(): View
    {
        $robotsMetaContentData = $this->robotsMetaContentRepo->getFirstWhere(params: ['page_name' => 'contacts']);
        if (!$robotsMetaContentData) {
            $robotsMetaContentData = $this->robotsMetaContentRepo->getFirstWhere(params: ['page_name' => 'default']);
        }
        return view(VIEW_FILE_NAMES['contacts'], compact('robotsMetaContentData'));
    }

    public function getHelpTopicView(): View
    {
        $robotsMetaContentData = $this->robotsMetaContentRepo->getFirstWhere(params: ['page_name' => 'helpTopic']);
        if (!$robotsMetaContentData) {
            $robotsMetaContentData = $this->robotsMetaContentRepo->getFirstWhere(params: ['page_name' => 'default']);
        }
        $helps = $this->helpTopicRepo->getListWhere(orderBy: ['ranking' => 'asc', 'id' => 'asc'], filters: ['status' => 1], dataLimit: 'all');
        $pageTitleBanner = $this->businessSettingRepo->whereJsonContains(params: ['type' => 'banner_faq_page'], value: ['status' => '1']);
        return view(VIEW_FILE_NAMES['faq'], compact('helps', 'pageTitleBanner', 'robotsMetaContentData'));
    }

}
