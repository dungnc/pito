<?php

namespace App\Http\Controllers\API;

use App\Model\User;
use App\Model\Role;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use App\Model\DetailUser\Customer;
use App\Model\DetailUser\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Contracts\AuthInterface;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * @group Authencation
 *
 * APIs for Authencation
 */
class AuthController extends Controller
{

    private $repository;
    public function __construct(AuthInterface $user_repository)
    {
        $this->repository = $user_repository;
    }

    /**
     * Login.
     * 
     * @bodyParam password string required The password of user. Example: password
     * @bodyParam email string required The email of user. Example: quyproi51vn@gmail.com
     */

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'email' => 'required|email'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        $response = $this->repository->login($request->email, $request->password);
        return AdapterHelper::sendResponse($response['status'], $response['data'], $response['status_code'], $response['message']);
    }

    /**
     * Logout.
     */

    public function logout(Request $request)
    {
        $this->repository->logout($request->user());
        return AdapterHelper::sendResponse(true, 'Logout successfully', 200, "Logout successfully");
    }
    /**
     * Register default
     * @bodyParam email string required The email of user. Example: quyproi51vn@gmail.com
     * @bodyParam password string required The password of user. Example: password
     * @bodyParam phone string required The phone of user. Example: 0974922032
     * @bodyParam name string required The name of user. Example: Quy NT
     * @bodyParam type_role string required The type_role of user (CUSTOMER,PITO_ADMIN,PARTNER). Example: PITO_ADMIN
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required',
            'phone' => 'required',
            'name' => 'required',
            'type_role' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        //create user
        $check_email_unique = User::where('email', $request->email)->where('social_type', User::$const_social_type['DEFAULT'])->exists();
        if ($check_email_unique) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Email already exists.');
        }
        $list_role = Role::all();
        $list_role = $list_role->map->name->all();
        if (!in_array($request->type_role, $list_role)) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Type role no support');
        }
        $input = $request->all();
        $input['phone_code'] = $request->phone_code;
        $this->repository->register_default($input);
        $mes = 'Unauthenticated account. Please check your email.';
        return AdapterHelper::sendResponse(true, $mes, 200, 'success');
    }

    /**
     * Register Social
     * @bodyParam email string required The email of user. Example: quyproi51vn@gmail.com
     * @bodyParam social_id string required The social_id of user. Example: social_id
     * @bodyParam social_type string required The social_type of user(FACEBOOK,GOOGLE). Example: FACEBOOK
     * @bodyParam phone string The phone of user. Example: 0974922032
     * @bodyParam name string required The name of user. Example: Quy NT
     * @bodyParam type_role string required The type_role of user (CUSTOMER,PITO_ADMIN,PARTNER). Example: PITO_ADMIN
     */
    public function register_via_social(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'social_id' => 'required',
            'social_type' => 'required',
            'type_role' => 'required',

            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_role = Role::all();
        $list_role = $list_role->map->name->all();
        if (!in_array($request->type_role, $list_role)) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Type role no support');
        }
        $params = $request->all();
        $params['password'] = "password";
        $params["phone"] = $request->phone ? $request->phone : "";
        $data = $this->repository->register_social($params);
        if ($data['user'] == null) {
            return AdapterHelper::sendResponse(false, 'This account has not been registered', 400, 'This account has not been registered');
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Get user profile via token
     * 
     */
    public function getUserViaToken(Request $request)
    {
        $locale = $request->locale ? $request->locale : config('translatable')['fallback_locale'];
        $user = $request->user();
        $data = $user->toArray();
        // if($user->detail){
        //     foreach($user->detail->translations as $key => $value){
        //         $data['detail'][$key] = $value[$locale];
        //     }
        // }
        $data['permissions'] = $user->getAllPermissions();
        $data['role_name'] = $user->getRoleNames()[0];
        // dd($user->detail->getTranslations['description']);
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Verify code
     * @bodyParam verify_code string required The verify_code of user. Example: VXz13
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'verify_code' => 'required',
        ]);

        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = User::where('verify_code', $request->verify_code)->first();
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'Error verifi code fail.', 400, 'Error verifi code fail.');
        }
        $now = time();
        if ((int) $user->verify_expires - $now <= 0) {
            return AdapterHelper::sendResponse(false, 'Error verifi code expires.', 400, 'Error verifi code expires.');
        }

        $date = date("Y-m-d g:i:s");
        $user->email_verified_at = $date;

        $user->save();

        $token_field = $user->createToken('Login Token')->accessToken;
        return AdapterHelper::sendResponse(true, $token_field, 200, 'success');
    }
    /**
     * Resend the email verification notification.
     * @bodyParam email string required The email of user. Example: quyproi51vn
     */
    public function resend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = User::where('email', $request->email)->first();
        if (!$user)
            return AdapterHelper::sendResponse(false, 'error', 404, 'user not found.');
        $user->verify_code = ($user->id + 20) . Str::random(3);

        $user->verify_expires = strtotime(Carbon::now()->addMinutes(10));
        $user->save();
        if ($user->hasVerifiedEmail()) {
            return AdapterHelper::sendResponse(false, 'User already have verified email!', 422, 'User already have verified email!');
            // return redirect($this->redirectPath());
        }
        $this->repository->sendApiEmailVerificationNotification($user);
        return AdapterHelper::sendResponse(true, 'The notification has been resubmitted', 200, 'success');
        // return back()->with(‘resent’, true);
    }

    /**
     * send mail forgot_passowrd
     * @bodyParam email string required The email of user. Example: quyproi51vn@gmail.com
     */
    public function forgot_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = User::where('email', $request->email)->first();
        if (!$user)
            return AdapterHelper::sendResponse(false, 'error', 404, 'user not found.');
        $this->repository->sendEmailResetingPassword($user);
        return AdapterHelper::sendResponse(true, 'Reset successful! Please check your mailbox to create new password!', 200, 'success');
    }

    /**
     * forgot reset password
     * @bodyParam email string required The email of user. Example: quyproi51vn@gmail.com
     * @bodyParam token string required The token reseting. Example: zxzxc34dfgsxzczczx...
     * @bodyParam password string required The password reset. Example: 123456
     */
    public function reset_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $user = User::where('email', $request->email)->first();
        if (!$user)
            return AdapterHelper::sendResponse(false, 'error', 404, 'user not found.');
        $response = $this->repository->resetingPassword($request->token, $user, $request->password);
        return AdapterHelper::sendResponse($response['status'], $response['data'], $response['status_code'], $response['message']);
    }

    public function update(Request $request, $id)
    {
        // check role
        $user = Auth::user();
        if (!($user->hasRole('Super Admin') || $user->id == $id))
            return AdapterHelper::sendResponse(false, 'Permission denied', 403, 'Permission denied');
        // end check role
        $user = User::find($id);
        if (!$user) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        }
        if (isset($request->name))
            $user->name = $request->name;
        if (isset($request->phone)) {
            $phone = AdapterHelper::detect_phone($request->phone);
            $check_phone = User::where('phone', $phone['phone'])->where('id', '<>', $user->id)->first();
            if ($check_phone) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Phone number already exists.');
            }
            $user->phone = $phone['phone'];
            $user->code_phone = $phone['code_phone'];
        }
        if (isset($request->email)) {
            if ($user->social_type == 'default') {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, "Can't edit email.");
            }
            $user->email = $request->email;
        }
        if (isset($request->gender))
            $user->gender = $request->gender;

        if (isset($request->avatar)) {
            if ($request->hasFile('avatar')) {
                $dir = dirname($_SERVER["SCRIPT_FILENAME"]) . '/upload/user/';
                $file = $request->avatar;
                $fileName = $user->id . "-" . rand(1, 1000) . "-" . time() . '.' . $file->getClientOriginalExtension();
                $file->move($dir, $fileName);
                if ($file->getClientOriginalExtension() == "jpeg") {
                    AdapterHelper::image_fix_orientation($dir . $fileName);
                }
                $fileName = $fileName;
                $user->avatar = env('APP_URL', 'api-pito.stdiohue.com') . User::$path . $fileName;
            } else {
                return AdapterHelper::sendResponse(false, 'error', 400, 'upload avatar error!');
            }
        }

        $user->save();

        $type_detail = null;
        if ($user->type_role == 'CUSTOMER') {
            $type_detail = Customer::where('customer_id', $user->id)->first();
        } else if ($user->type_role == 'PARTNER')
            $type_detail = Partner::where('partner_id', $user->id)->first();
        else $type_detail = Admin::where('pito_user_id', $user->id)->first();
        if (!$type_detail) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        }
        $type_detail->update($request->all());

        $user = User::find($id);
        $locale = $request->locale ? $request->locale : config('translatable')['fallback_locale'];
        $data = $user->toArray();
        foreach ($user->detail->translations as $key => $value) {
            $data['detail'][$key] = $value[$locale];
        }
        // dd($user->detail->getTranslations['description']);
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }
}
