<?php

namespace App\Http\Requests;

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SheetTabRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var SheetTab|null $tab */
        $tab = $this->route('tab');

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $tab->spreadsheet ?? $this->route('spreadsheet');

        return [
            'tab_name' => [
                'required', 'string', 'max:100',
                Rule::unique(SheetTab::class)->where('spreadsheet_id', $spreadsheet->id)->ignore($tab),
            ],
            'header_row' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
