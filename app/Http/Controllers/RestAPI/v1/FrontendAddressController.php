<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\ShippingAddress;
use App\Traits\CommonTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FrontendAddressController extends Controller
{
    use CommonTrait;

    public function index(Request $request): JsonResponse
    {
        $addresses = ShippingAddress::query()
            ->where('customer_id', $request->user()->id)
            ->where('is_guest', 0)
            ->latest('id')
            ->get();

        return response()->json(['data' => AddressResource::collection($addresses)->resolve()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $address = ShippingAddress::create(array_merge($data, [
            'customer_id' => $request->user()->id,
            'is_guest' => 0,
        ]));

        return response()->json(['data' => (new AddressResource($address))->resolve()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = $this->owned($request, $id);
        $address->update($this->validated($request));

        return response()->json(['data' => (new AddressResource($address->refresh()))->resolve()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = $this->owned($request, $id);
        $address->delete();

        return response()->json(['message' => translate('successfully removed!')]);
    }

    private function owned(Request $request, int $id): ShippingAddress
    {
        return ShippingAddress::query()
            ->where('customer_id', $request->user()->id)
            ->where('is_guest', 0)
            ->findOrFail($id);
    }

    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => ['required', 'string', 'max:255'],
            'address_type' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'zip' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'is_billing' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            abort(response()->json(['errors' => $validator->errors()], 422));
        }

        $data = $validator->validated();
        if ((int) getWebConfig(name: 'delivery_country_restriction') === 1 && ! $this->delivery_country_exist_check($data['country'])) {
            abort(response()->json(['message' => translate('Delivery_unavailable_for_this_country')], 422));
        }
        if ((int) getWebConfig(name: 'delivery_zip_code_area_restriction') === 1 && ! $this->delivery_zipcode_exist_check($data['zip'])) {
            abort(response()->json(['message' => translate('Delivery_unavailable_for_this_zip_code_area')], 422));
        }

        return $data;
    }
}
