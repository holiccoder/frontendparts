<?php

namespace App\Http\Requests\Submissions;

use App\Enums\CategoryType;
use App\Enums\ComponentLevel;
use App\Enums\SubmissionFramework;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New community submission (task 5.3): metadata (name/level/category/
 * framework), a single-file paste per declared framework, optional sample
 * data (JSON object) and the real-world citation URL. Code size is capped
 * so submissions stay single components, not whole projects.
 */
class StoreComponentSubmissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'level' => ['required', Rule::enum(ComponentLevel::class)],
            'usage_category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('type', CategoryType::Usage->value),
            ],
            'framework' => ['required', Rule::enum(SubmissionFramework::class)],
            'description' => ['required', 'string', 'max:5000'],
            'react_code' => ['nullable', 'string', 'max:50000', 'required_if:framework,react,both'],
            'vue_code' => ['nullable', 'string', 'max:50000', 'required_if:framework,vue,both'],
            'sample_data' => ['nullable', 'string', 'max:20000', $this->jsonObjectRule()],
            'source_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * Sample data must decode to a JSON object (`{}` allowed) — it becomes
     * the component's data.json on approval, and the sync validator derives
     * the params schema from its keys.
     */
    private function jsonObjectRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $decoded = json_decode((string) $value, true);

            if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                $fail('The :attribute must be a JSON object, e.g. {"label": "Hello"}.');
            }
        };
    }

    /**
     * Decoded sample data as an associative array, null when omitted.
     *
     * @return array<string, mixed>|null
     */
    public function sampleData(): ?array
    {
        $raw = $this->validated('sample_data');

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $raw, true);

        return $decoded;
    }
}
