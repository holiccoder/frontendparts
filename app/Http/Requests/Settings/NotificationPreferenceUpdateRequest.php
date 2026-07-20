<?php

namespace App\Http\Requests\Settings;

use App\Enums\DigestFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferenceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only marketing categories are writable (SPEC §16.3) — there is
     * deliberately no `transactional` key, so validated() can never carry a
     * request to disable mandatory mail.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_updates' => ['required', 'boolean'],
            'blog' => ['required', 'boolean'],
            'digest_frequency' => ['required', Rule::enum(DigestFrequency::class)],
        ];
    }
}
