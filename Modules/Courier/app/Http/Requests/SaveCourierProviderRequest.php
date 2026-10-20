<?php

namespace Modules\Courier\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Courier\app\Services\ProviderRegistry;

class SaveCourierProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('country')) {
            $this->merge(['country' => strtoupper(trim((string) $this->input('country')))]);
        }
    }

    public function rules(): array
    {
        $registry = app(ProviderRegistry::class);
        $provider = (string) $this->input('provider');

        if (!$registry->has($provider)) {
            return ['provider' => ['required', 'string', Rule::in($registry->available())]];
        }

        $driver = $registry->driver($provider);
        $driver->setCountry($this->input('country'));

        return [
            'provider'    => ['required', 'string', Rule::in($registry->available())],
            'is_enabled'  => ['nullable', 'boolean'],
            'environment' => ['nullable', 'string', Rule::in($driver->environments())],
            'country'     => ['nullable', 'string', Rule::in($driver->supportedCountries())],
            'credentials' => ['nullable', 'array'],
            ...$this->credentialRules($driver->credentialFields()),
        ];
    }

    public function attributes(): array
    {
        $registry = app(ProviderRegistry::class);
        $provider = (string) $this->input('provider');

        $attributes = [
            'provider'    => 'Delivery Partner',
            'is_enabled'  => 'Status',
            'environment' => 'Environment',
            'country'     => 'Operating Country',
        ];

        if (!$registry->has($provider)) {
            return $attributes;
        }

        foreach ($registry->driver($provider)->credentialFields() as $field) {
            $attributes['credentials.'.$field['key']] = $field['label'];
        }

        return $attributes;
    }

    private function credentialRules(array $fields): array
    {
        if (!$this->has('credentials')) {
            return [];
        }

        $rules = [];

        foreach ($fields as $field) {
            $fieldRules = [
                empty($field['required']) ? 'nullable' : 'required',
                'string',
                'max:1000',
            ];

            if (($field['type'] ?? 'text') === 'select') {
                $fieldRules[] = Rule::in(array_column($field['options'] ?? [], 'id'));
            }

            $rules['credentials.'.$field['key']] = $fieldRules;
        }

        return $rules;
    }
}
