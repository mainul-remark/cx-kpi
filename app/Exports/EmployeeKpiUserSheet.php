<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The tab of one user on the KPI workbook: a heading row, a line per activity and the total score.
 *
 * A zero is kept as a figure, so an activity nothing was reported for does not look left out.
 */
class EmployeeKpiUserSheet implements FromArray, WithTitle, WithEvents, WithStrictNullComparison
{
    public const POSITION = 'Doer';

    public const HEADINGS = [
        'Employee Name',
        "Position\n(Doer/ Supervisor)",
        'KPI Title',
        'Target',
        'Actual Result',
        'Achievement %',
        'Weight (%)',
        'Weighted Score',
        'Assessment Frequency',
    ];

    private const COLUMN_WIDTHS = ['A' => 22, 'B' => 18, 'C' => 44, 'D' => 14, 'E' => 16, 'F' => 16, 'G' => 13, 'H' => 17, 'I' => 14];

    private const HEADING_FILL = 'FFF2CC';

    private const HEADING_COLOR = 'C55911';

    /**
     * @param  array<string, mixed>|null  $user  the user's row of the KPI sheet, nobody on a workbook without users
     * @param  list<array{title: string, target: int, actual: int, achievement: float, weight: float, score: float}>  $lines
     */
    public function __construct(private string $title, private ?array $user, private array $lines, private string $frequency)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $rows = [self::HEADINGS];

        if ($this->user === null) {
            return $rows;
        }

        $name = self::text($this->user['name']).($this->user['employee_id'] ? "\n(".self::text($this->user['employee_id']).')' : '');

        if (empty($this->lines)) {
            $rows[] = [$name, self::POSITION, 'No target set in this period', null, null, null, null, null, $this->frequency];

            return $rows;
        }

        foreach ($this->lines as $index => $line) {
            $rows[] = [
                // the name and the position are written once and merged over the user's lines
                $index === 0 ? $name : null,
                $index === 0 ? self::POSITION : null,
                self::text($line['title']),
                $line['target'],
                $line['actual'],
                $line['achievement'],
                $line['weight'],
                $line['score'],
                $this->frequency,
            ];
        }

        $rows[] = [null, null, 'Total Score', null, null, null, 1, round(array_sum(array_column($this->lines, 'score')), 2), null];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lines = count($this->lines);
                $last = max(1, count($this->array()));
                $lastLine = $lines > 0 ? $lines + 1 : $last;

                foreach (self::COLUMN_WIDTHS as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getStyle('A1:I'.$last)->applyFromArray([
                    'font' => ['name' => 'Calibri', 'size' => 11],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::HEADING_COLOR]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADING_FILL]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(62);
                $sheet->freezePane('A2');

                if ($last === 1) {
                    return;
                }

                if ($lastLine > 2) {
                    $sheet->mergeCells('A2:A'.$lastLine);
                    $sheet->mergeCells('B2:B'.$lastLine);
                }

                $sheet->getStyle('D2:E'.$last)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('F2:F'.$last)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyle('G2:G'.$last)->getNumberFormat()->setFormatCode('0%');
                $sheet->getStyle('H2:H'.$last)->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle('D2:I'.$last)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($lines > 0) {
                    $sheet->getStyle('C'.$last.':I'.$last)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::HEADING_COLOR]],
                    ]);
                    $sheet->getStyle('C'.$last)->getFont()->setSize(14);
                    $sheet->getStyle('C'.$last)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }

    /**
     * A spreadsheet would run a cell starting with one of these as a formula.
     */
    private static function text(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
