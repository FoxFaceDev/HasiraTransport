<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class DriversSpreadsheetSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('Drivers.xlsx');

        if (! is_file($path)) {
            throw new RuntimeException("Drivers workbook not found at {$path}.");
        }

        $rows = $this->readRows($path);

        DB::transaction(function () use ($rows): void {
            $groupedRows = [];

            foreach ($rows as $row) {
                $identity = json_encode([
                    $row['name'],
                    $row['phone'],
                    $row['license_number'],
                ]);
                $groupedRows[$identity][] = $row;
            }

            foreach ($groupedRows as $matchingRows) {
                $identity = $matchingRows[0];
                $existingDrivers = Driver::query()
                    ->where('name', $identity['name'])
                    ->where('phone', $identity['phone'])
                    ->where('license_number', $identity['license_number'])
                    ->orderBy('id')
                    ->get();

                foreach ($matchingRows as $index => $row) {
                    $values = [
                        'has_certificate' => $row['certificate_number'] !== null,
                        'certificate_number' => $row['certificate_number'],
                    ];

                    if ($existingDrivers->has($index)) {
                        $existingDrivers->get($index)->update($values);
                    } else {
                        Driver::query()->create([...$row, ...$values]);
                    }
                }
            }
        });

        $this->command?->info(count($rows).' drivers imported from Drivers.xlsx.');
    }

    /**
     * @return array<int, array{name: string, phone: ?string, license_number: ?string, certificate_number: ?string}>
     */
    private function readRows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open {$path}.");
        }

        try {
            $sharedStrings = $this->sharedStrings($this->xml($zip, 'xl/sharedStrings.xml'));
            $sheet = $this->xml($zip, 'xl/worksheets/sheet1.xml');
            $sheetRows = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];
            $rows = [];

            foreach ($sheetRows as $sheetRow) {
                $rowNumber = (int) $sheetRow['r'];
                $cells = $this->cells($sheetRow, $sharedStrings);

                if ($rowNumber === 7) {
                    $this->validateHeaders($cells);

                    continue;
                }

                if ($rowNumber < 8 || $this->blank($cells['C'] ?? null) === null) {
                    continue;
                }

                $rows[] = [
                    'name' => $this->blank($cells['C'] ?? null),
                    'license_number' => $this->blank($cells['D'] ?? null),
                    'certificate_number' => $this->blank($cells['E'] ?? null),
                    'phone' => $this->blank($cells['F'] ?? null),
                ];
            }

            if ($rows === []) {
                throw new RuntimeException('Drivers.xlsx contains no driver rows.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $entry): SimpleXMLElement
    {
        $contents = $zip->getFromName($entry);

        if ($contents === false) {
            throw new RuntimeException("Drivers.xlsx is missing {$entry}.");
        }

        $xml = simplexml_load_string($contents);

        if ($xml === false) {
            throw new RuntimeException("Drivers.xlsx contains invalid XML in {$entry}.");
        }

        return $xml;
    }

    /** @return array<int, string> */
    private function sharedStrings(SimpleXMLElement $xml): array
    {
        $strings = [];

        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $strings[] = implode('', array_map(
                static fn (SimpleXMLElement $part): string => (string) $part,
                $parts,
            ));
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<string, ?string>
     */
    private function cells(SimpleXMLElement $row, array $sharedStrings): array
    {
        $cells = [];

        foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
            $attributes = $cell->attributes();
            preg_match('/^[A-Z]+/', (string) $attributes['r'], $matches);
            $column = $matches[0];
            $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
            $value = $valueNodes === [] ? null : (string) $valueNodes[0];

            if ((string) $attributes['t'] === 's' && $value !== null) {
                $value = $sharedStrings[(int) $value] ?? null;
            }

            $cells[$column] = $value;
        }

        return $cells;
    }

    /** @param array<string, ?string> $cells */
    private function validateHeaders(array $cells): void
    {
        $expected = [
            'C' => 'NAME',
            'D' => 'LICENSE NUMBER',
            'E' => 'CERTIFICATE NUMBER',
            'F' => 'PHONE NUMBER',
        ];

        foreach ($expected as $column => $header) {
            if (trim((string) ($cells[$column] ?? '')) !== $header) {
                throw new RuntimeException("Unexpected Drivers.xlsx header in column {$column}.");
            }
        }
    }

    private function blank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
