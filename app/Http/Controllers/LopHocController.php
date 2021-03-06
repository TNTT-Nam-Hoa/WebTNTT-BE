<?php

namespace App\Http\Controllers;

use App\DiemDanh;
use App\DiemSo;
use App\KhoaHoc;
use App\LopHoc;
use App\Services\Excel\Exports\LopHocInserted;
use App\Services\Excel\Imports\LopHocImport;
use App\Services\Library;
use Carbon\Carbon;
use DB;
use Excel;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LopHocController extends Controller
{
    /**
     * @return Response
     */
    public function getDanhSachTheoKhoa($khoaHocID, LopHoc $lopHoc)
    {
        $lopHoc = $lopHoc->where('khoa_hoc_id', $khoaHocID)->locDuLieu()->get()->load('huynh_truong')->map(function ($c) {
            $c['ten'] = $c->taoTen();
            return $c;
        });

        return response()->json([
            'data' => $lopHoc,
        ]);
    }

    /**
     * @param  LopHoc  $lopHoc
     * @return Response
     */
    public function getThongTin(LopHoc $lopHoc)
    {
        $lopHoc->load(['huynh_truong', 'hoc_vien']);
        $lopHoc->ten = $lopHoc->taoTen();

        return response()->json($lopHoc);
    }

    public function postTapTin(Request $request, Library $library)
    {
        if (!$request->hasFile('file')) {
            return response()->json([
                'error' => 'Không tìm thấy tập tin.',
            ], 400);
        }

        $importer = new LopHocImport();
        Excel::import($importer, $request->file('file'));
        try {
            return response()->json([
                'data' => $importer->getResult(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Kiểm tra lại định dạng tập tin.',
            ], 400);
        }
    }

    public function postTao(Request $request)
    {
        if (!$request->has('data')) {
            return response()->json([
                'error' => 'Không thấy dữ liệu.',
            ], 400);
        }

        $resultArr  = [];
        $tmpItemArr = $request->data;
        $khoaHocID  = KhoaHoc::hienTaiHoacTaoMoi()->id;

        try {
            DB::beginTransaction();
            foreach ($tmpItemArr as $tmpItem) {
                try {
                    $lopHoc = LopHoc::create(array_merge([
                        'khoa_hoc_id' => $khoaHocID,
                    ], $tmpItem));
                } catch (Exception $e) {
                    continue;
                }

                $lopHoc->ten = $lopHoc->taoTen();
                $resultArr[] = $lopHoc->toArray();
            }

            $fileName = 'TaoMoi_LopHoc_'.Carbon::now()->format('d-m-Y_h-i-s').'.xlsx';
            $result   = Excel::store(new LopHocInserted($resultArr), $fileName);

            if (!$result) {
                throw new Exception('Can not create file');
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return response()->json([
            'data' => $resultArr,
            'file' => $fileName,
        ]);
    }

    /**
     * @param  LopHoc  $lopHoc
     * @return Response
     */
    public function postUpdate(LopHoc $lopHoc)
    {
        try {
            $lopHoc->fill(\Request::all());
            $lopHoc->save();
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Kiểm tra lại thông tin.',
            ], 400);
        }

        return $this->getThongTin($lopHoc);
    }

    public function postXoa(LopHoc $lopHoc)
    {
        $lopHoc->delete();
        return response()->json();
    }

    /**
     * @param  LopHoc  $lopHoc
     * @return Response
     */
    public function postThemThanhVien(LopHoc $lopHoc, Request $request)
    {
        if (!$request->has('id') || !is_array($request->id)) {
            return response()->json([
                'error' => 'Không thấy dữ liệu.',
            ], 400);
        }

        $this->themThanhVien($lopHoc, $request->id);
        return $this->getThongTin($lopHoc);
    }

    public function themThanhVien(LopHoc $lopHoc, $arrID)
    {
        $lopHoc->thanh_vien()->attach($arrID);
        $lopHoc->tinhTongKet();
    }

    /**
     * @param  LopHoc  $lopHoc
     * @param  tmpItem  $tmpItem
     * @return Response
     */
    public function postXoaThanhVien(LopHoc $lopHoc, Request $request)
    {
        if (!$request->has('id') || !is_array($request->id)) {
            return response()->json([
                'error' => 'Không thấy dữ liệu.',
            ], 400);
        }

        $arrID = $request->id;
        $lopHoc->thanh_vien()->detach($arrID);
        $lopHoc->tinhTongKet();
        return $this->getThongTin($lopHoc);
    }

    /**
     * @param  LopHoc  $lopHoc
     * @param  DiemDanh  $diemDanh
     * @return Response
     */
    public function getChuyenCan(LopHoc $lopHoc, DiemDanh $diemDanh, Request $request, Library $library)
    {
        if (!$request->has('ngay_hoc')) {
            return response()->json([
                'error' => 'Không thấy dữ liệu.',
            ], 400);
        }

        $arrHocVien = $lopHoc->hoc_vien()->pluck('tai_khoan_id')->toArray();
        $sDate      = $this->getSundayFromADate($lopHoc, $library, $request->ngay_hoc);

        if (!$sDate) {
            return response()->json([
                'error' => 'Ngày không hợp lệ.',
            ], 400);
        }

        return response()->json([
            'data'   => $diemDanh->getChuyenCanData($arrHocVien, $sDate),
            'sunday' => $sDate,
        ]);
    }

    /**
     * @param  LopHoc  $lopHoc
     * @param  Library  $library
     * @param $ngay_hoc
     * @return mixed|null
     */
    private function getSundayFromADate(LopHoc $lopHoc, Library $library, $ngay_hoc)
    {
        // Trong pham vi 6 ngay
        $endDate   = strtotime($ngay_hoc);
        $startDate = strtotime('-6day', $endDate);
        // Chỉ hiện ngày trong phạm vi của Khóa Học Tương Ứng
        if ($startDate < strtotime($lopHoc->khoa_hoc->ngay_bat_dau)) {
            $startDate = strtotime($lopHoc->khoa_hoc->ngay_bat_dau);
        }
        if ($endDate > strtotime($lopHoc->khoa_hoc->ngay_ket_thuc)) {
            $endDate = strtotime($lopHoc->khoa_hoc->ngay_ket_thuc);
        }
        $startDate = date('Y-m-d', $startDate);
        $endDate   = date('Y-m-d', $endDate);
        // Lay ngay Chua Nhat
        $aDate = $library->SpecificDayBetweenDates($startDate, $endDate);

        return empty($aDate) ? null : array_shift($aDate);
    }

    public function postChuyenCan(LopHoc $lopHoc, Request $request, DiemDanh $diemDanh)
    {
        $diemDanh->luuChuyenCan($lopHoc, $request->thieu_nhi, $request->ngay);

        return response()->json($request);
    }

    public function getHocLuc(LopHoc $lopHoc, Request $request, DiemSo $diemSo)
    {
        $arrHocVien = $lopHoc->hoc_vien()->pluck('tai_khoan_id')->toArray();

        return response()->json([
            'data' => $diemSo->getHocLuc($arrHocVien, $lopHoc->khoa_hoc, $request->dot),
            'dot'  => $request->dot,
        ]);
    }

    public function postHocLuc(LopHoc $lopHoc, Request $request, DiemSo $diemSo)
    {
        $diemSo->luuHocLuc($lopHoc, $request->thieu_nhi, $request->dot, $request->lan);

        return response()->json(true);
    }

    public function postXepHang(LopHoc $lopHoc, Request $request)
    {
        foreach ($request->thieu_nhi as $arrTmp) {
            $hocVien = $lopHoc->hoc_vien()
                ->where('tai_khoan_id', $arrTmp['id'])
                ->first();

            $hocVien->pivot->xep_hang = $arrTmp['xep_hang'];
            $hocVien->pivot->ghi_chu  = $arrTmp['ghi_chu'];
            $hocVien->pivot->save();
        }

        return response()->json(true);
    }

    public function postNhanXet(LopHoc $lopHoc, Request $request)
    {
        foreach ($request->thieu_nhi as $arrTmp) {
            $hocVien = $lopHoc->hoc_vien()
                ->where('tai_khoan_id', $arrTmp['id'])
                ->first();

            $hocVien->pivot->nhan_xet = $arrTmp['nhan_xet'];
            $hocVien->pivot->save();
        }
        return response()->json(true);
    }
}
