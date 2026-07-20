<?php

namespace App\Http\Requests\Support;

use App\Enums\TicketCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt,zip', 'max:5120'],
        ];
    }
}
