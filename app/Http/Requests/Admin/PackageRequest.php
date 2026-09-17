<?php

namespace App\Http\Requests\Admin;

use App\Models\Package;
use App\Models\PackageFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_highlighted' => $this->boolean('is_highlighted'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        /** @var Package|null $package */
        $package = $this->route('package');

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique('packages', 'slug')->ignore($package?->id)],
            'tier' => ['required', Rule::in(array_keys(Package::TIER_LABELS))],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_unless:price,0'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
            'is_highlighted' => ['boolean'],
            'is_default' => ['boolean'],
            'display_lines' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (PackageFeature::KEYS as $key => $description) {
            $field = 'features.'.str_replace('.', '_', $key);
            $rules["{$field}.label"] = ['nullable', 'string', 'max:191'];
            $rules["{$field}.show"] = ['nullable', 'boolean'];
            $rules[PackageFeature::KEY_TYPES[$key] === 'limit' ? "{$field}.limit" : "{$field}.enabled"] =
                PackageFeature::KEY_TYPES[$key] === 'limit'
                    ? ['nullable', 'integer', 'min:0', 'max:100000']
                    : ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Package|null $package */
            $package = $this->route('package');

            if ($this->boolean('is_default') && ((float) $this->input('price') > 0 || $this->input('tier') !== Package::TIER_FREE)) {
                $validator->errors()->add('is_default', 'Gói mặc định phải là gói Free giá 0₫.');
            }

            if ($this->boolean('is_default') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'Gói mặc định phải đang bật.');
            }

            // Luôn phải có gói mặc định — nếu không, giới hạn Free sẽ rơi về cấu hình dự phòng.
            if ($package?->is_default && ! $this->boolean('is_default')) {
                $validator->errors()->add('is_default', 'Hãy đặt gói khác làm mặc định trước khi bỏ gói này.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên gói',
            'tier' => 'hạng',
            'price' => 'giá',
            'duration_days' => 'số ngày',
        ];
    }
}
