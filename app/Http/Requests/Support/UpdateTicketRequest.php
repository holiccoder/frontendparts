<?php

namespace App\Http\Requests\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * User-side ticket status update: users may only close their ticket — every
 * other state is admin-set (SPEC §13.3, TicketStatus transition map).
 */
class UpdateTicketRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['closed'])],
        ];
    }
}
