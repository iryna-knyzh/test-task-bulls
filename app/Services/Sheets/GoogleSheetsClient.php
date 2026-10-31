<?php

namespace App\Services\Sheets;

use Google\Client;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Sheets;
use RuntimeException;

class GoogleSheetsClient implements SheetsClient
{
    public function __construct(
        private readonly ?string $apiKey,
    ) {}

    public function getValues(string $spreadsheetId, string $tabName): array
    {
        $range = "'".str_replace("'", "''", $tabName)."'";

        try {
            $rows = (new Sheets($this->makeClient()))->spreadsheets_values->get($spreadsheetId, $range)->getValues();
        } catch (GoogleServiceException $exception) {
            $reason = $exception->getErrors()[0]['message'] ?? $exception->getMessage();

            throw new RuntimeException("Google Sheets API error: {$reason}", previous: $exception);
        }

        return array_values(array_map(
            fn (array $row): array => array_map(strval(...), array_values($row)),
            $rows ?? [],
        ));
    }

    /**
     * Build an API client from the API key.
     * An API key can only read spreadsheets shared as "Anyone with the link".
     */
    private function makeClient(): Client
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('Google API key is not configured. Set GOOGLE_API_KEY in .env.');
        }

        $client = new Client;
        $client->setDeveloperKey($this->apiKey);

        return $client;
    }
}
