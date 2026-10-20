<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $id
 */
class ProductIDRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percent,flat',
            'funding_type' => 'nullable|in:seller,platform',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => translate('please_select_at_least_one_product'),
        ];
    }

}
