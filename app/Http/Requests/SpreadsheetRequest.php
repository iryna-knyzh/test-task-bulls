<?php

namespace App\Http\Requests;

use App\Models\Spreadsheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpreadsheetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept a full Google Sheets URL and keep only its spreadsheet id.
     */
    protected function prepareForValidation(): void
    {
        $value = trim((string) $this->input('google_spreadsheet_id'));

        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $value, $matches) === 1) {
            $value = $matches[1];
        }

        $this->merge([
            'google_spreadsheet_id' => $value,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Spreadsheet|null $spreadsheet */
        $spreadsheet = $this->route('spreadsheet');

        return [
            'name' => ['required', 'string', 'max:255'],
            'google_spreadsheet_id' => [
                'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique(Spreadsheet::class)->ignore($spreadsheet),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
