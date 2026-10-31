<?php

namespace App\Services\Sheets;

interface SheetsClient
{
    /**
     * Get all cell values of a tab as rows of formatted strings.
     *
     * @return list<list<string>>
     */
    public function getValues(string $spreadsheetId, string $tabName): array;
}
