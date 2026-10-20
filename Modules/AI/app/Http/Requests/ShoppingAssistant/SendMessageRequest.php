<?php

namespace Modules\AI\app\Http\Requests\ShoppingAssistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'                 => ['required', 'string', 'max:1000'],
            'image_url'               => ['nullable', 'string', 'max:2048', $this->allowedImageHost()],
            'guest_id'                => ['nullable', 'string', 'max:64'],
            'cart_selection'             => ['nullable', 'array'],
            'cart_selection.product_id'  => ['nullable', 'integer'],
            'cart_selection.color'       => ['nullable', 'string', 'max:32'],
            'cart_selection.qty'         => ['nullable', 'integer', 'min:1'],
            'cart_selection.choices'     => ['nullable', 'array'],
            'cart_selection.variant_key' => ['nullable', 'string', 'max:255'],
            'cart_selections'            => ['nullable', 'array', 'max:20'],
            'cart_selections.*.product_id' => ['nullable', 'integer'],
            'cart_selections.*.color'    => ['nullable', 'string', 'max:32'],
            'cart_selections.*.qty'      => ['nullable', 'integer', 'min:1'],
            'cart_selections.*.choices'  => ['nullable', 'array'],
            'cart_selections.*.variant_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    // Reject image_url not on the store's own app/storage host — prevents SSRF against the AI vision provider.
    private function allowedImageHost(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            if (empty($value)) {
                return;
            }

            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            if ($host === '') {
                $fail(translate('Invalid_image_URL'));
                return;
            }

            if (!in_array($host, $this->allowedImageHosts(), strict: true)) {
                $fail(translate('Image_URL_is_not_allowed'));
            }
        };
    }

    /** @return string[] */
 
        private function allowedImageHosts(): array
{
    $hosts = [
        strtolower((string) parse_url((string) url('/'), PHP_URL_HOST)),
        strtolower((string) parse_url((string) Storage::disk('public')->url('/'), PHP_URL_HOST)),
    ];

    if (env('DEVELOPMENT_ENVIRONMENT', false)) {
        $sample = env(
            'AI_DEV_SAMPLE_IMAGE_URL',
            'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600'
        );

        $hosts[] = strtolower((string) parse_url($sample, PHP_URL_HOST));
    }

    return array_values(array_filter(array_unique($hosts)));
}
}
