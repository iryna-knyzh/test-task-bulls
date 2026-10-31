<?php

namespace Tests\Fakes;

use App\Services\Sheets\SheetsClient;
use RuntimeException;

class FakeSheetsClient implements SheetsClient
{
    /**
     * @var array<string, list<list<string>>>
     */
    private array $tabs = [];

    /**
     * @param  list<list<string>>  $rows
     */
    public function withTab(string $spreadsheetId, string $tabName, array $rows): self
    {
        $this->tabs["{$spreadsheetId}/{$tabName}"] = $rows;

        return $this;
    }

    public function getValues(string $spreadsheetId, string $tabName): array
    {
        return $this->tabs["{$spreadsheetId}/{$tabName}"]
            ?? throw new RuntimeException("Unable to parse range: {$tabName}");
    }
}
