<?php

namespace Modules\AI\app\Http\Requests\ShoppingAssistant;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
