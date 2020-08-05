<?php

namespace App\Http\Controllers\API_PARTNER\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Setting\ServiceOrderDefault;
use App\Model\PartnerHas\ServiceOrderOfPartner;
use App\Model\PartnerHas\CategoryServiceOrder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Traits\AdapterHelper;
class ServiceController extends Controller
{
    /**
     * 
     */
    public function index(Request $request){
        $user = $request->user();

        $data = CategoryServiceOrder::where('partner_id',$user->id)->with(['service_order'=>function($q){
            $q->orderBy('name','asc');
        }])->whereHas('service_order')->get()->toArray();
        
     

        
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

        /**
     * Detail service for partner.
     */
    public function show(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $service = ServiceOrderOfPartner::with(['category'])->find($id);
            if (!$service)
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');

            $service->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $service, 200, 'Success');
    }

}
