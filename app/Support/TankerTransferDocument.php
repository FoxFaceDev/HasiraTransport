<?php

namespace App\Support;

use App\Models\TankerTransfer;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use RuntimeException;

class TankerTransferDocument
{
    private const SUPPORTED_TYPES = ['sale_with_truck', 'sale_line_only', 'truck_change'];

    public function render(TankerTransfer $transfer): string
    {
        if (! in_array($transfer->change_type, self::SUPPORTED_TYPES, true)) {
            throw new RuntimeException('Unsupported tanker transfer document type.');
        }

        $pdf = new Mpdf([
            'format' => 'A4',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font' => 'kjino',
            'fontDir' => [public_path('fonts')],
            'fontdata' => [
                'kjino' => [
                    'R' => 'KJino.TTF',
                    'B' => 'KJino.TTF',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'useSubstitutions' => true,
        ]);

        $pdf->SetTitle($this->title($transfer->change_type));
        $pdf->AddPage();
        $pdf->WriteHTML($this->styles(), HTMLParserMode::HEADER_CSS);
        $pdf->Image(resource_path('pdf/templates/header.png'), 0, 0, 210, 41.5, 'png');
        $pdf->WriteFixedPosHTML($this->metadata($transfer), 8, 46, 194, 26, 'hidden');
        $pdf->WriteFixedPosHTML($this->documentBody($transfer), 15, 78, 180, 205, 'hidden');

        return $pdf->Output('', 'S');
    }

    private function metadata(TankerTransfer $transfer): string
    {
        $number = $this->value($transfer->document_number);
        $date = $this->escape($transfer->transferred_at?->format('Y-m-d'));

        return <<<HTML
            <div style="height: 16mm; box-sizing: border-box; border: 0.25mm solid #111; padding: 2.5mm 3mm; direction: ltr; font-family: kjino; font-size: 9pt; line-height: 2.35;">
                <div>No: <strong style="margin-left: 2mm;">{$number}</strong></div>
                <div>Date: <strong style="margin-left: 2mm;">{$date}</strong></div>
            </div>
        HTML;
    }

    private function documentBody(TankerTransfer $transfer): string
    {
        return match ($transfer->change_type) {
            'sale_with_truck' => $this->saleWithTruck($transfer),
            'sale_line_only' => $this->saleLineOnly($transfer),
            'truck_change' => $this->truckChange($transfer),
        };
    }

    private function saleWithTruck(TankerTransfer $transfer): string
    {
        $seller = $this->value($transfer->previous_owner);
        $buyer = $this->value($transfer->new_owner);
        $plate = $this->value($transfer->previous_plate_number);
        $vin = $this->value($transfer->previous_vin);
        $type = $this->value($transfer->previous_truck_type);
        $model = $this->value($transfer->previous_truck_model);
        $date = $this->value($transfer->transferred_at?->format('Y-m-d'));

        return <<<HTML
            <div class="document sale-document" dir="rtl">
                <div class="document-title">بەڵگەنامەی کڕین و فرۆشتنی هێڵی گواستنەوەی نەوتی خاوی حەسیرە</div>
                <div class="summary-lines">
                    <div>لایەنی یەکەم فرۆشیار {$this->parenthesized($seller)} خاوەنی ئۆتۆمبێلی ژمارە {$this->parenthesized($plate)}</div>
                    <div>بە ژمارە شاسی {$this->parenthesized($vin)} جۆری {$this->parenthesized($type)} مۆدیلی {$this->parenthesized($model)}</div>
                    <div class="summary-gap">لایەنی دووەم کڕیاری ئۆتۆمبێلی لایەنی یەکەم {$this->parenthesized($buyer)} لە بەرواری {$this->parenthesized($date)}</div>
                    <div>هەردوولا ڕێکەوتن لەسەر:</div>
                </div>
                {$this->saleTerms()}
                <div class="sale-table-spacer"></div>
                {$this->partyTable($transfer)}
                {$this->committeeFooter()}
            </div>
        HTML;
    }

    private function saleLineOnly(TankerTransfer $transfer): string
    {
        return <<<HTML
            <div class="document sale-document line-only-document" dir="rtl">
                <div class="document-title">بەڵگەنامەی کڕین و فرۆشتنی هێڵی گواستنەوەی نەوتی خاوی حەسیرە</div>
                <div class="vehicle-party">
                    <div class="party-line">لایەنی یەکەم فرۆشیار {$this->parenthesized($this->value($transfer->previous_owner))} خاوەنی ئۆتۆمبێلی ژمارە {$this->parenthesized($this->value($transfer->previous_plate_number))} بە ژمارە شاسی {$this->parenthesized($this->value($transfer->previous_vin))}</div>
                    <div class="party-line indent-line">جۆری {$this->parenthesized($this->value($transfer->previous_truck_type))} مۆدیلی {$this->parenthesized($this->value($transfer->previous_truck_model))}</div>
                    <div class="party-line second-party">لایەنی دووەم کڕیار {$this->parenthesized($this->value($transfer->new_owner))} خاوەنی ئۆتۆمبێلی ژمارە {$this->parenthesized($this->value($transfer->new_plate_number))} بە ژمارە شاسی {$this->parenthesized($this->value($transfer->new_vin))}</div>
                    <div class="party-line indent-line">جۆری {$this->parenthesized($this->value($transfer->new_truck_type))} مۆدیلی {$this->parenthesized($this->value($transfer->new_truck_model))}</div>
                </div>
                {$this->saleTerms()}
                <div class="sale-table-spacer"></div>
                {$this->partyTable($transfer)}
                {$this->committeeFooter()}
            </div>
        HTML;
    }

    private function truckChange(TankerTransfer $transfer): string
    {
        return <<<HTML
            <div class="document truck-change-document" dir="rtl">
                <div class="document-title">بەڵگەنامەی گۆڕینی خاوەندارێتی هێڵی گواستنەوەی نەوتی خاوی حەسیرە</div>
                <div class="summary-lines truck-summary">
                    <div>خاوەنی ئۆتۆمبێل {$this->parenthesized($this->value($transfer->previous_owner))} بە ئۆتۆمبێلی ژمارە {$this->parenthesized($this->value($transfer->previous_plate_number))}</div>
                    <div>بە ژمارە شاسی {$this->parenthesized($this->value($transfer->previous_vin))} جۆری {$this->parenthesized($this->value($transfer->previous_truck_type))} مۆدیلی {$this->parenthesized($this->value($transfer->previous_truck_model))}.</div>
                    <div class="change-statement">هەستاوە بە گۆڕینی ئۆتۆمبێلەکەی بۆ مۆدیلی بەرزتر.</div>
                </div>
                <ul class="terms truck-terms">
                    <li>هیچ کەس مافی خاوەندارێتی نەماوە لەسەر ئۆتۆمبێلی ئاماژە پێکراو (پێشتر)</li>
                    <li>بەرپرسیارم لەوەی هیچکەس بە ئۆتۆمبێلی ئاماژە پێکراو (پێشتر) داوای هێڵ بکات لە حەسیرە</li>
                </ul>
                <div class="truck-table-spacer"></div>
                {$this->truckComparisonTable($transfer)}
                {$this->committeeFooter()}
            </div>
        HTML;
    }

    private function saleTerms(): string
    {
        return <<<'HTML'
            <ul class="terms sale-terms">
                <li>لایەنی یەکەم هیچ مافی خاوەندارێتی نەماوە لەسەر ئۆتۆمبێلی ئاماژە پێکراو.</li>
                <li>لایەنی یەکەم هێڵ دەکات بەناوی لایەنی دووەم وە هیچ مافێکی خاوەندارێتی لەسەر هێڵەکە نامێنێت.</li>
                <li>لایەنی دووەم هیچ قەرزێکی لایەنی یەکەم لا نەماوە و خاوەندارێتی هێڵەکە وەردەگرێت بە پێی بەڵگەنامەکان.</li>
            </ul>
        HTML;
    }

    private function partyTable(TankerTransfer $transfer): string
    {
        $seller = $this->partyColumn('لایەنی یەکەم فرۆشیار', $transfer->previous_owner, [
            'ژمارەی پێناس' => $transfer->seller_national_id,
            'کۆدی ئاسایش' => $transfer->seller_security_code,
            'الوکیل' => $transfer->seller_agent,
            'رقم الوکالة' => $transfer->seller_agency_number,
            'تاریخ' => $transfer->seller_document_date?->format('Y-m-d'),
        ]);
        $buyer = $this->partyColumn('لایەنی دووەم کڕیار', $transfer->new_owner, [
            'ژمارەی پێناس' => $transfer->buyer_national_id,
            'کۆدی ئاسایش' => $transfer->buyer_security_code,
            'الوکیل' => $transfer->buyer_agent,
            'رقم الوکالة' => $transfer->buyer_agency_number,
            'تاریخ' => $transfer->buyer_document_date?->format('Y-m-d'),
        ]);

        return <<<HTML
            <table class="two-column-table party-table" dir="rtl"><tr><td class="outer-cell">{$seller}</td><td class="outer-cell">{$buyer}</td></tr></table>
        HTML;
    }

    /** @param array<string, mixed> $details */
    private function partyColumn(string $heading, mixed $name, array $details): string
    {
        $rows = '';

        foreach ($details as $label => $value) {
            $rows .= '<tr><td class="detail-label">'.$this->escape($label).'/</td><td class="detail-value">'.$this->value($value).'</td></tr>';
        }

        return '<div class="column-heading">'.$this->escape($heading).' '.$this->parenthesized($this->value($name)).'</div>'
            .'<table class="party-content"><tr><td class="party-details"><table class="detail-table">'.$rows.'</table></td>'
            .'<td class="party-evidence-cell"><table class="party-evidence"><tr><td class="evidence-box">وێنە</td></tr><tr><td class="evidence-gap"></td></tr><tr><td class="evidence-box stamp-box">پەنجە مۆر</td></tr></table></td></tr></table>';
    }

    private function truckComparisonTable(TankerTransfer $transfer): string
    {
        $old = $this->truckColumn('ئۆتۆمبێلی پێشتر', [
            'ئۆتۆمبێلی ژمارە' => $transfer->previous_plate_number,
            'ژمارە شاسی' => $transfer->previous_vin,
            'جۆری' => $transfer->previous_truck_type,
            'مۆدیلی' => $transfer->previous_truck_model,
            'ڕەنگ' => $transfer->previous_truck_color,
        ]);
        $new = $this->truckColumn('ئۆتۆمبێلی نوێ', [
            'ئۆتۆمبێلی ژمارە' => $transfer->new_plate_number,
            'ژمارە شاسی' => $transfer->new_vin,
            'جۆری' => $transfer->new_truck_type,
            'مۆدیلی' => $transfer->new_truck_model,
            'ڕەنگ' => $transfer->new_truck_color,
        ], true);

        return <<<HTML
            <table class="two-column-table truck-table" dir="rtl"><tr><td class="outer-cell">{$old}</td><td class="outer-cell">{$new}</td></tr></table>
        HTML;
    }

    /** @param array<string, mixed> $details */
    private function truckColumn(string $heading, array $details, bool $withEvidence = false): string
    {
        $rows = '';

        foreach ($details as $label => $value) {
            $rows .= '<tr><td class="detail-label">'.$this->escape($label).'/</td><td class="detail-value truck-value">'.$this->value($value).'</td></tr>';
        }

        $evidence = $withEvidence
            ? '<table class="evidence-table truck-evidence"><tr><td class="evidence-box stamp-box">پەنجە مۆر</td><td class="evidence-box">وێنە</td></tr></table>'
            : '';

        return '<div class="column-heading">'.$this->escape($heading).'</div><table class="detail-table truck-details">'.$rows.'</table>'.$evidence;
    }

    private function committeeFooter(): string
    {
        return <<<'HTML'
            <div class="committee">
                <div class="committee-title">لێژنەی بەناوکردنی هێڵی گواستنەوەی نەوتی خاوی حەسیرە</div>
                <div class="committee-role-spacer"></div>
                <table><tr><td>ئەندام</td><td>ئەندام</td><td>سەرۆک لێژنە</td></tr></table>
            </div>
        HTML;
    }

    private function parenthesized(string $value): string
    {
        return '<span class="parenthesized">(&nbsp;<strong>'.$value.'</strong>&nbsp;)</span>';
    }

    private function value(mixed $value): string
    {
        return filled($value) ? $this->escape($value) : '-';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return <<<'HTML'
            <style>
                .document { color: #000; direction: rtl; font-family: kjino; font-size: 10pt; line-height: 1.65; }
                .document-title { margin: 0 0 5mm; text-align: center; font-size: 13pt; font-weight: bold; }
                .summary-lines, .vehicle-party { text-align: right; line-height: 1.85; }
                .summary-gap, .second-party { margin-top: 3mm; }
                .parenthesized { white-space: nowrap; }
                .parenthesized strong { display: inline; padding: 0 0.8mm; font-size: 10.5pt; font-weight: bold; }
                .change-statement { margin-top: 4mm; text-align: center; }
                .terms { margin: 4mm 5mm 4mm 0; padding: 0 5mm 0 0; line-height: 1.55; }
                .terms li { margin-bottom: 0.7mm; padding-right: 1mm; }
                .two-column-table { width: 100%; border-collapse: collapse; table-layout: fixed; direction: rtl; }
                .two-column-table .outer-cell { width: 50%; height: 73mm; padding: 0; vertical-align: top; border: 0.25mm solid #111; }
                .column-heading { height: 10mm; box-sizing: border-box; border-bottom: 0.25mm solid #111; padding: 0.8mm 2mm; text-align: right; font-size: 10.5pt; line-height: 1.4; }
                .detail-table { width: 92%; margin: 2mm auto 0; border-collapse: collapse; table-layout: fixed; direction: rtl; }
                .detail-table td { border: 0; padding: 0.2mm 1mm; vertical-align: middle; line-height: 1.45; }
                .detail-label { width: 48%; text-align: right; font-size: 9.5pt; }
                .detail-value { width: 52%; direction: ltr; text-align: left; font-family: kjino; font-size: 10pt; font-weight: bold; overflow-wrap: anywhere; }
                .evidence-table { direction: ltr; width: 49mm; margin: 12mm 0 0 2mm; border-collapse: separate; border-spacing: 2mm 0; }
                .evidence-table .evidence-box { width: 21mm; height: 22mm; padding: 0; border: 0.35mm solid #102c5c; text-align: center; vertical-align: middle; font-size: 8.5pt; line-height: 1.5; }
                .party-content { width: 100%; border-collapse: collapse; table-layout: fixed; direction: rtl; }
                .party-content td { padding: 0; border: 0; vertical-align: top; }
                .party-content .party-details { width: 70%; }
                .party-content .party-evidence-cell { width: 30%; }
                .party-content .detail-table { width: 96%; margin-top: 2mm; }
                .party-content .detail-value { box-sizing: border-box; padding-left: 5mm; text-align: right; }
                .party-evidence { width: 22mm; margin: 4mm auto 0; border-collapse: collapse; }
                .party-evidence .evidence-box { width: 21mm; height: 22mm; padding: 0; border: 0.35mm solid #102c5c; text-align: center; vertical-align: middle; font-size: 8.5pt; line-height: 1.5; }
                .party-evidence .evidence-gap { height: 5mm; padding: 0; border: 0; }
                .committee { margin-top: 5mm; font-size: 10pt; }
                .committee-title { text-align: center; }
                .committee-role-spacer { height: 11mm; }
                .committee table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                .committee td { width: 33.333%; border: 0; text-align: center; }
                .truck-summary { margin: 0 9mm; }
                .truck-terms { margin-top: 5mm; margin-bottom: 5mm; }
                .truck-table { margin-top: 18mm; }
                .truck-table-spacer { height: 4.1mm; }
                .truck-table .outer-cell { height: 83mm; }
                .truck-details { margin-top: 2mm; }
                .truck-value { font-size: 10.5pt; }
                .truck-evidence { margin-top: 23mm; }
                .party-table { margin-top: 30mm; }
                .sale-table-spacer { height: 4.4mm; }
                .party-table .outer-cell { height: 75mm; }
                .line-only-document .vehicle-party { line-height: 1.65; }
                .line-only-document .second-party { margin-top: 2mm; }
                .line-only-document .terms { margin-top: 3mm; margin-bottom: 3mm; }
            </style>
        HTML;
    }

    private function title(string $type): string
    {
        return match ($type) {
            'sale_with_truck' => 'فرۆشتنی هێڵ لەگەڵ بارهەڵگر',
            'sale_line_only' => 'فرۆشتنی هێڵ تەنها',
            'truck_change' => 'گۆڕینی بارهەڵگر',
        };
    }
}
