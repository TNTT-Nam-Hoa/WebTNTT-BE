<?php

namespace App\Services\Excel\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Excel;

class LopHocInserted implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithEvents
{
    use Exportable;

    private $writerType = Excel::XLSX;
    private $data = null;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Danh Sach';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->freezePane('A2');
            },
        ];
    }

    public function headings(): array
    {
        return [
            'Mã Lớp',
            'Tên',
            'Vị Trí Học',
        ];
    }

    /**
     * @return array
     */
    public function array(): array
    {
        $results = [];
        foreach ($this->data as $item) {
            $results[] = [
                $item['id'],
                $item['ten'],
                $item['vi_tri_hoc'],
            ];
        }
        return $results;
    }
}
