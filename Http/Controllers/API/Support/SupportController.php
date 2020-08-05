<?php

namespace App\Http\Controllers\API\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Model\Support\Support;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Mail\Version2\PartnerSupport\PartnerSupport;
class SupportController extends Controller
{
    
    /**
     * get list support
     */
    public function index(Request $request){
        $query = Support::with('user');
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * show support
     */
    public function show(Request $request,$id){
        $support = Support::with('user')->findOrFail($id);
        return AdapterHelper::sendResponse(true, $support, 200, 'success');
    }

    /**
     * Create new support
     */
    public function create(Request $request){

        $validator = Validator::make($request->all(), [
            'name' => 'required',
      
            'connect_type' => 'required',
            'message' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            $support = new Support();
            $support->name = $request->name;
            $support->email = $request->email;
            $support->phone = $request->phone;
            $support->connect_type = $request->connect_type;
            $support->message = $request->message;
            $support->user_id = $request->user_id;
            $support->save();
        }catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        DB::commit();
        
        MultiMail::from('partners@pito.vn')
        ->to('partners@pito.vn')
        ->send(new PartnerSupport($support));
        
        return AdapterHelper::sendResponse(true, $support, 200, 'success');
    }

    /**
     * Update support
     */
    public function update(Request $request,$id){
        $support = Support::findOrFail($id);
        DB::beginTransaction();
        try{
            if(isset($request->name))
                $support->name = $request->name;
            if(isset($request->email))
                $support->email = $request->email;
            if(isset($request->phone))
                $support->phone = $request->phone;
            if(isset($request->connect_type))
                $support->connect_type = $request->connect_type;
            if(isset($request->message))
                $support->message = $request->message; 
            $support->save();
        }catch (\Throwable $th) {
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        DB::commit();
    }

    /**
     * Delete support
     */
    public function destroy($id){
        $support = Support::findOrFail($id);
        $support->delete();
        return AdapterHelper::sendResponse(true, 'Delete success', 200, 'success');
    }
}
