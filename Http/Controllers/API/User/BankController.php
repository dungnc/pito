<?php

namespace App\Http\Controllers\API\User;

use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use App\Model\DetailUser\Partner;
use App\Model\DetailUser\Customer;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\Contract\Bank;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * @group User
 *
 * APIs for User
 */
class BankController extends Controller
{

    /**
     * Update active.
     * @bodyParam list_bank json required list bank. Example: [{id:null,owner_name,bank_key,bank_name,card_number}]
     * @bodyParam user_id int required . Example: 33
     */
    public function update_or_create_full_of_user(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'list_bank' => 'required',
            'user_id' => 'required'
        ]);
        if ($validation->fails()) {
            return AdapterHelper::sendResponse(false, 'Validation error', 400, $validation->errors());
        }
        DB::beginTransaction();
        try {
            //code...
            $list_bank_new = collect(json_decode($request->list_bank));
            $list_bank_original = Bank::where('user_id', $request->user_id)->get();
            $list_bank_original_id = $list_bank_original->map->id->all();
            $list_bank_new_id = $list_bank_new->map->id->all();
            $list_bank_delete_id = array_diff($list_bank_original_id, $list_bank_new_id);
            $list_bank_delete_id = array_values($list_bank_delete_id);
            Bank::destroy($list_bank_delete_id);

            $list_bank_update_or_create_id = array_diff($list_bank_new_id, $list_bank_delete_id);
            $list_bank_update_or_create = [];
            foreach ($list_bank_update_or_create_id as $key => $value) {
                $list_bank_update_or_create[] = $list_bank_new[$key];
            }
            // dd();
            $list_bank_update_or_create = json_decode(json_encode($list_bank_update_or_create), true);
            foreach ($list_bank_update_or_create as $key => $value) {
                $value['user_id'] = $request->user_id;
                if (!$value['id']) {
                    unset($value['id']);
                    Bank::create($value);
                } else {
                    Bank::where('id', $value['id'])->update($value);
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return AdapterHelper::ViewAndLogError($th, $request);
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    public function delete($id)
    {
        $bank = Bank::find($id)->delete();
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
