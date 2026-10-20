<?php

namespace App\Services;

use App\Traits\FileManagerTrait;
use Illuminate\Support\Str;

class FlashDealService
{
    use FileManagerTrait;

    public function getAddData(object $request): array
    {
        return [
            'title' => $request['title'][array_search('en', $request['lang'])],
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
            'background_color' => $request['background_color'],
            'text_color' => $request['text_color'],
            'banner' => $request->has('image') ? $this->upload(dir:'deal/', format: 'webp', image: $request->file('image')) : 'def.webp',
            'slug' => Str::slug($request['title'][array_search('en', $request['lang'])]),
            'featured' => $request['featured'] == 1 ? 1 : 0,
            'deal_type' => $request['deal_type'] == 'flash_deal' ? 'flash_deal' : 'feature_deal',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function getUpdateData(object $request, object $data): array
    {
        return [
            'title' => $request['title'][array_search('en', $request['lang'])],
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
            'background_color' => $request['background_color'],
            'text_color' => $request['text_color'],
            'banner' => $request->file('image') ? $this->update('deal/', $data['banner'],'webp', $request->file('image')) : $data['banner'],
            'slug' => Str::slug($request['title'][array_search('en', $request->lang)]),
            'featured' => $request['featured'] == 'on' ? 1 : 0,
            'deal_type' => $request['deal_type'] == 'flash_deal' ? 'flash_deal' : 'feature_deal',
            'updated_at' => now(),
        ];
    }


    public function getAddProduct(object $request,string|int $productId ,string|int $id): array
    {
        $discountType = in_array($request['discount_type'] ?? 'percent', ['percent', 'flat'], true)
            ? $request['discount_type']
            : 'percent';
        $discount = max(0, (float) ($request['discount'] ?? 0));
        if ($discountType === 'percent') {
            $discount = min(100, $discount);
        }

        return [
            'product_id' => $productId,
            'flash_deal_id' => $id,
            'discount' => $discount,
            'discount_type' => $discountType,
            'funding_type' => in_array($request['funding_type'] ?? 'seller', ['seller', 'platform'], true)
                ? $request['funding_type']
                : 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

}
