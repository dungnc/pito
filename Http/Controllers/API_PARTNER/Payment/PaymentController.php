<?php

namespace App\Http\Controllers\API_PARTNER\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Model\Order\Order;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\HistoryRevenue\HistoryRevenueForPartner;

class PaymentController extends Controller
{
    /**
     * list payment of partners
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $is_pay = $request->is_pay;
        try {
            $query = ProposaleForPartner::with(['proposale.order' => function ($q) {
                $q->select(['id']);
            }])
            
          
                ->select('*')
                ->whereHas('proposale', function ($q) {
                    $q->whereNotIn('status', [3, 4]);
                })
                ->where('partner_id', $user->id);
            if(!$is_pay){
                $query = $query->whereHas('proposale.order',function($q){
                
                    $q->whereNotIn('status',[4,5,10]);
            });
            }
            if (isset($request->is_pay)) {
                $query = $query->where('is_pay', $request->is_pay);
            }
            $data = $query->paginate($request->per_page ? $request->per_page : 15)->toArray();
            foreach ($data['data'] as $key => $value) {
                $tmp = $value;

                $tmp['order'] = $value['proposale']['order'];
                unset($tmp['proposale']['order']);
                $data['data'][$key] = $tmp;
            }
            $data = collect($data);
            return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, 'Mobile', $request->getRequestUri());
            return AdapterHelper::sendResponse(false, 'Error undefine', 500, $th->getMessage());
        }
    }

    /**
     * detail payment of partners
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $proposal_id = Order::with('proposale.proposale_for_partner')->find($id)->proposale->proposale_for_partner()->where('partner_id', $user->id)->first()->id;
        $data = HistoryRevenueForPartner::where('proposale_id', $proposal_id)->where('partner_id', $user->id)
            ->orderBy('id', 'desc')
            ->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }
}
