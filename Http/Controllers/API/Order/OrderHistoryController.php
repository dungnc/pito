<?php

namespace App\Http\Controllers\API\Order;

use App\Repositories\Contracts\HistoryDriverInterface;
use Mockery\Exception;
use PDF;
use App\Model\User;
use App\Model\Order\Order;
use Illuminate\Http\Request;
use App\Model\Order\SubOrder;
use App\Traits\AdapterHelper;
use App\Model\Order\DetailOrder;
use App\Model\Order\ServiceOrder;
use App\Model\Proposale\Proposale;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Mail\SendProposale;
use App\Model\Order\OrderForCustomer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;

/**
 * @group Order History
 *
 * APIs for Order History
 */
class OrderHistoryController extends Controller
{

    private $driver = null;


    public function __construct(HistoryDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Get History Of Order
     * @queryParam page int The current page of history. Example: 1
     */
    public function index($id)
    {

        $data = $this->driver->getAll(Order::class, $id);

        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get History Of Order
     * @queryParam page int The current page of history. Example: 1
     * @bodyParam email string required email. Example: quyproi51vn@gmail.com
     * @bodyParam token string required email. Example: quyproi51vn@gmail.com
     */
    public function index_share(Request $request, $id)
    {
        $token = $request->token;
        $email = $request->email;
        if (!Hash::check($email . "-" . $id . "-" . $email . "-" . ($id * 100), $token))
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        $data = $this->driver->getAll(Order::class, $id);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Get History Detail
     */
    public function show($order_id, $history_id)
    {
        $data = $this->driver->getDetail(Order::class, $order_id, $history_id);
        if (!isset($data)) return AdapterHelper::sendResponse(false, 'History not found', 404, 'Error not found');
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }


    /**
     * Remove History of a order
     */
    public function removeMany(Request $request, $order_id)
    {
        try {
            $data = $this->driver->removeMany(Order::class, $order_id, $request->only('id_array', 'from', 'to'));
            return AdapterHelper::sendResponse(true, $data, 200, 'success');
        } catch (Exception $exception) {
            return AdapterHelper::sendResponse(false, null, 500, $exception->getMessage());
        }
    }

    /**
     * Remove One History Of Order
     */
    public function removeOne($order_id, $history_id)
    {
        try {
            $data = $this->driver->removeOne(Order::class, $order_id, $history_id);
            return AdapterHelper::sendResponse(true, $data, 200, 'success');
        } catch (Exception $exception) {
            return AdapterHelper::sendResponse(false, null, 500, $exception->getMessage());
        }
    }
}
