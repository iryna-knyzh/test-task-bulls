<?php

use App\Services\Sheets\GoogleSheetsClient;

test('reading a sheet without an api key fails with setup instructions', function () {
    (new GoogleSheetsClient(null))->getValues('spreadsheet-id', 'Sales');
})->throws(RuntimeException::class, 'Google API key is not configured. Set GOOGLE_API_KEY in .env.');
