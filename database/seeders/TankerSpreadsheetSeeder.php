<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Tanker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class TankerSpreadsheetSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('squence owner.xlsx');

        if (! is_file($path)) {
            throw new RuntimeException("Truck workbook not found at {$path}.");
        }

        $rows = $this->readRows($path);
        $driversByPhone = Driver::query()->get()->groupBy(
            fn (Driver $driver): string => $this->normalizePhone($driver->phone),
        );
        $matched = 0;
        $unmatched = 0;
        $ambiguous = 0;

        DB::transaction(function () use ($rows, $driversByPhone, &$matched, &$unmatched, &$ambiguous): void {
            foreach ($rows as $row) {
                $drivers = $driversByPhone->get($this->normalizePhone($row['sequence_owner_phone']), collect());
                $driverId = null;

                if ($drivers->count() === 1) {
                    $driverId = $drivers->first()->id;
                    $matched++;
                } elseif ($drivers->isEmpty()) {
                    $unmatched++;
                } else {
                    $ambiguous++;
                }

                Tanker::query()->updateOrCreate(
                    ['plate_number' => $row['plate_number']],
                    [...$row, 'driver_id' => $driverId],
                );
            }
        });

        $this->command?->info(count($rows).' trucks imported from squence owner.xlsx.');
        $this->command?->line("Driver matches: {$matched}; unmatched: {$unmatched}; ambiguous: {$ambiguous}.");
    }

    /**
     * @return array<int, array{sequence_number: string, sequence_owner: string, sequence_owner_phone: string, plate_number: string, vin: string, truck_type: string, truck_color: string}>
     */
    private function readRows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open {$path}.");
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheet = $this->importSheet($zip);
            $sheetRows = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];
            $rows = [];
            $plates = [];

            foreach ($sheetRows as $sheetRow) {
                $rowNumber = (int) $sheetRow['r'];
                $cells = $this->cells($sheetRow, $sharedStrings);

                if ($rowNumber === 1) {
                    $this->validateHeaders($cells);

                    continue;
                }

                if ($rowNumber < 2 || $this->blank($cells['A'] ?? null) === null) {
                    continue;
                }

                $row = [
                    'sequence_number' => $this->required($cells['A'] ?? null, 'SEQUENCE', $rowNumber),
                    'sequence_owner' => $this->required($cells['B'] ?? null, 'SEQUENCE OWNER', $rowNumber),
                    'plate_number' => $this->required($cells['C'] ?? null, 'TRUCK PLATE NUM', $rowNumber),
                    'vin' => $this->required($cells['D'] ?? null, 'VIN', $rowNumber),
                    'sequence_owner_phone' => $this->required($cells['E'] ?? null, 'PHONE NUMBER', $rowNumber),
                    'truck_type' => $this->required($cells['F'] ?? null, 'TYPE AND MODEL', $rowNumber),
                    'truck_color' => $this->required($cells['G'] ?? null, 'COLOUR', $rowNumber),
                ];
                $plateKey = mb_strtolower($row['plate_number']);

                if (isset($plates[$plateKey])) {
                    throw new RuntimeException("Duplicate plate number on workbook rows {$plates[$plateKey]} and {$rowNumber}.");
                }

                $plates[$plateKey] = $rowNumber;
                $rows[] = $row;
            }

            if ($rows === []) {
                throw new RuntimeException('squence owner.xlsx contains no truck rows.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function importSheet(ZipArchive $zip): SimpleXMLElement
    {
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $targets = [];

        foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            $targets[(string) $relationship['Id']] = 'xl/'.ltrim((string) $relationship['Target'], '/');
        }

        foreach ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [] as $sheet) {
            if ((string) $sheet['name'] !== 'Import Data') {
                continue;
            }

            $relationshipAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $entry = $targets[(string) $relationshipAttributes['id']] ?? null;

            if ($entry !== null) {
                return $this->xml($zip, $entry);
            }
        }

        throw new RuntimeException('squence owner.xlsx is missing the Import Data sheet.');
    }

    private function xml(ZipArchive $zip, string $entry): SimpleXMLElement
    {
        $contents = $zip->getFromName($entry);

        if ($contents === false || ($xml = simplexml_load_string($contents)) === false) {
            throw new RuntimeException("Unable to read {$entry} from squence owner.xlsx.");
        }

        return $xml;
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];

        foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') ?: [] as $item) {
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
            } elseif ((string) $attributes['t'] === 'inlineStr') {
                $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                $value = implode('', array_map(
                    static fn (SimpleXMLElement $part): string => (string) $part,
                    $parts,
                ));
            }

            $cells[$column] = $value;
        }

        return $cells;
    }

    /** @param array<string, ?string> $cells */
    private function validateHeaders(array $cells): void
    {
        $expected = [
            'A' => 'SEQUENCE',
            'B' => 'SEQUENCE OWNER',
            'C' => 'TRUCK PLATE NUM',
            'D' => 'VIN',
            'E' => 'PHONE NUMBER',
            'F' => 'TYPE AND MODEL',
            'G' => 'COLOUR',
        ];

        foreach ($expected as $column => $header) {
            if (trim((string) ($cells[$column] ?? '')) !== $header) {
                throw new RuntimeException("Unexpected Import Data header in column {$column}.");
            }
        }
    }

    private function required(?string $value, string $column, int $row): string
    {
        return $this->blank($value) ?? throw new RuntimeException("Blank {$column} value on workbook row {$row}.");
    }

    private function blank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', strtr((string) $phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]));

        return strlen($digits) === 11 && str_starts_with($digits, '0') ? substr($digits, 1) : $digits;
    }
}
