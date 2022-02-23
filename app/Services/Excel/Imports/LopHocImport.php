<?php

namespace App\Services\Excel\Imports;

use App\KhoaHoc;
use App\LopHoc;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LopHocImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected $data = [];

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    /**
     * $row = [
     *      0 => "Ngành"
     *      1 => "Cấp"
     *      2 => "Đội"
     *      3 => "Vị Trí Học"
     * ];
     *
     * @param  Collection  $rows
     * @return array
     */
    public function collection(Collection $rows)
    {
        $khoaId = KhoaHoc::hienTaiHoacTaoMoi()->id;
        $lops   = LopHoc::where('khoa_hoc_id', $khoaId)->get();
        $rows   = $rows->where('nganh', '<>', null)
            ->where('cap', '<>', null)
            ->where('doi', '<>', null);

        foreach ($rows as $index => $row) {
            $lop = $lops->filter(function ($lh) use ($row) {
                return $lh->nganh == $row['nganh'] && $lh->cap == $row['cap'] && $lh->doi == $row['doi'];
            })->first();

            if (!$lop) {
                $this->data[] = $row;
            }
        }

        return $this->data;
    }

    /**
     * @return array
     */
    public function getResult()
    {
        return $this->data;
    }
}
