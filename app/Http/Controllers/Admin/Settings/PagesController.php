<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Contracts\Repositories\AttachmentRepositoryInterface;
use App\Contracts\Repositories\BusinessPageRepositoryInterface;
use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\BusinessPageAddRequest;
use App\Http\Requests\Admin\BusinessPageUpdateRequest;
use App\Services\BusinessPageService;
use App\Models\LegalPolicyVersion;
use App\Traits\UpdateClass;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PagesController extends BaseController
{

    use UpdateClass;

    public function __construct(
        private readonly BusinessSettingRepositoryInterface $businessSettingRepo,
        private readonly BusinessPageRepositoryInterface    $businessPageRepo,
        private readonly AttachmentRepositoryInterface      $attachmentRepo,
        private readonly BusinessPageService                $businessPageService,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View Index function is the starting point of a controller
     * Index function is the starting point of a controller
     */
    public function index(Request|null $request, ?string $type = null): View
    {
        $defaultPages = [
            'about_us' => 'about-us',
            'terms_condition' => 'terms-and-conditions',
            'privacy_policy' => 'privacy-policy',
            'refund_policy' => 'refund-policy',
            'return_policy' => 'return-policy',
            'cancellation_policy' => 'cancellation-policy',
            'shipping_policy' => 'shipping-policy',
        ];

        foreach ($defaultPages as $key => $value) {
            $pages = \App\Models\BusinessPage::where('slug', $value)->orderBy('id')->get();
            if ($pages->count() > 1) {
                $idsToDelete = $pages->skip(1)->pluck('id')->toArray() ?? [];
                foreach ($idsToDelete as $id) {
                    $this->businessPageRepo->delete(params: ['id' => $id]);
                }
                if (!empty($idsToDelete)) {
                    \App\Models\BusinessPage::destroy($idsToDelete);
                }
                cacheRemoveByType(type: 'business_pages');
            }
        }

        $businessPages = $this->businessPageRepo->getListWhere(orderBy: ['default_status' => 'desc'], searchValue: $request['search'] ?? '', dataLimit: 'all');
        if ($businessPages->count() <= 0) {
            $this->addOrUpdateBusinessPagesData();
            $businessPages = $this->businessPageRepo->getListWhere(orderBy: ['default_status' => 'desc'], dataLimit: 'all');
        }
        return view('admin-views.pages-and-media.list', compact('businessPages'));
    }

    public function getAddView(): View|RedirectResponse
    {
        if ($this->businessPageRepo->getListWhere(dataLimit: 'all')->count() >= 17) {
            ToastMagic::warning(translate('Can_not_add_custom_business_page_more_then_10'));
            return back();
        }

        return view('admin-views.pages-and-media.add');
    }

    public function getAdd(BusinessPageAddRequest $request): RedirectResponse
    {
        $data = $this->businessPageService->getPageAddData(request: $request);
        $businessPage = $this->businessPageRepo->add(data: $data);

        if ($request->hasFile('banner')) {
            $attachmentData = $this->businessPageService->getPageAttachmentAddData(request: $request, page: $businessPage);
            $this->attachmentRepo->add(data: $attachmentData);
        }
        ToastMagic::success(translate('Page_add_successfully'));
        return redirect()->route('admin.pages-and-media.list');
    }

    public function getUpdateView(Request $request): View
    {
        $businessPage = $this->businessPageRepo->getFirstWhere(params: ['slug' => $request['slug']], relations: ['banner']);
        return view('admin-views.pages-and-media.update', compact('businessPage'));
    }

    public function getUpdate(BusinessPageUpdateRequest $request): RedirectResponse
    {
        $existingPage = $this->businessPageRepo->getFirstWhere(params: ['id' => $request['id']]);
        $isPolicyPage = in_array($existingPage?->slug, ['about-us', 'terms-and-conditions', 'privacy-policy', 'refund-policy', 'return-policy', 'cancellation-policy']);
        if ($isPolicyPage && $existingPage) {
            LegalPolicyVersion::create([
                'business_page_id' => $existingPage->id,
                'version' => ((int) ($existingPage->version ?: 1)),
                'title' => $existingPage->title,
                'hero_title' => $existingPage->hero_title,
                'hero_subtitle' => $existingPage->hero_subtitle,
                'at_a_glance' => $existingPage->at_a_glance,
                'description' => $existingPage->description,
                'seo_title' => $existingPage->seo_title,
                'seo_description' => $existingPage->seo_description,
                'policy_status' => $existingPage->policy_status ?: 'published',
                'published_at' => $existingPage->published_at,
                'published_by' => $existingPage->published_by,
            ]);
        }
        $data = $this->businessPageService->getPageUpdateData(request: $request);
        if ($isPolicyPage) {
            $data['version'] = ((int) ($existingPage->version ?: 1)) + 1;
            if (($data['policy_status'] ?? 'published') === 'published') {
                $data['published_at'] = now();
                $data['published_by'] = auth('admin')->id();
            }
        }
        $this->businessPageRepo->updateWhere(params: ['id' => $request['id']], data: $data);

        foreach (['hero_image', 'supporting_image_1', 'supporting_image_2'] as $imageField) {
            if (!$request->hasFile($imageField)) continue;
            $this->businessPageRepo->updateWhere(
                params: ['id' => $request['id']],
                data: [$imageField => $this->update(
                    dir: 'company-pages/',
                    oldImage: $existingPage?->{$imageField},
                    format: 'webp',
                    image: $request->file($imageField)
                )]
            );
        }

        if ($request->hasFile('banner')) {
            $oldImage = $this->attachmentRepo->getFirstWhere(params: [
                'attachable_type' => 'App\Models\BusinessPage',
                'attachable_id' => $request['id'],
                'file_type' => 'banner'
            ]);
            $this->attachmentRepo->updateOrInsert(params: [
                'attachable_type' => 'App\Models\BusinessPage',
                'attachable_id' => $request['id'],
                'file_type' => 'banner',
            ], data: [
                'attachable_type' => 'App\Models\BusinessPage',
                'attachable_id' => $request['id'],
                'file_type' => 'banner',
                'file_name' => $this->update(dir: 'business-pages/', oldImage: $oldImage?->file_name, format: 'webp', image: $request['banner']),
                'storage_disk' => config('filesystems.disks.default') ?? 'public',
            ]);
        }
        ToastMagic::success(translate('Update_successfully'));
        return redirect()->route('admin.pages-and-media.list');
    }

    public function getVersionHistory(Request $request): View
    {
        $page = $this->businessPageRepo->getFirstWhere(params: ['id' => $request['id']]);
        $versions = LegalPolicyVersion::where('business_page_id', $request['id'])->latest('version')->paginate(20);
        return view('admin-views.pages-and-media.versions', compact('page', 'versions'));
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $this->businessPageRepo->updateWhere(params: ['id' => $request['id']], data: ['status' => $request['status'] ?? 0]);
        return response()->json([
            'status' => 1,
            'message' => translate('Status_updated_successfully')
        ]);
    }

    public function getDeleteImage(Request $request): RedirectResponse
    {
        $data = $this->businessPageRepo->getFirstWhere(params: ['id' => $request['id']], relations: ['banner']);
        $fileName = $data?->banner?->file_name;

        if ($fileName) {
            $name = "business-pages/". $fileName;
            $deleteFromDirectory = $this->delete($name);
            if ($deleteFromDirectory['success']) {
                $this->attachmentRepo->updateOrInsert(
                    params: [
                        'attachable_type' => 'App\Models\BusinessPage',
                        'attachable_id' => $request['id'],
                        'file_type' => 'banner',
                    ],
                    data: [
                        'file_name' => '',
                    ]
                );
            }
            ToastMagic::success(translate('Banner_Image_Deleted_Successfully'));
            return redirect()->route('admin.pages-and-media.list');
        }
        ToastMagic::success(translate('Banner_Image_Path_Not_Found'));
        return redirect()->route('admin.pages-and-media.list');
    }


    public function getDelete(Request $request): RedirectResponse
    {
        $this->businessPageRepo->delete(params: ['slug' => $request['slug']]);
        ToastMagic::success(translate('Delete_successfully'));
        return redirect()->route('admin.pages-and-media.list');
    }

}
