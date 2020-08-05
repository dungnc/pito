<?php

namespace App\Http\Controllers\API;

use App\Helpers\Shorty;
use App\Model\User;
use App\Model\Location\City;
use App\Model\Location\Ward;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Validation\Rule;
use App\Model\Location\District;
use App\Model\DetailUser\Company;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationOrderToSlack;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;


/**
 * @group Location
 *
 * APIs for search location
 */
class LocationController extends Controller
{


    public function get_url_by_short_link(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query_url' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $query = $request->query_url;
        $hostname = config('app.url_front') . "shortLink";
        $chars = config('hashing.short_link_hash');
        $salt = config('app.name');
        $padding = 5;
        $shorty = new Shorty($hostname);
        $shorty->set_chars($chars);
        $shorty->set_salt($salt);
        $shorty->set_padding($padding);
        if (preg_match('/^([a-zA-Z0-9]+)$/', $query, $matches)) {
            $id = Shorty::decode($matches[1]);
            $result = Shorty::fetch($id);
            if ($result) {
                Shorty::update($id);
                return AdapterHelper::sendResponse(true, $result, 200, 'success');
            } else {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
            }
        }
        return AdapterHelper::sendResponse(false, 'error ', 502, 'error');
    }
    /**
     * Get search City.
     * @bodyParam search string  dung de search name va type cua city. Example: Ho chi Minh
     * 
     */
    public function getCity(Request $request)
    {
        $query = City::select('*');
        if ($request->search) {
            $query = $query->where('name', 'LIKE', '%' . $request->search . "%")
                ->orWhere('type', 'LIKE', '%' . $request->search . "%");
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get search District.
     * @bodyParam search string  dung de search name va type cua distric. Example: Quan 1
     * 
     */
    public function getDistrict(Request $request, $city_id)
    {
        $query = District::where('city_id', $city_id);
        if ($request->search) {
            $query = $query->where('name', 'LIKE', '%' . $request->search . "%")
                ->orWhere('type', 'LIKE', '%' . $request->search . "%");
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get search Ward.
     * @bodyParam search string  dung de search name va type cua ward. Example: phuong truong an
     * 
     */
    public function getWard(Request $request, $district_id)
    {
        $query = Ward::where('district_id', $district_id);
        if ($request->search) {
            $query = $query->where('name', 'LIKE', '%' . $request->search . "%")
                ->orWhere('type', 'LIKE', '%' . $request->search . "%");
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get search company.
     * @bodyParam search string search được các field( name, tax_code, address,id). Example: stdiohue
     * 
     */
    public function index_company(Request $request)
    {
        $query = Company::select('*');
        if ($request->search) {
            $query = $query->where('company', 'LIKE', '%' . $request->search . "%")
                ->orWhere('address', 'LIKE', '%' . $request->search . "%")
                ->orWhere('id', 'LIKE', '%' . $request->search . "%")
                ->orWhere('tax_code', 'LIKE', '%' . $request->search . "%");
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }
    /**
     * Create company 
     * @bodyParam user_id string truyen user_id. Example: 2
     * @bodyParam company_id int truyen id cua company neu tao moi thi truyen null. Example: null
     * @bodyParam company string ten. Example: stdiohue
     * @bodyParam address string  . Example: 143/27A Phan Boi chau
     * @bodyParam _lat string  dung de search name va type cua ward. Example: 123
     * @bodyParam _long string required  Example: 123
     * @bodyParam tax_code string required mã số thuế. Example: 123123
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'user_id' => 'required',
            'company' => 'required',
            '_lat' => 'required',
            '_long' => 'required',
            // 'tax_code' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $data = $request->only(['user_id', 'company_id', 'company', 'address', '_lat', '_long', 'tax_code']);
            // $user = User::find($data['user_id']);
            $company = null;
            if (!$request->company_id) {
                $check = Company::where('tax_code', $data['tax_code'])->first();
                if ($check && $data['tax_code'] && $data['tax_code'] != "") {
                    $company = $check;
                } else {
                    $company = new Company();
                    $company->company = $data['company'];
                    $company->address = $data['address'];
                    $company->_lat = $data['_lat'];
                    $company->_long = $data['_long'];
                    $company->tax_code = $data['tax_code'];
                    $company->save();
                }
            } else {
                $company = Company::find($data['company_id']);
            }
            // $user->company()->attach($company);

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $company, 200, 'success');
    }

    /**
     * Create company and attach company for user
     * @bodyParam user_id string truyen user_id. Example: 2
     * @bodyParam company_id int truyen id cua company neu tao moi thi truyen null. Example: null
     * @bodyParam company string ten. Example: stdiohue
     * @bodyParam address string  . Example: 143/27A Phan Boi chau
     * @bodyParam _lat string  dung de search name va type cua ward. Example: 123
     * @bodyParam _long string required  Example: 123
     * @bodyParam tax_code string required mã số thuế. Example: 123123
     */
    public function create_and_attach(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'company' => 'required',
            '_lat' => 'required',
            '_long' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $data = $request->only(['user_id', 'company_id', 'company', 'address', '_lat', '_long', 'tax_code']);
            $user = User::find($data['user_id']);
            $company = null;
            if (!$request->company_id) {
                $check = Company::where('tax_code', $data['tax_code'])->first();
                if ($check && $data['tax_code'] && $data['tax_code'] != "") {
                    $company = $check;
                } else {
                    $company = new Company();
                    $company->company = $data['company'];
                    $company->address = $data['address'];
                    $company->_lat = $data['_lat'];
                    $company->_long = $data['_long'];
                    $company->tax_code = $data['tax_code'];
                    $company->save();
                }
            } else {
                $company = Company::find($data['company_id']);
            }
            if (!$user->company()->wherePivot('company_id', $company->id)->exists())
                $user->company()->attach($company);

            if ($company) {
                $updates['company'] = $company->company;
                $updates['address'] = $company->address;
                $updates['_lat'] = $company->_lat;
                $updates['_long'] = $company->_long;
                $updates['tax_code'] = $company->tax_code;
                $updates['company_id'] = $company->id;
            }
            $user->customer()->update($updates);

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $company, 200, 'success');
    }
    /**
     * Get company by user.
     */
    public function get_company_of_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        try {
            //code...
            $user = User::find($request->user_id);
            if (!$user) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'User Not Found');
            }
            $data = $user->company()->get();
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }

    /**
     * Check tax code API
     * @return : company
     * @throw 
     */
    public function check_tax_code(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tax_code' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $company = Company::where('tax_code', $request->tax_code)->first();
        return AdapterHelper::sendResponse(true, $company, 200, 'success');
    }

    public function test_send_noti_to_slack(Request $request)
    {
        try {
            //code...
            $user = $request->user();
            $user->notify(new NotificationOrderToSlack('ddd'));
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined error', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
