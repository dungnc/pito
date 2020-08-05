<?php

namespace App\Http\Controllers\API\TicketAndReview;

use PDF;
use App\Model\User;
use App\Model\Order\Order;
use Illuminate\Support\Str;
use App\Mail\SendLinkReview;
use Illuminate\Http\Request;
use App\Model\Order\SubOrder;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Model\TicketAndReview\Review;
use App\Model\TicketAndReview\TicketEnd;
use App\Model\GenerateToken\RequestToken;
use Illuminate\Support\Facades\Validator;
use App\Model\TicketAndReview\TicketStart;
use App\Model\Proposale\ProposaleForPartner;
use App\Mail\SendLinkReviewFeedbackForPartner;
use App\Model\TicketAndReview\Review\FullReview;
use App\Mail\Version2\ReviewFinal\SendReviewPartner;
use App\Model\TicketAndReview\Review\QuestionReview;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Mail\Version2\ReviewFinal\CustomerReviewPartner;

/**
 * @group Set Data review
 *
 * APIs for review
 */
class SetDataReview extends Controller
{


    /**
     * Get List question review.
     */
    public function index_question_reivew(Request $request)
    {
        $query = QuestionReview::with([
            'answer_review' => function ($q) {
                $q->select('*')->orderBy('_order', 'asc');
            }
        ])->orderBy('_order', 'asc');
        $data = $query->get();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Get List and search review.
     * @bodyParam name_user string tên của user . Example: quy
     * @bodyParam name_target string tên của user bị review . Example: quý
     * @bodyParam type search type. Example: CUSTOMER-PARTNER
     */
    public function index_review(Request $request)
    {
        $query = FullReview::with([
            'user' => function ($q) {
                $q->select(['id', 'name', 'type_role']);
            },
            'target_user' => function ($q) {
                $q->select(['id', 'name', 'type_role']);
            }, 'order'
        ]);
        if ($request->name_user) {
            $name_user = $request->name_user;
            $query = $query->whereHas('user', function ($q) use ($name_user) {
                $q->where('name', 'LIKE', "%" . $name_user . "%");
            });
        }
        if ($request->name_target) {
            $name_target = $request->name_target;
            $query = $query->whereHas('target_user', function ($q) use ($name_target) {
                $q->where('name', 'LIKE', "%" . $name_target . "%");
            });
        }
        if ($request->type) {
            $query = $query->where('type', $request->type);
        }
        $data = $query->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    /**
     * Get detail review by id
     */
    public function detail_review(Request $request, $id)
    {
        $data = FullReview::with([
            'user' => function ($q) {
                $q->select(['id', 'name', 'type_role']);
            },
            'target_user' => function ($q) {
                $q->select(['id', 'name', 'type_role']);
            }, 'order'
        ])->find($id);
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Get list review by order.
     */
    public function list_review_for_partner(Request $request, $id)
    {
        $data = FullReview::with([
            'user' => function ($q) {
                $q->select(['id', 'name', 'type_role']);
            },
            'target_user' => function ($q) {
                $q->select(['id', 'name', 'type_role']);
            }, 'order'
        ])->where('order_id', $id)
            ->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }

    public function check_token_exists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $token_request = RequestToken::FindToken($request->token)->first();
        if (!$token_request) {
            return AdapterHelper::sendResponse(false, 'Mã Token Không Đúng hoặc Bạn Đã Review Rồi!', 404, 'Mã Token Không Đúng hoặc Bạn Đã Review Rồi!');
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * Add review full.
     * @bodyParam user_id int required người review . Example: 1
     * @bodyParam target_user_id int required người bị review . Example: quý
     * @bodyParam field_more string required json tự add khi review . Example: [{"xx":12}]
     * @bodyParam type int required type review . Example: CUSTOMER-PARTNER
     * @bodyParam sub_order_id int required  . Example: 1
     */
    public function add_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        DB::beginTransaction();
        try {
            //code...
            $token_request = RequestToken::FindToken($request->token)->first();
            if (!$token_request) {
                return AdapterHelper::sendResponse(false, 'Mã Xác Thực Không Đúng hoặc Bạn Đã Đánh Giá Rồi!', 404, 'Mã Xác Thực Không Đúng hoặc Bạn Đã Đánh Giá Rồi!');
            }
            $data_token = json_decode($token_request->request);
            $param = [
                'order_id' => $data_token->order_id,
                'type' => $data_token->type,
                'user_id' => $data_token->user_id,
                'target_user_id' => $data_token->target_user_id,
                'field_more' => $request->field_more
            ];
            $review = FullReview::create($param);
            $token_request->delete();
            $order = Order::with([
                'assign_pito_admin', 'pito_admin',
                'sub_order', 'sub_order.order_for_partner',
                'sub_order.proposale_for_partner',
                'proposale'
            ])->find($data_token->order_id);
            $assign_pito_admin = $order->assign_pito_admin;
            $sub_orders = $order->sub_order;
            $proposale = $order->proposale;
            foreach ($sub_orders as $sub_order) {
                $proposale_for_partner = ProposaleForPartner::with([
                    'partner',
                    'proposale.order.company',
                    'proposale.order.assign_pito_admin',
                    'proposale.order.order_for_customer',
                    'proposale.order.order_for_customer.customer',
                    'sub_order.order_for_partner.service',
                    'sub_order.order_for_partner.service_none',
                    'sub_order.order_for_partner.service_default',
                    'sub_order.order_for_partner.service_transport',
                    'sub_order.order_for_partner.detail',
                ])->where('sub_order_id', $sub_order->id)
                    ->where('proposale_id', $proposale->id)->first();
                MultiMail::to($proposale_for_partner->partner->email)
                    ->from('partners@pito.vn')
                    ->send(new SendReviewPartner($proposale_for_partner->partner, $proposale_for_partner, $review->id));
                MultiMail::to($assign_pito_admin->email)
                    ->from('partners@pito.vn')
                    ->send(new SendReviewPartner($proposale_for_partner->partner, $proposale_for_partner, $review->id));
                // MultiMail::to('quyproi51vn@gmail.com')
                //     ->from('partners@pito.vn')
                //     ->send(new SendReviewPartner($proposale_for_partner->partner, $proposale_for_partner));
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Error undefined', 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, $review, 200, 'Success');
    }

    /**
     * send review full.
     * @bodyParam user_id int required người review . Example: 1
     * @bodyParam type int required type review . Example: CUSTOMER-PARTNER
     * @bodyParam sub_order_id int required  . Example: 1
     * @bodyParam target_user_id int required  . Example: 1
     */
    public function send_link_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $this->send_link_review_share($request);
        return AdapterHelper::sendResponse(true, 'success', 200,  'success');
    }

    public function send_link_review_share($request)
    {
        $order = Order::with(['order_for_customer.customer'])->find($request->order_id);
        $customer = $order->order_for_customer->customer;
        $data_token = [
            'type' => $request->type,
            'order_id' => $request->order_id,
            'target_user_id' => $request->target_user_id,
            'user_id' => $order->order_for_customer->customer->id
        ];
        $request_token_cofirm = new RequestToken("CUSTOMER.REVIEW", $data_token);
        $token_review = $request_token_cofirm->createToken();
        $url_reivew = config('app.url_front') . "feedbacks?token=" . $token_review;
        MultiMail::from('order@pito.vn')
            ->to($customer->email)
            ->send(new CustomerReviewPartner($customer, $order, $url_reivew));
        // MultiMail::from('order@pito.vn')
        //     ->to('quyproi51vn@gmail.com')
        //     ->send(new CustomerReviewPartner($customer, $order, $url_reivew));
    }
}
