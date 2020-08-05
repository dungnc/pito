<?php

namespace App\Http\Controllers\API_PARTNER\Voucher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\PointAndVoucher\Voucher;
use App\Model\PointAndVoucher\VoucherForPartner;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
class VoucherController extends Controller
{

    /**
     * Get all voucher
     * @param 
     */
    public function index(Request $request){
        $query = Voucher::with('type_voucher','type_discount');

        if($request->voucher_code != null && $request->voucher_code != "null"){
            $query->where('voucher_code','LIKE','%'.$request->voucher_code.'%');
        }

        if($request->type_discount_id != null && $request->type_discount_id != "null"){
            $query->where('type_discount_id',$request->type_discount_id);
        }

        if($request->start_date != null && $request->start_date != "null"){
            $query->whereDate('start_date',$request->start_date);
        }

        if($request->end_date != null && $request->end_date != "null"){
            $query->whereDate('end_date',$request->end_date);
        }

        if($request->status != null){
            $date_now = date('Y-m-d');

            switch($request->status){
                case 0 : 
                    $query->whereDate('start_date','<=',$date_now);
                    $query->whereDate('end_date','>=',$date_now);
                    break;
                case 1 :
                    $query->whereDate('end_date','<',$date_now);
                    break;
                case 2 :
                    $query->whereDate('start_date','>',$date_now);
                    break;
            }
        }

        if ($request->sort_by && $request->sort_type) {
            $query = $query->orderBy($request->sort_by, $request->sort_type);
        } else {
            $query = $query->orderBy('end_date','DESC');
            $query = $query->orderBy('start_date','ASC');
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

        /**
     * Show voucher
     */
    public function show(Request $request,$id){
        $voucher = Voucher::with('partners','type_discount','type_voucher')->findOrFail($id);
        return AdapterHelper::sendResponse(true, $voucher, 200, 'success');
    }


    /**
     * Create new voucher
     */
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'voucher_code' => ['required',Rule::unique('vouchers'),],
            'type_voucher_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
        ],['voucher_code.unique' => "Mã ưu đãi đã tồn tại, xin vui lòng kiểm tra lại!"]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        $today = strtotime(date('Y-m-d'));
        $start_date = strtotime($request->start_date);
        $end_date = strtotime($request->end_date);

        if($start_date < $today){
            return AdapterHelper::sendResponse(false, 'Validator error', 400,'Ngày bắt đầu phải từ hôm nay trở đi!');
        }

        if($end_date < $today){
            return AdapterHelper::sendResponse(false, 'Validator error', 400,'Ngày kết thúc phải từ hôm nay trở đi!');
        }

        if($start_date >= $end_date){
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Ngày kết thúc phải sau ngày bắt đầu!');
        }
        DB::beginTransaction();
        try{
            $voucher = new Voucher();
            $voucher->voucher_code = $request->voucher_code;
            $voucher->type_voucher_id = $request->type_voucher_id;
            $voucher->start_date = $request->start_date;
            $voucher->end_date = $request->end_date;
            $voucher->is_active = $request->is_active;
            switch($request->type_voucher_id){
                case 1 : {
                    $voucher->value_discount = $request->value_discount;
                    $voucher->type_discount_id = $request->type_discount_id;
                    break;
                }
                case 2 : {
                    $voucher->value_discount = $request->value_discount;
                    break;
                }
                default : break;
            }
            $voucher->save();

            switch($request->apply_type_id){
                case 2 : {
                    if($request->orders_price){
                        $voucher->orders_price = $request->orders_price;
                        $voucher->save();
                    }
                    if($request->partner_id){
                        $vouchers_for_partners = new VoucherForPartner();
                        $vouchers_for_partners->voucher_id = $voucher->id;
                        $vouchers_for_partners->partner_id = $request->partner_id;
                        $vouchers_for_partners->save();
                    }
                    break;
                }
                default : break;
            }
        }catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        DB::commit();
        return AdapterHelper::sendResponse(true, $voucher, 200, 'success');
    }


    /**
     * update voucher
     */
    public function update(Request $request,$id){
        $voucher = Voucher::find($id);
        if(!$voucher){
            return AdapterHelper::sendResponse(false, 'Validator error', 400,'Voucher không tồn tại!');
        }
        DB::beginTransaction();
        try{
            if(isset($request->voucher_code))
                $voucher->voucher_code = $request->voucher_code;
            if(isset($request->type_voucher_id))
                $voucher->type_voucher_id = $request->type_voucher_id;
            if(isset($request->start_date))
                $voucher->start_date = $request->start_date;
            if(isset($request->end_date))
                $voucher->end_date = $request->end_date;
            if(isset($request->is_active))
                $voucher->is_active = $request->is_active;
            switch($request->type_voucher_id){
                case 1 : {
                    $voucher->value_discount = $request->value_discount;
                    $voucher->type_discount_id = $request->type_discount_id;
                    break;
                }
                case 2 : {
                    $voucher->value_discount = $request->value_discount;
                    break;
                }
                default : break;
            }
            $voucher->save();

            switch($request->apply_type_id){
                case 1 : {
                    $voucher->orders_price = null;
                    $voucher->save();
                    VoucherForPartner::where('voucher_id',$voucher->id)->delete();
                }
                case 2 : {
                    $voucher->orders_price = $request->orders_price;
                    $voucher->save();
                    if(isset($request->partner_id)){
                        VoucherForPartner::where('voucher_id',$voucher->id)->delete();
                        $vouchers_for_partners = new VoucherForPartner();
                        $vouchers_for_partners->voucher_id = $voucher->id;
                        $vouchers_for_partners->partner_id = $request->partner_id;
                        $vouchers_for_partners->save();
                    }else{
                        VoucherForPartner::where('voucher_id',$voucher->id)->delete();
                    }
                    break;
                }
                default : break;
            }
        }catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        DB::commit();
        return AdapterHelper::sendResponse(true, $voucher, 200, 'success');
    }


    /**
     * Delete voucher
     */
    public function destroy($id){
        $voucher = Voucher::findOrFail($id);
        VoucherForPartner::where('voucher_id',$voucher->id)->delete();
        $voucher->delete();
        return AdapterHelper::sendResponse(true, 'Delete success', 200, 'success');
    }
}
