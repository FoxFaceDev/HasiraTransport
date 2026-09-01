<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\Setting;
use App\Models\Tanker;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ImportTransportWorkbooks extends Command
{
    protected $signature = 'transport:import-workbooks
        {drivers=Drivers.xlsx : Path to the drivers workbook}
        {owners=squence owner.xlsx : Path to the sequence owners workbook}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import drivers and sequence-owner trucks as independent records';

    public function handle(): int
    {
        $driverPath = $this->absolutePath($this->argument('drivers'));
        $ownerPath = $this->absolutePath($this->argument('owners'));

        $drivers = $this->parseDrivers($this->readSheet($driverPath, 1));
        $tankers = $this->parseTankers($this->readSheet($ownerPath, 2));

        $duplicateOwnerPhones = $this->duplicateSummary(array_column($tankers, 'sequence_owner_phone'));
        $duplicateDriverPhones = $this->duplicateSummary(array_column($drivers, 'phone'));
        $duplicatePlates = $this->duplicateSummary(array_column($tankers, 'plate_number'), true);

        $this->table(
            ['Dataset', 'Rows', 'Repeated phone values', 'Rows using repeated phones'],
            [
                ['Drivers', count($drivers), $duplicateDriverPhones['values'], $duplicateDriverPhones['rows']],
                ['Sequence owners / trucks', count($tankers), $duplicateOwnerPhones['values'], $duplicateOwnerPhones['rows']],
            ]
        );

        if ($duplicatePlates['values'] > 0) {
            $this->error("The truck workbook contains {$duplicatePlates['values']} duplicate plate number(s), affecting {$duplicatePlates['rows']} rows.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Validation passed. No database records were changed.');

            return self::SUCCESS;
        }

        if (Driver::query()->exists() || Tanker::query()->exists()) {
            $this->error('Import stopped because the drivers or tankers table is not empty.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($drivers, $tankers) {
            $now = now();

            foreach (array_chunk($drivers, 500) as $chunk) {
                Driver::query()->insert(array_map(fn (array $driver) => [
                    ...$driver,
                    'blocked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            foreach (array_chunk($tankers, 500) as $chunk) {
                Tanker::query()->insert(array_map(fn (array $tanker) => [
                    ...$tanker,
                    'driver_id' => null,
                    'blocked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            $currentLimit = (int) (Setting::query()->where('key', 'max_tankers')->value('value') ?? 1039);
            Setting::query()->updateOrCreate(
                ['key' => 'max_tankers'],
                ['value' => (string) max(1039, $currentLimit, count($tankers))]
            );
        }, 3);

        $this->info('Import completed successfully. Drivers and sequence owners were not linked.');

        return self::SUCCESS;
    }

    private function parseDrivers(array $rows): array
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['NAME', 'LICENSE NUMBER', 'CERTIFICATE NUMBER', 'PHONE NUMBER']);
        $columns = array_flip($headers);
        $drivers = [];

        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            $name = $this->cell($row, $columns['NAME']);
            $license = $this->cell($row, $columns['LICENSE NUMBER']);
            $certificate = $this->cell($row, $columns['CERTIFICATE NUMBER']);
            $phone = $this->cell($row, $columns['PHONE NUMBER']);

            if ($name === '' && $license === '' && $certificate === '' && $phone === '') {
                continue;
            }

            if ($name === '') {
                throw new RuntimeException('Drivers.xlsx has a row without a driver name at Excel row '.($headerIndex + $offset + 2).'.');
            }

            $drivers[] = [
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'license_number' => $license !== '' ? $license : null,
                'has_certificate' => $certificate !== '',
                'certificate_number' => $certificate !== '' ? $certificate : null,
            ];
        }

        return $drivers;
    }

    private function parseTankers(array $rows): array
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['SEQUENCE', 'SEQUENCE OWNER', 'TRUCK PLATE NUM', 'VIN', 'PHONE NUMBER']);
        $columns = array_flip($headers);
        $truckTypeColumn = $columns['PHONE NUMBER'] + 1;
        $colourColumn = $columns['COLOUR'] ?? ($truckTypeColumn + 1);
        $tankers = [];

        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            $sequence = $this->cell($row, $columns['SEQUENCE']);
            $owner = $this->cell($row, $columns['SEQUENCE OWNER']);
            $plate = $this->cell($row, $columns['TRUCK PLATE NUM']);
            $vin = $this->cell($row, $columns['VIN']);
            $phone = $this->cell($row, $columns['PHONE NUMBER']);
            $truckType = $this->cell($row, $truckTypeColumn);
            $colour = $this->cell($row, $colourColumn);

            if ($sequence === '' && $owner === '' && $plate === '' && $vin === '' && $phone === '' && $truckType === '' && $colour === '') {
                continue;
            }

            $excelRow = $headerIndex + $offset + 2;
            foreach (['sequence' => $sequence, 'plate number' => $plate, 'truck type' => $truckType] as $field => $value) {
                if ($value === '') {
                    throw new RuntimeException("squence owner.xlsx has a row without {$field} at Excel row {$excelRow}.");
                }
            }

            $tankers[] = [
                'sequence_number' => $sequence,
                'sequence_owner' => $owner !== '' ? $owner : null,
                'sequence_owner_phone' => $phone !== '' ? $phone : null,
                'plate_number' => $plate,
                'vin' => $vin !== '' ? $vin : null,
                'truck_type' => $truckType,
                'truck_color' => $colour !== '' ? $colour : null,
            ];
        }

        return $tankers;
    }

    private function readSheet(string $path, int $sheetNumber): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException("Could not open workbook: {$path}");
        }

        try {
            $sharedStrings = [];
            $sharedXml = $archive->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                [$document, $xpath] = $this->xml($sharedXml);
                foreach ($xpath->query('//x:si') as $item) {
                    $value = '';
                    foreach ($xpath->query('.//x:t', $item) as $textNode) {
                        $value .= $textNode->textContent;
                    }
                    $sharedStrings[] = $value;
                }
            }

            $sheetXml = $archive->getFromName("xl/worksheets/sheet{$sheetNumber}.xml");
            if ($sheetXml === false) {
                throw new RuntimeException("Workbook {$path} does not contain sheet {$sheetNumber}.");
            }

            [, $xpath] = $this->xml($sheetXml);
            $rows = [];

            foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
                $row = [];
                foreach ($xpath->query('./x:c', $rowNode) as $cellNode) {
                    $column = $this->columnIndex($cellNode->getAttribute('r'));
                    $type = $cellNode->getAttribute('t');
                    $valueNode = $xpath->query('./x:v', $cellNode)->item(0);
                    $value = $valueNode?->textContent ?? '';

                    if ($type === 's' && $value !== '') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = '';
                        foreach ($xpath->query('./x:is//x:t', $cellNode) as $textNode) {
                            $value .= $textNode->textContent;
                        }
                    }

                    $row[$column] = $this->clean($value);
                }

                if ($row !== []) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            $archive->close();
        }
    }

    private function findHeader(array $rows, array $required): array
    {
        foreach ($rows as $index => $row) {
            $headers = [];
            foreach ($row as $column => $value) {
                $headers[$column] = strtoupper($this->clean($value));
            }

            if (count(array_intersect($required, $headers)) === count($required)) {
                return [$index, $headers];
            }
        }

        throw new RuntimeException('The expected workbook header row could not be found.');
    }

    private function duplicateSummary(array $values, bool $caseInsensitive = false): array
    {
        $counts = [];
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $key = $caseInsensitive ? strtolower($value) : $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $duplicates = array_filter($counts, fn (int $count) => $count > 1);

        return [
            'values' => count($duplicates),
            'rows' => array_sum($duplicates),
        ];
    }

    private function absolutePath(string $path): string
    {
        $candidate = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        return $resolved;
    }

    private function xml(string $contents): array
    {
        $document = new DOMDocument;
        $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return [$document, $xpath];
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function cell(array $row, int $column): string
    {
        return $this->clean($row[$column] ?? '');
    }

    private function clean(mixed $value): string
    {
        return trim(str_replace("\u{00A0}", ' ', (string) $value));
    }
}
