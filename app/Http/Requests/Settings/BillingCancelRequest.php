<?php

namespace App\Http\Requests\Settings;

use App\Enums\CancellationReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingCancelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The exit survey is REQUIRED before cancelling (SPEC §16.2 B7):
     * `reason` must always be a valid survey answer. `confirmed` separates
     * the two flow steps — absent/false presents the reason-mapped save
     * offer without touching the order; true finalizes the cancellation.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(CancellationReason::class)],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }
}
