<?php

namespace App\Http\Controllers\API\Chat;

use App\Model\Role;
use App\Model\User;
use App\Events\MessageChat;
use App\Model\Chat\Message;
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
class MessageController extends Controller
{

    /**
     * Get List Message 
     */

    public function index(Request $request)
    {
        $data = Message::with(['user'])->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * push message
     * @bodyParam search string search. 
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $user = $request->user();
            $message = new Message();
            $message->message = $request->message;
            $message->user_id = $user->id;
            $message->save();
            broadcast(new MessageChat($message, $user))->toOthers();
            $message->load('user');
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $message, 200, 'Success');
    }
}
