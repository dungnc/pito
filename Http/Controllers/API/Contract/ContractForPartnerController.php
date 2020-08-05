<?php

namespace App\Http\Controllers\API\Contract;

use App\Model\Role;
use App\Model\User;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Validation\Rule;
use App\Model\DetailUser\Company;
use App\Mail\SendLinkDownloadFile;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Mail\SendContractNewPartner;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Model\Contract\ContractForPartner;

/**
 * @group Contract
 *
 * APIs for Contract
 */
class ContractForPartnerController extends Controller
{

    /**
     * Get List Contract for partner
     * @bodyParam search string search. 
     */

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $query = ContractForPartner::where('partner_id', $request->partner_id)->select('*');
        if ($request->search) {
            $query = $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }


    /**
     * Update contract for partner
     * @bodyParam file string required file. Example: base64
     * @bodyParam name string required Tên hợp đồng. Example: Hợp đồng hợp tác
     * @bodyParam description string Mô tả hợp đồng. Example: 
     * @bodyParam partner_id int required id của partner. Example: 60
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $contract  = ContractForPartner::find($id);
            if (!$contract) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Contract not found');
            }
            if ($request->file) {
                $url = explode('storage/', $contract->file);
                if (count($url) > 1) {
                    $url = $url[1];
                } else {
                    $url = $url[0];
                }
                $fileName = $request->partner_id . rand(1, 1000) . "-" . time();
                $dir = ContractForPartner::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->file, $dir, $url);
                $contract->file = env('APP_URL') . 'storage/' . $dir;
            }
            $contract->name = $request->name;
            $contract->description = $request->description;
            $contract->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $contract, 200, 'Success');
    }

    /**
     * create contract for partner
     * Update contract for partner
     * @bodyParam file string required file. Example: base64
     * @bodyParam name string required Tên hợp đồng. Example: Hợp đồng hợp tác
     * @bodyParam description string Mô tả hợp đồng. Example: 
     * @bodyParam partner_id int required id của partner. Example: 60
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'file' => 'required',
            'partner_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $contract = new ContractForPartner();
            $fileName = $request->partner_id . rand(1, 1000) . "-" . time();
            if ($request->file) {
                $dir = ContractForPartner::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->file, $dir);
                $contract->file = env('APP_URL') . 'storage/' . $dir;
            }

            $contract->name = $request->name;
            $contract->description = $request->description;
            $contract->partner_id = $request->partner_id;
            $contract->save();
            $partner = User::find($request->partner_id);
            $pito = $request->user();
            if ($pito->type_role == User::$const_type_role['PITO_ADMIN']) {
                // Mail::to($partner->email)->send(new SendContractNewPartner($partner, $pito, $contract));
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $contract, 200, 'Success');
    }

    /**
     * Send mail link download
     * @bodyParam contract_id int contract_id
     * @bodyParam partner_id int required id cua partner
     */
    public function sendMailDownloadForPartner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contract_id' => 'required',
            'partner_id' => 'required',
        ]);
        try {
            //code...
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $partner = User::find($request->partner_id);
            if (!$partner) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'User not found');
            }
            $contract = ContractForPartner::find($request->contract_id);
            if (!$contract) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Contract not found');
            }
            $partner = User::find($request->partner_id);
            $pito = $request->user();
            // Mail::to($partner->email)->send(new SendLinkDownloadFile($partner, $pito, $contract));
        } catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $partner->email, 200, 'Successs');
    }

    /**
     * Delete contract
     * @bodyParam contract_id int contract_id
     * @bodyParam partner_id int required id cua partner
     */
    public function delete(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required',
        ]);
        DB::beginTransaction();
        try {
            //code...
            if ($validator->fails()) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
            }
            $partner = User::find($request->partner_id);
            if (!$partner) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'User not found');
            }
            $contract = ContractForPartner::find($id);
            if (!$contract) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Contract not found');
            }
            $delete = AdapterHelper::delete_file($contract->file);
            $contract->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'Successs');
    }
}
