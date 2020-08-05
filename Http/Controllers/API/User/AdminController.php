<?php

namespace App\Http\Controllers\API\User;

use App\Model\Role;
use App\Model\User;
use Dotenv\Regex\Success;
use App\Mail\RejectPartner;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Carbon;
use App\Model\DetailUser\Admin;
use Illuminate\Validation\Rule;
use App\Model\DetailUser\Company;
use App\Model\DetailUser\Partner;
use App\Mail\SendLinkDownloadFile;
use App\Model\DetailUser\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Model\Contract\ContractForPartner;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Contracts\User\AdminInterface;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * @group User
 *
 * APIs for User
 */
class AdminController extends Controller
{

    private $repository;
    private $repository_main;
    public function __construct(AdminInterface $admin, UserRepository $userRepository)
    {
        $this->repository = $admin;
        $this->repository_main = $userRepository;
    }

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

        if (count($data_search) == 1) {
            $data = $this->repository->get_all($request->type_role, ['*'], true);
        } else {
            if (isset($data_search['name']))
                $data_search['name'] = convert_unicode($data_search['name']);
            if (isset($data_search['email']))
                $data_search['email'] = convert_unicode($data_search['email']);
            if (isset($data_search['detail;company']))
                $data_search['detail;company'] = convert_unicode($data_search['detail;company']);
            $data = $this->repository->search($data_search, ['*'], true);
        }
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * Get User by id.
     * @bodyParam id string required The id of user. Example: 1
     */

    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return AdapterHelper::sendResponse(false, 'User Not found', 404, 'User Not found');
        }
        $data = $this->repository_main->find($id);
        $data['permissions'] = $data->getAllPermissions();
        $data['role_name'] = $data->getRoleNames()[0];
        if (!$data) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        }

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }


    /**
     * Update user info
     * Create account for customer and partner
     * @bodyParam type_role string required truyền đối tượng tham chiếu PARTNER hoặc CUSTOMER. Example: PARTNER
     * @bodyParam phone string required so dien thoai PARTNER hoặc CUSTOMER. Example: 0974922032
     * @bodyParam name string required tên của khách hàng hoặc tên nhà hàng. Example: tà vẹt
     * @bodyParam email string required email cua user. Example: user@gmail.com
     * @bodyParam password string required nên truyền mặc đinh là password. Example: password
     * @bodyParam avatar file .
     * @bodyParam descriptions string mô tả hoặc note thêm của khách hàng hoặc nhà hàng. Example: quán đẹp
     * @bodyParam address string nhập địa chỉ của khách hàng hoặc nhà hàng. Example: 2 võ thị sáu
     * @bodyParam _lat string nhập _lat nếu đối tượng là partner. Example: 106.1232
     * @bodyParam _long string nhập _long nếu đối tượng là partner. Example: 103.12352
     * @bodyParam people_contact string nhập người liên hệ nếu đối tượng là partner. Example: Thanh Quý
     * @bodyParam website string nhập website nếu đối tượng là partner. Example: now.vn
     * @bodyParam company string nhập tên công ty nếu đối tượng là khách hàng. Example: stdiohue
     */
    public function update(Request $request, $id)
    {

        $user = User::find($id);
        if (!$user)
            return AdapterHelper::sendResponse(false, 'Not found', 500, 'User not found');
        $validator = Validator::make($request->all(), [
            'email' => [
                Rule::unique('users')->ignore($id),
            ],
            'social_id' => [
                Rule::unique('users')->ignore($id)
            ],
            'phone' => [
                'required',
                Rule::unique('users')->ignore($id),
            ],
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if ($request->password && $user->type_role != "CUSTOMER") {
            if (strlen($request->password) < 8 || strlen($request->password) > 16) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, 'The length of password should be 8-16 characters');
            }
        }

        DB::beginTransaction();
        try {
            //code...
            $data_update_user = $request->all();
            unset($data_update_user['type_role']);
            unset($data_update_user['password']);
            unset($data_update_user['avatar']);
            $data_update_user['name'] = convert_unicode($data_update_user['name']);
            $user->update($data_update_user);
            if ($request->password && $request->password != "") {
                $user->password = bcrypt($request->password);
                $user->save();
                $tk = $request->user()->token();
                $user->tokens()->where('id', '<>', $tk->id)->delete();
            }
            if ($request->avatar) {
                $fileName = $user->id . Str::random(4) . "-" . time();
                $dir = User::$path . $fileName;
                $dir = AdapterHelper::upload_file($request->avatar, $dir);
                $user->avatar = env('APP_URL') . 'storage/' . $dir;
                $user->save();
            }
            if ($user->type_role == "CUSTOMER") {
                $company = null;
                if ($request->company_id === null) {
                    if ($request->tax_code && $request->company) {
                        $check_company = Company::where('tax_code', $request->tax_code)->first();
                        if ($check_company && $request->tax_code && $request->tax_code != "") {
                            $company = $check_company;
                        } else {
                            $company = new Company();
                            $company->name = convert_unicode($request->company);
                            $company->address = $request->address;
                            $company->_lat = $request->_lat;
                            $company->_long = $request->_long;
                            $company->tax_code = $request->tax_code;
                            $company->save();
                        }
                    }
                } else {
                    $company = Company::find($request->company_id);
                }
                if ($company && !$user->company()->wherePivot('company_id', $company->id)->exists()) {
                    $user->company()->attach($company);
                }
                $updates = $request->only(['description', 'point', 'company', 'receive_promotion_email', 'tax_code', 'address', '_lat', '_long']);
                if ($company) {
                    // $updates['company'] = $company->company;
                    // $updates['address'] = $company->address;
                    // $updates['_lat'] = $company->_lat;
                    // $updates['_long'] = $company->_long;
                    // $updates['tax_code'] = $company->tax_code;
                    $updates['company_id'] = $company->id;
                }

                $user->customer()->update($updates);
            }
            if ($user->type_role == "PITO_ADMIN") {
                if ($data_update_user['role']) {
                    $role = Role::find($data_update_user['role']);
                    $user->assignRole($role);
                }
                $updates = $request->only(['description', 'address', '_lat', '_long']);
                $user->admin()->update($updates);
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $user, 200, 'Success');
    }

    /**
     * create account user
     * @bodyParam role string nếu đối tượng là PITO_ADMIN thì trường role bắt buộc. Example: admin
     * @bodyParam type_role string required truyền đối tượng tham chiếu PARTNER hoặc CUSTOMER. Example: PARTNER
     * @bodyParam receive_promotion_email string required Có nhận email khuyến mãi không có= 1 không = 0. Example: 1
     * @bodyParam tax_code string Mã số thuế. Example: 1231234
     * @bodyParam phone string required so dien thoai PARTNER hoặc CUSTOMER. Example: 0974922032
     * @bodyParam name string required tên của khách hàng hoặc tên nhà hàng. Example: tà vẹt
     * @bodyParam email string required email cua user. Example: user@gmail.com
     * @bodyParam password string required nên truyền mặc đinh là password. Example: password
     * @bodyParam descriptions string mô tả hoặc note thêm của khách hàng hoặc nhà hàng. Example: quán đẹp
     * @bodyParam address string nhập địa chỉ của khách hàng hoặc nhà hàng. Example: 2 võ thị sáu
     * @bodyParam _lat string nhập _lat nếu đối tượng là partner. Example: 106.1232
     * @bodyParam _long string nhập _long nếu đối tượng là partner. Example: 103.12352
     * @bodyParam people_contact string nhập người liên hệ nếu đối tượng là partner. Example: Thanh Quý
     * @bodyParam website string nhập website nếu đối tượng là partner. Example: now.vn
     * @bodyParam company string nhập tên công ty nếu đối tượng là khách hàng. Example: stdiohue
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => [
                'required',
                Rule::unique('users'),
            ],
            'type_role' => 'required',
            'password' => 'required|min:8|max:16',
            'phone' => [
                'required',
                Rule::unique('users'),
            ],
        ]);
        $current_user = $request->user();
        // $check_role = $current_user->hasRole([User::$const_type_role['PITO_ADMIN']]);
        // if (!$check_role) {
        //     return AdapterHelper::sendResponse(false, 'Permission denied', '403', 'Permission denied');
        // }

        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            if ($request->type_role == "CUSTOMER") {
                $data = $this->repository_main->create_account_for_customer($request->all());
            }
            if ($request->type_role == "PARTNER") {
                $data = $this->repository_main->create_account_for_partner($request->all());
            }
            if ($request->type_role == "PITO_ADMIN") {
                if (!$request->role) {
                    return AdapterHelper::sendResponse(false, 'Validation error', 500, 'Hãy truyền thêm tên role vì đây là PITO ADMIN, ví dụ: admin');
                }
                $data = $this->repository_main->create_account_for_pito_admin($request->all());
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

    /**
     * Delete users
     * 
     */
    public function destroy(Request $request, $id)
    {
        $current_user = $request->user();
        // $check_role = $current_user->hasRole([User::$const_type_role['PITO_ADMIN']]);
        // if (!$check_role) {
        //     return AdapterHelper::sendResponse(false, 'Permission denied', '403', 'Permission denied');
        // }
        $user =  User::findOrFail($id);
        $user->email = null;
        $user->social_id = null;
        $user->save();

        $user->delete();
        return AdapterHelper::sendResponse(true, "success", 200, 'Success');
    }

    /**
     * Update active.
     * @bodyParam user_id int required id user. Example: 1
     * @bodyParam is_active int required active: 1, deactive: 0. Example: 1
     */

    public function update_active(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'is_active' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $user = User::find($request->user_id);
            if (!$user) {
                return AdapterHelper::sendResponse(false, 'User not found', 404, 'User not found');
            }
            if ($user->type_role == User::$const_type_role['CUSTOMER']) {
                $customer = Customer::where('customer_id', $request->user_id)->first();
                if (!$customer)
                    return AdapterHelper::sendResponse(false, 'Customer not found', 404, 'Customer not found');
                $customer->is_active = $request->is_active;
                $customer->save();
            }
            if ($user->type_role == User::$const_type_role['PITO_ADMIN']) {
                $admin = Admin::where('pito_user_id', $request->user_id)->first();
                if (!$admin)
                    return AdapterHelper::sendResponse(false, 'Admin not found', 404, 'Admin not found');
                $admin->is_active = $request->is_active;
                $admin->save();
            }
            if ($user->type_role == User::$const_type_role['PARTNER']) {
                $partner = Partner::where('partner_id', $request->user_id)->first();
                if (!$partner)
                    return AdapterHelper::sendResponse(false, 'Partner not found', 404, 'Partner not found');
                $partner->is_active = $request->is_active;
                $partner->save();
                if (!$request->is_active) {
                    $partner = $user;
                    // Mail::to($partner->email)->send(new RejectPartner($partner));
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * Get total of users with role
     * @bodyParams : type_role role of users
     */
    public function get_total_users(Request $request)
    {
        $data = $this->repository_main->total_users($request->type_role);
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Send mail link download
     * @bodyParam contract_id int contract_id
     * @bodyParam partner_id int required id cua partner
     */
    // public function sendMailDownloadForPartner(Request $request){

    //     $validator = Validator::make($request->all(), [
    //         'contract_id' => 'required',
    //         'partner_id' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
    //     }
    //     $partner = User::find($request->partner_id);
    //     if(!$partner){
    //         return AdapterHelper::sendResponse(false, 'Not found', 404, 'User not found');
    //     }
    //     $contract = ContractForPartner::find($request->contract_id);
    //     if(!$contract){
    //         return AdapterHelper::sendResponse(false, 'Not found', 404, 'Contract not found');
    //     }
    //     Mail::to($partner->email)->to(new SendLinkDownloadFile($contract->file,$contract->name));
    //     return AdapterHelper::sendResponse(false,'Success',200,'Successs');
    // }
}
