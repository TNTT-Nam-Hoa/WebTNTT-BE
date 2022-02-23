<?php

namespace App\Services\Excel\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Excel;
use Request;

class TaiKhoanExport implements WithMultipleSheets
{
    use Exportable;

    private $writerType = Excel::XLSX;

    public function __construct()
    {
    }

    public function sheets(): array
    {
        $sheets = [
            new TaiKhoanSheet(),
        ];

        if (Request::get('khoa')) {
            $sheets[] = new TongKetSheet();
        }

        return $sheets;
    }
}
