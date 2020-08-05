<?php

namespace App\Http\Controllers\API\Order;

use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\Order\ChangeRequestOrder;
use Illuminate\Support\Facades\Validator;

/**
 * @group Order History
 *
 * APIs for Order History
 */
class RequestChangeOrderController extends Controller
{
    public function index(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'order_id' => 'required',
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $data = ChangeRequestOrder::where('order_id', $request->order_id);
            if ($request->sort_by && $request->sort_type) {
                $data->orderBy($request->sort_by, $request->sort_type);
            } else {
                $data->orderBy('created_at', 'desc');
            }
            $data = $data->get();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }
    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'is_handle' => 'required',
            ]);
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            ChangeRequestOrder::find($request->id)->update(['is_handle' => $request->is_handle]);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
