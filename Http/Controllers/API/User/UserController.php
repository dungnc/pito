<?php

namespace App\Http\Controllers\API\User;

use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Carbon;
use App\Mail\VerifyEmailSuccess;
use App\Model\DetailUser\Partner;
use App\Model\DetailUser\Customer;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Contracts\User\AdminInterface;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * @group User
 *
 * APIs for User
 */
class UserController extends Controller
{


    /**
     * Get List user.
     * @bodyParam type_role string required The role of user. Example: CUSTOMER,PITO_ADMIN,PARTNER
     */

    public function index(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'type_role' => 'required'
        ]);
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'User Not found', 404, 'User Not found');
        }
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_role = Role::all();
        $list_role = $list_role->map->name->all();
        if (!in_array($request->type_role, $list_role)) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Type role no support');
        }

        $data_search = $request->all();
        unset($data_search['page']);

        if (count($data_search) ==  1) {
            $data = $this->repository->get_all($request->type_role, ['*'], true);
        } else {
            $data = $this->repository->search($data_search, ['*'], true);
        }
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * Get User by id.
     * @bodyParam id string required The id of user. Example: 1
     */

    public function show(Request $request, $id)
    { }

    /**
     * sync user in active campaint.
     * @bodyParam id string required The id of user. Example: 1
     */

    public function sync_user(Request $request)
    {
        $api_key = '0d6f10e9c81f6a0becd75d5c597f36f79c28ccf312e4c391d4813f19a4716977bf460b9f';
        $client = new Client();
        $list_group_customer = ['PITO | Khách Yêu Cầu Gọi Từ Website', 'PITO | Christmas 2018'];
        $list_group_partner = ['PITO | Partner', 'PITO | Partner - Application'];
        $list_id_customer = ["12", "26"];
        $list_id_partner = ["36", "35"];
        $params = [
            'api_action' => 'contact_list',
            'api_key' => $api_key,
            'api_output' => 'json',
            'ids' => 'all',
            'page' => $request->page ? $request->page : 0,
            'full' => '1'
        ];
        $query = "";
        foreach ($params as $key => $value) $query .= urlencode($key) . '=' . urlencode($value) . '&';
        $query = rtrim($query, '& ');
        $api = 'https://cocorico84860.api-us1.com/admin/api.php?';
        $url = $api . $query;
        $res = $client->request('GET', $url);
        $response_data = $res->getBody()->getContents();
        $response_data = json_decode($response_data, true);
        $data = [
            'customer' => 0,
            'partner' => 0
        ];
        DB::beginTransaction();
        try {
            //code...
            foreach ($response_data as $key => $value) {
                if ($key == 'result_code' || $key == 'result_message' || $key == 'result_output')
                    continue;
                if (strlen(strstr($value['listslist'], "12")) > 0 || strlen(strstr($value['listslist'], "26")) > 0) {

                    if (!User::where('user_id_active_campaign', $value['id'])->exists()) {
                        $user = new User();
                        $user->name = $value['last_name'] . " " . $value['first_name'];
                        $user->email = $value['email'];
                        $user->phone = $value['phone'];
                        $user->password = bcrypt('password');
                        $user->social_type = User::$const_social_type['DEFAULT'];
                        $user->last_list = $value['listslist'];
                        $user->user_id_active_campaign = $value['id'];
                        $user->type_role = User::$const_type_role['CUSTOMER'];
                        $user->save();
                        $user->assignRole('CUSTOMER');
                        $customer = new Customer();
                        $customer->point = 0;
                        $customer->customer_id = $user->id;
                        if (count($value['geo'])) {
                            $customer->address = $value['geo'][0]['city'] . " " . $value['geo'][0]['state'] . " " . $value['geo'][0]['country'];
                            $customer->_lat = $value['geo'][0]['lat'];
                            $customer->_long = $value['geo'][0]['lon'];
                        }
                        $customer->save();
                        $data['customer'] += 1;
                    }
                }
                if (strlen(strstr($value['listslist'], "35")) > 0 || strlen(strstr($value['listslist'], "36")) > 0) {
                    if (!User::where('user_id_active_campaign', $value['id'])->exists()) {
                        $user = new User();
                        $user->name = $value['customer_acct_name'];
                        $user->email = $value['email'];
                        $user->phone = $value['phone'];
                        $user->password = bcrypt('password');
                        $user->social_type = User::$const_social_type['DEFAULT'];
                        $user->last_list = $value['listslist'];
                        $user->user_id_active_campaign = $value['id'];
                        $user->type_role = User::$const_type_role['PARTNER'];
                        $user->save();
                        $user->assignRole('PARTNER');

                        $partner = new Partner();
                        $partner->point = 0;
                        $partner->partner_id = $user->id;
                        $partner->people_contact = $value['last_name'] . " " . $value['first_name'];
                        if (count($value['geo'])) {
                            $partner->address = $value['geo'][0]['city'] . " " . $value['geo'][0]['state'] . " " . $value['geo'][0]['country'];
                            $partner->_lat = $value['geo'][0]['lat'];
                            $partner->_long = $value['geo'][0]['lon'];
                        }
                        $partner->save();
                        $data['partner'] += 1;
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    public function confirm_email(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'verify_code' => 'required',
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = User::where('verify_code', $request->verify_code)->where('email', $request->email)->first();
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'Error verifi code fail.', 400, 'Error verifi code fail.');
        }
        if ($user->email_verified_at !== null) {
            return view('alert.confirm-proposale');
        }
        $date = date("Y-m-d g:i:s");
        $user->email_verified_at = $date;
        $user->save();
        // Mail::to($user->email)->send(new VerifyEmailSuccess($user,''));
        return view('alert.confirm-proposale');
    }
}
