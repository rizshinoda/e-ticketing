<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketDisruptionArraySheet implements
    FromArray,
    WithTitle,
    WithStyles,
    WithColumnWidths
{
    public function __construct(
        protected string $sheetTitle,
        protected array $rows,
        protected array $headerRows = [1],
        protected array $widths = [],
        protected bool $autoFilter = true,
        protected string $freezeAt = 'A2',
        protected array $wrapTextColumns = [],
        protected bool $autoHeight = false,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function columnWidths(): array
    {
        return $this->widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        $dimension = "A1:{$lastColumn}{$lastRow}";

        $sheet->getStyle($dimension)
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getDefaultRowDimension()->setRowHeight(21);
        // Wrap text pada kolom tertentu.
        foreach ($this->wrapTextColumns as $column) {
            if ($lastRow >= 2) {
                $sheet->getStyle(
                    "{$column}2:{$column}{$lastRow}"
                )->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            }
        }

        // Sesuaikan tinggi baris dengan isi teks.
        if ($this->autoHeight) {
            for ($row = 2; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(-1);
            }
        }
        // Styling header.
        foreach ($this->headerRows as $row) {
            if ($row > $lastRow) {
                continue;
            }

            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                ->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => [
                            'rgb' => 'FFFFFF',
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '1F2937',
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

            $sheet->getRowDimension($row)->setRowHeight(28);
        }

        // Bekukan baris judul/header.
        $sheet->freezePane($this->freezeAt);

        // Filter otomatis untuk sheet data.
        if ($this->autoFilter && $lastRow >= 1) {
            $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        }

        return [];
    }
}
