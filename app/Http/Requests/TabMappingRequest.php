<?php

namespace App\Http\Requests;

use App\Enums\ColumnType;
use App\Models\SheetTab;
use App\Models\TabMapping;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TabMappingRequest extends FormRequest
{
    /**
     * Table and column names go into DDL, so only safe identifiers are allowed.
     */
    private const string IDENTIFIER = 'regex:/^[a-z][a-z0-9_]{0,63}$/';

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
        /** @var TabMapping|null $mapping */
        $mapping = $this->route('mapping');

        /** @var SheetTab $tab */
        $tab = $mapping->tab ?? $this->route('tab');

        return [
            'target_table' => [
                'required', 'string', self::IDENTIFIER,
                Rule::notIn(config('sheets.reserved_tables')),
                Rule::unique(TabMapping::class)->where('sheet_tab_id', $tab->id)->ignore($mapping),
            ],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.source_column' => ['required', 'string', 'max:255'],
            'columns.*.target_column' => ['required', 'string', self::IDENTIFIER, 'distinct', Rule::notIn(['id'])],
            'columns.*.data_type' => ['required', Rule::enum(ColumnType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $format = 'Use lowercase latin letters, digits and underscores, starting with a letter.';

        return [
            'target_table.regex' => $format,
            'target_table.not_in' => 'This table is used by the application.',
            'columns.*.target_column.regex' => $format,
        ];
    }
}
