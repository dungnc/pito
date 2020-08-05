<?php

namespace App\Http\Controllers\API\Service;

use App\Model\User;
use App\Model\Order\Order;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\PartnerHas\CategoryServiceOrder;
use Illuminate\Support\Facades\Validator;
use App\Model\Setting\ServiceOrderDefault;
use App\Model\PartnerHas\ServiceOrderOfPartner;

/**
 * @group service 2
 *
 * APIs for service
 */
class ServiceForPartnerController extends Controller
{
    /**
     * Get List service cua partner.
     * * @bodyParam option string chuyen api sang chê độ get theo category nếu là catgory.Example: catgory
     */
    public function index(Request $request, $partner_id)
    {
        $data = ServiceOrderOfPartner::with(['category'])
            ->where('partner_id', $partner_id)
            ->orderBy('id', 'desc')
            ->get();
        if ($request->option == 'category') {
            $data = CategoryServiceOrder::with(['service_order'])
                ->where('partner_id', $partner_id)
                ->orderBy('id', 'asc')
                ->get();
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Get List service cua partner.
     * * @bodyParam option string chuyen api sang chê độ get theo category nếu là catgory.Example: catgory
     */
    public function index_list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_partner_id = json_decode($request->partner_id);
        if ($list_partner_id) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json fail');
        }
        $data = ServiceOrderOfPartner::with(['category', 'partner'])
            ->whereIn('partner_id', $list_partner_id)
            ->orderBy('partner_id', 'asc')
            ->get();

        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }
    /**
     * Create service for partner.
     * @bodyParam partner_id int id của partner.Example: 1
     * @bodyParam name date . Example: dịch vụ vận chuyển
     * @bodyParam DVT string Đơn vị tính người cái bàn.Example: người
     * @bodyParam json_amount_price string đoạn json số lượng giá. Example: [{"amount":10,"price":30000},{"amount":20,"price":45000}]
     * @bodyParam description string mo ta dich vu. Example: dịch vụ của partner
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            'partner_id' => 'required',
            'name' => 'required',
            'DVT' => 'required',
            'json_amount_price' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if (!json_decode($request->json_amount_price)) {
            return AdapterHelper::sendResponse(false, 'Validation error', 400, 'The field json amount price fail');
        }

        DB::beginTransaction();
        try {
            //code...
            $service = new ServiceOrderOfPartner();
            $service->partner_id = $request->partner_id;
            $service->category_id = $request->category_id;
            $service->name = $request->name;
            $service->DVT = $request->DVT;
            $service->description = $request->description;
            $service->json_amount_price = $request->json_amount_price;
            $service->save();
            $service->SKU = AdapterHelper::createSKU([$request->name, $request->partner_id], $service->id);
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

    /**
     * Create category service for partner.
     * @bodyParam partner_id int required id của partner.Example: 1
     * @bodyParam name string required name.Example: hello
     */
    public function create_category(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $cate = new CategoryServiceOrder();
            $cate->name = $request->name;
            $cate->partner_id = $request->partner_id;

            $cate->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $cate, 200, 'Success');
    }

    /**
     * Update category service for partner.
     * @bodyParam partner_id int required id của partner.Example: 1
     * @bodyParam name string required name.Example: hello
     */
    public function update_category(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $cate = CategoryServiceOrder::find($id);
            $cate->name = $request->name;
            $cate->partner_id = $request->partner_id;

            $cate->save();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $cate, 200, 'Success');
    }

    /**
     * Get service for partner.
     * @bodyParam partner_id int required id của partner.Example: 1
     * @bodyParam name string search name.Example: hello
     */
    public function index_category(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $query = CategoryServiceOrder::where('partner_id', $request->partner_id);
            if ($request->name) {
                $query->where('name', 'LIKE', '%' . $request->name . "%");
            }
            $data = $query->paginate($request->per_page ? $request->per_page : 15);
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * get service all of list partner.
     * @bodyParam list_partner_id json required id của partner.Example: [1,2,3,4]
     */
    public function get_service_list_partner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_partner_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $list_partner_id = json_decode($request->list_partner_id);
        if (!$list_partner_id) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, 'Json partner fail');
        }
        try {
            //code...
            $list_partner = User::select(['id', 'name', 'phone', 'email'])->with(['category_service_order'])->whereIn('id', $list_partner_id)->get();
            $service = ServiceOrderOfPartner::with(['category', 'partner' => function ($q) {
                return $q->select(['id', 'name', 'phone', 'email']);
            }])->whereIn('partner_id', $list_partner_id)
                ->orderBy('partner_id', 'desc')
                ->get();

            // $list_cate_service_pito = CategoryServiceOrder::where('partner_id', null)->get();
            // $list_partner[] = [
            //     'id' => "PITO",
            //     'name' => 'PITO',
            //     'category_service_order' => $list_cate_service_pito
            // ];
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        $res = ['list_partner' => $list_partner, 'service' => $service, 'list_cate_service_pito' => []];
        return AdapterHelper::sendResponse(true, $res, 200, 'Success');
    }

    /**
     * Get service for partner.
     * @bodyParam partner_id int required id của partner.Example: 1
     * @bodyParam name string search name.Example: hello
     */
    public function delete_category(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            CategoryServiceOrder::find($id)->delete();

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Update service for partner.
     * @bodyParam partner_id int id của partner.Example: 1
     * @bodyParam name date . Example: dịch vụ vận chuyển
     * @bodyParam DVT string Đơn vị tính người cái bàn.Example: người
     * @bodyParam json_amount_price string đoạn json số lượng giá. Example: [{"amount":10,"price":30000},{"amount":20,"price":45000}]
     * @bodyParam description string mo ta dich vu. Example: dịch vụ của partner
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            'partner_id' => 'required',
            'name' => 'required',
            'DVT' => 'required',
            'json_amount_price' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if (!json_decode($request->json_amount_price)) {
            return AdapterHelper::sendResponse(false, 'Validation error', 400, 'The field json amount price fail');
        }

        DB::beginTransaction();
        try {
            //code...
            $service = ServiceOrderOfPartner::find($id);
            if (!$service)
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
            $service->partner_id = $request->partner_id;
            $service->name = $request->name;
            $service->DVT = $request->DVT;
            $service->description = $request->description;
            $service->json_amount_price = $request->json_amount_price;
            $service->category_id = $request->category_id;
            $service->save();
            $service->SKU = AdapterHelper::createSKU([$request->name, $request->partner_id], $service->id);
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
    /**
     * Delete service for partner.
     */
    public function delete(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            //code...
            $service = ServiceOrderOfPartner::find($id);
            if (!$service)
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
            $service->delete();
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'Success', 200, 'Success');
    }

    /**
     * Calc transport for partner (truyền lat long 2 vị trí)
     * @bodyParam _lat_to string required.Example: 1123
     * @bodyParam _long_to string required. Example: 123.12312
     * @bodyParam _lat_from string required. Example: 123.12312
     * @bodyParam _long_from string required. Example: 123.12312
     */
    public function CalcTransportForPartner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'image' => 'required',
            '_lat_to' => 'required',
            '_long_to' => 'required',
            '_lat_from' => 'required',
            '_long_from' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        try {
            //code...
            $to = [
                '_lat' => $request->_lat_to,
                '_long' => $request->_long_to,
            ];
            $from = [
                '_lat' => $request->_lat_from,
                '_long' => $request->_long_from,
            ];
            $data = ServiceOrderDefault::getSeviceTransport($to, $from);
        } catch (\Throwable $th) {
            //throw $th;
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Undefined Error', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }
}
