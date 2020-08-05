<?php

namespace App\Http\Controllers\API\Payment;

use App\Model\User;
use App\Helpers\Shorty;
use App\Model\Order\Order;
use App\Traits\Notification;
use Illuminate\Http\Request;
use App\Mail\SendLinkPayment;
use App\Traits\AdapterHelper;
use App\Model\Proposale\Proposale;
use App\Traits\NotificationHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Model\GenerateToken\RequestToken;
use Illuminate\Support\Facades\Validator;
use App\Model\Proposale\ProposaleForPartner;
use App\Model\Proposale\ProposaleForCustomer;
use App\Model\Notification\NotificationSystem;
use App\Model\PartnerHas\ServiceOrderOfPartner;
use App\Model\HistoryRevenue\HistoryRevenueForPartner;
use IWasHereFirst2\LaravelMultiMail\Facades\MultiMail;
use App\Model\HistoryRevenue\HistoryRevenueForCustomer;
use App\Mail\Version2\RemiderOrder\RemiderPaymentForCustomer;

/**
 * @group Payment
 *
 * APIs for payment
 */
class PaymentController extends Controller
{
    /**
     * Get List service cua partner.
     */
    private $vnp_TmnCode = "BSETY6GH";
    private $vnp_HashSecret = "AOLBEHPIECLXDWJJYEAMDVFZRNAWSGVQ";
    private $vnp_Url = "http://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    private $vnp_Returnurl = "share/payment/notification";

    private $status_payment = [
        'pay' => [
            '00' => 'Giao dịch thành công',
            '01' => 'Giao dịch đã tồn tại',
            '02' => 'Merchant không hợp lệ (kiểm tra lại vnp_TmnCode)',
            '03' => 'Dữ liệu gửi sang không đúng định dạng',
            '04' => 'Khởi tạo GD không thành công do Website đang bị tạm khóa',
            '05' => 'Quý khách nhập sai mật khẩu quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch',
            '13' => 'Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Xin quý khách vui lòng thực hiện lại giao dịch.',
            '07' => 'Giao dịch bị nghi ngờ là giao dịch gian lận',
            '09' => 'Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
            '10' => 'Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần',
            '11' => 'Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.',
            '12' => 'Thẻ/Tài khoản của khách hàng bị khóa.',
            '24' => 'Khách hàng hủy giao dịch.',
            '51' => 'Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.',
            '65' => 'Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.',
            '75' => 'Ngân hàng thanh toán đang bảo trì.',
            '08' => 'Hệ thống Ngân hàng đang bảo trì. Xin quý khách tạm thời không thực hiện giao dịch bằng thẻ/tài khoản của Ngân hàng này.',
            '99' => 'Lỗi không xác định'
        ]
    ];

    /**
     * Tạo request payment vnpay.
     *
     * @bodyParam proposale_id int required id của proposale theo đối tương user.Example: 8
     * @bodyParam user_id int required id của user .Example: 20
     * @bodyParam amount int required số tiền cần thanh toán. Example: 20000
     * @bodyParam description string required nội dung hay mô tả thanh toán.Example: thanh toán tiệc bàn
     */

    public function create_url_payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'type_role' => 'required',
            'amount' => 'required',
            // 'description' => 'required'
        ]);

        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        // user_id-type_role-order_id-proposale_id-proposale_for_{type_role}_id-count;
        $user = null;
        $SKU = "";
        $request->amount = AdapterHelper::CurrencyIntToString($request->amount);
        if ($request->type_role == User::$const_type_role['CUSTOMER']) {
            $proposale = ProposaleForCustomer::with('proposale')->find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Not found', 400, 'Proposale Not Found');
            }
            $SKU .= $proposale->customer_id;
            $user = User::find($proposale->customer_id);
            $total_price = (int) $request->amount;
            $SKU .= "-CUSTOMER-" . $proposale->proposale->order_id . "-" . $proposale->proposale_id . "-" . $proposale->id;
            $list_price = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU . "%")->where('status', '00')->get();
            foreach ($list_price as $key => $value) {
                $total_price += (int) $value->price;
            }
            // if ($total_price > $proposale->price) {
            //     return AdapterHelper::sendResponse(false, 'Not found', 400, 'Amount cannot exceed price of proposale ');
            // }
            $count = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU . "%")->get();
            $SKU .= "-" . count($count);
            $request_data_token_payment = [
                'SKU' => $SKU,
                'type_role' => 'CUSTOMER',
            ];
            $request_token_payment = new RequestToken("CUSTOMER.RESPONSE_PAYMENT", $request_data_token_payment);
            $token_payment = $request_token_payment->createToken();
            $SKU .= "-" . $token_payment;
        } else {
            $proposale = ProposaleForPartner::with('proposale')->find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Not found', 400, 'Proposale Not Found');
            }
            $SKU .= $proposale->partner_id;
            $user = User::find($proposale->partner_id);
            $total_price = (int) $request->amount;
            $SKU .= "-PARTNER-" . $proposale->proposale->order_id . "-" . $proposale->proposale_id . "-" . $proposale->id;
            $list_price = HistoryRevenueForPartner::where('SKU', 'LIKE', "%" . $SKU . "%")->where('status', '00')->get();
            foreach ($list_price as $key => $value) {
                $total_price += (int) $value->price;
            }
            // if ($total_price > $proposale->price) {
            //     return AdapterHelper::sendResponse(false, 'Not found', 400, 'Amount cannot exceed price of proposale ');
            // }
            $count = HistoryRevenueForPartner::where('SKU', 'LIKE', "%" . $SKU . "%")->get();
            $SKU .= "-" . count($count);
            $request_data_token_payment = [
                'SKU' => $SKU,
                'type_role' => 'PARTNER',
            ];
            $request_token_payment = new RequestToken("PARTNER.RESPONSE_PAYMENT", $request_data_token_payment);
            $token_payment = $request_token_payment->createToken();
            $SKU .= "-" . $token_payment;
        }

        $vnp_TxnRef = $SKU; //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
        $vnp_OrderInfo = "Thanh Toán Cho PITO";
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $request->amount * 100;
        $vnp_Locale = 'vn';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

        $inputData = array(
            "vnp_Version" => "2.0.0",
            "vnp_TmnCode" => $this->vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => config('app.url_front') . $this->vnp_Returnurl,
            "vnp_TxnRef" => $SKU,
        );
        // dd($inputData);

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . $key . "=" . $value;
            } else {
                $hashdata .= $key . "=" . $value;
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $this->vnp_Url . "?" . $query;
        if (isset($this->vnp_HashSecret)) {
            // $vnpSecureHash = md5($vnp_HashSecret . $hashdata);
            $vnpSecureHash = hash('sha256', $this->vnp_HashSecret . $hashdata);
            $vnp_Url .= 'vnp_SecureHashType=SHA256&vnp_SecureHash=' . $vnpSecureHash;
        }
        // Mail::to($user->email)->send(new SendLinkPayment($vnp_Url));
        return AdapterHelper::sendResponse(true, $vnp_Url, 200, 'Success');
    }

    public function send_payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'type_role' => 'required',
            // 'amount' => 'required',
            // 'description' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        if ($request->type_role == User::$const_type_role['CUSTOMER']) {
            $proposale = ProposaleForCustomer::with('proposale.order')->find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Not found', 400, 'Proposale Not Found');
            }
            $user = User::find($proposale->customer_id);
            $customer = $user;
            $request_data_token_payment = [
                'proposale_id' => $proposale->id,
                'type_role' => 'CUSTOMER',
                'user_id' => $user->id
            ];
            $request_token_payment = new RequestToken("CUSTOMER.PAYMENT", $request_data_token_payment);
            $token_payment = $request_token_payment->createToken();
            MultiMail::from('order@pito.vn')
                ->to($customer->email)
                ->send(new RemiderPaymentForCustomer($proposale->proposale->order, $customer, $token_payment));
            $url_payment = env('APP_URL_FRONT') . 'share/payment?token=' . $token_payment;

            // create short url
            $hostname = config('app.url_front') . "shortLink";;
            $chars = config('hashing.short_link_hash');
            $salt = config('app.name');
            $padding = 5;
            $shorty = new Shorty($hostname);
            $shorty->set_chars($chars);
            $shorty->set_salt($salt);
            $shorty->set_padding($padding);
            $short_url = Shorty::create_short_link($url_payment);
            Notification::esms($customer->phone, "PITO xin moi quy khach vui long thanh toan don hang da thuc hien. Truy cap link de biet them chi tiet: " . $short_url);
        } else {
            $proposale = ProposaleForPartner::with('proposale')->find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Not found', 400, 'Proposale Not Found');
            }
            $user = User::find($proposale->partner_id);
        }

        return AdapterHelper::sendResponse(true, 'Success', 200, 'Success');
    }
    public function view_payment_share(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'type_role' => 'required',
            'token' => 'required',
        ]);
        $user = null;
        $SKU = "";
        if ($request->type_role == User::$const_type_role['CUSTOMER']) {
            $proposale = ProposaleForCustomer::with('proposale')->find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Not found', 400, 'Proposale Not Found');
            }
            $SKU .= $proposale->customer_id;
            $user = User::find($proposale->customer_id);
            $total_price = 0;
            $SKU .= "-CUSTOMER-" . $proposale->proposale->order_id . "-" . $proposale->proposale_id . "-" . $proposale->id;
            $list_price = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU . "%")->where('status', '00')->get();
            foreach ($list_price as $key => $value) {
                $total_price += (int) $value->price;
            }

            $count = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU . "%")->get();
            $SKU .= "-" . count($count) . "-" . time();
        } else {
            $proposale = ProposaleForPartner::with('proposale')->find($request->proposale_id);
            if (!$proposale) {
                return AdapterHelper::sendResponse(false, 'Not found', 400, 'Proposale Not Found');
            }
            $SKU .= $proposale->partner_id;
            $user = User::find($proposale->partner_id);
            $total_price = 0;
            $SKU .= "-PARTNER-" . $proposale->proposale->order_id . "-" . $proposale->proposale_id . "-" . $proposale->id;
            $list_price = HistoryRevenueForPartner::where('SKU', 'LIKE', "%" . $SKU . "%")->where('status', '00')->get();
            foreach ($list_price as $key => $value) {
                $total_price += (int) $value->price;
            }

            $count = HistoryRevenueForPartner::where('SKU', 'LIKE', "%" . $SKU . "%")->get();
            $SKU .= "-" . count($count) . "-" . time();
        }
        if (!Hash::check($user->user_id . "-" . $request->proposale_id . "-" . $request->type_role, $request->token))
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        $data = [
            'price' => $proposale->price,
            'price_paid' => $total_price,
            'type_role' => $request->type_role,
            'proposale_id' => $request->proposale,
        ];
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }


    private function update_pay_for_customer($inputData)
    {
        $refId = $inputData['vnp_TxnRef'];
        $array_id = explode("-", $refId);
        // HistoryRevenueForCustomer
        $total_price = 0;
        // $Order = Order::find($array_id[2]);
        // $Proposale = Proposale::find($array_id[3]);
        // $ProposaleForCustomer = ProposaleForCustomer::find($array_id[3]);

        // hard code khi change đơn hang thay đổi báo giá là cập nhật báo giá mới
        $Order = Order::with('proposale.proposale_for_customer')->find($array_id[2]);
        $Proposale = $Order->proposale;
        $ProposaleForCustomer = $Proposale->proposale_for_customer;
        if (!$Order || !$Proposale || !$ProposaleForCustomer) {
            return AdapterHelper::sendResponse(false, 'Đơn hàng không tồn tại', 404, 'Đơn hàng không tồn tại');
        }
        $new_history = new HistoryRevenueForCustomer();
        $new_history->customer_id = $array_id[0];
        $new_history->proposale_id = $ProposaleForCustomer->id;
        $new_history->price = (int) $inputData['vnp_Amount'];
        $new_history->price = (int) $new_history->price / 100;
        $new_history->DVT = "VNĐ";
        $new_history->status = $inputData['vnp_ResponseCode'];
        $new_history->description = $inputData['vnp_OrderInfo'];
        $new_history->SKU = $refId;

        unset($inputData['vnp_Amount']);
        unset($inputData['vnp_OrderInfo']);
        unset($inputData['vnp_TxnRef']);
        $new_history->field_more = json_encode($inputData);
        $new_history->save();

        $Customer = User::find($array_id[0]);
        $Customer->customer()->update(['point' => (int) $new_history->price / 100000]);
        $SKU_real = $array_id[0] . "-" . $array_id[1] . "-" . $Order->id . "-" . $Proposale->id . "-" . $ProposaleForCustomer->id;
        $list_price = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU_real . "%")->where('status', '00')->get();
        foreach ($list_price as $key => $value) {
            $total_price += (int) $value->price;
        }

        // if ($total_price == (int) $ProposaleForCustomer->price) {
        //     $ProposaleForCustomer->is_pay = 1;
        //     $ProposaleForCustomer->save();
        // }
    }

    private function update_pay_for_partner($inputData)
    {
        $refId = $inputData['vnp_TxnRef'];
        $array_id = explode("-", $refId);
        $total_price = 0;
        // HistoryRevenueForPartner
        $Order = Order::find($array_id[2]);
        $Proposale = Proposale::find($array_id[3]);
        $ProposaleForPartner = ProposaleForPartner::find($array_id[3]);
        if (!$Order || !$Proposale || !$ProposaleForPartner) {
            return AdapterHelper::sendResponse(false, 'Đơn hàng không tồn tại', 404, 'Đơn hàng không tồn tại');
        }
        $new_history = new HistoryRevenueForPartner();
        $new_history->partner_id = $array_id[0];
        $new_history->proposale_id = $array_id[4];
        $new_history->price = (int) $inputData['vnp_Amount'];
        $new_history->price = (int) $new_history->price / 100;
        $new_history->DVT = "VNĐ";
        $new_history->status = $inputData['vnp_ResponseCode'];
        $new_history->description = $inputData['vnp_OrderInfo'];
        $new_history->SKU = $refId;

        unset($inputData['vnp_Amount']);
        unset($inputData['vnp_OrderInfo']);
        unset($inputData['vnp_TxnRef']);
        $new_history->field_more = json_encode($inputData);
        $new_history->save();

        $SKU_real = $array_id[0] . "-" . $array_id[1] . "-" . $array_id[2] . "-" . $array_id[3] . "-" . $array_id[4];
        $list_price = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU_real . "%")->where('status', '00')->get();
        foreach ($list_price as $key => $value) {
            $total_price += (int) $value->price;
        }

        // if ($total_price == (int) $ProposaleForPartner->price) {
        //     $ProposaleForPartner->is_pay = 1;
        //     $ProposaleForPartner->save();
        // }
    }

    /**
     * Response payment from vnpay
     *
     */
    public function payment_from_vnpay(Request $request)
    {
        DB::beginTransaction();
        try {
            $inputData = array();
            $data = $request->all();
            foreach ($data as $key => $value) {
                if (substr($key, 0, 4) == "vnp_") {
                    $inputData[$key] = $value;
                }
            }

            $vnp_SecureHash = $inputData['vnp_SecureHash'];
            unset($inputData['vnp_SecureHashType']);
            unset($inputData['vnp_SecureHash']);
            ksort($inputData);
            $i = 0;
            $hashData = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashData = $hashData . '&' . $key . "=" . $value;
                } else {
                    $hashData = $hashData . $key . "=" . $value;
                    $i = 1;
                }
            }
            $secureHash = hash('sha256', $this->vnp_HashSecret . $hashData);
            $refId = $inputData['vnp_TxnRef'];

            //code...
            $array_id = explode("-", $refId);
            $total_price = 0;
            if ($array_id[1] == User::$const_type_role['CUSTOMER']) {
                if (HistoryRevenueForCustomer::where('SKU', $refId)->exists()) {
                    DB::rollBack();
                    $response = [
                        "status" => '01',
                        "message" => $this->status_payment['pay']['01'],
                        "price" => $inputData['vnp_Amount']
                    ];
                    return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
                } else {
                    $ProposaleForCustomer = ProposaleForCustomer::with(['proposale', 'customer'])->find($array_id[4]);
                    if (!$ProposaleForCustomer) {
                        return AdapterHelper::sendResponse(false, 'Not found', 404, 'Báo giá không tồn tại');
                    }
                    $data_token = RequestToken::FindToken($array_id[count($array_id) - 1])
                        ->where('type', 'CUSTOMER.RESPONSE_PAYMENT')
                        ->first();

                    if (!$data_token) {
                        DB::rollBack();
                        $response = [
                            "status" => '07',
                            "message" => $this->status_payment['pay']['07'],
                            "price" => $inputData['vnp_Amount']
                        ];
                        return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
                    } else {
                        $data_token_request = json_decode($data_token->request);
                        $SKU_tmp = "";
                        for ($i = 0; $i < count($array_id) - 1; $i++) {
                            $SKU_tmp .= $array_id[$i] . "-";
                        }
                        $SKU_tmp = trim($SKU_tmp, "-");
                        if ($data_token_request->SKU != $SKU_tmp) {
                            DB::rollBack();
                            $response = [
                                "status" => '07',
                                "message" => $this->status_payment['pay']['07'],
                                "price" => $inputData['vnp_Amount']
                            ];
                            return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
                        }
                    }

                    $this->update_pay_for_customer($inputData, $array_id);

                    $data_token->delete();

                    $new_notifi = new NotificationSystem();
                    $new_notifi->content = 'Order PT' . $ProposaleForCustomer->proposale->order_id . ": " . $ProposaleForCustomer->customer->name . ' đã thêm môt thanh toán của khách hàng có báo giá (' . $array_id[4] . ')';
                    $new_notifi->tag = NotificationSystem::EVENT_TYPE["PAYMENT.ADD.HISTORY"];
                    $new_notifi->type = Order::class;
                    $new_notifi->type_id = $ProposaleForCustomer->proposale->order_id;
                    $new_notifi->save();
                }
            } else {
                if (HistoryRevenueForPartner::where('SKU', $refId)->exists()) {
                    DB::rollBack();

                    $response = [
                        "status" => '01',
                        "message" => $this->status_payment['pay']['01'],
                        "price" => $inputData['vnp_Amount']
                    ];
                    return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
                } else {
                    $ProposaleForPartner = ProposaleForPartner::with(['proposale', 'partner'])->find($array_id[4]);
                    if (!$ProposaleForPartner) {
                        return AdapterHelper::sendResponse(false, 'Not found', 404, 'Báo giá không tồn tại');
                    }
                    $data_token = RequestToken::FindToken($array_id[count($array_id) - 1])
                        ->where('type', 'PARTNER.RESPONSE_PAYMENT')
                        ->first();
                    if (!$data_token) {
                        DB::rollBack();
                        $response = [
                            "status" => '07',
                            "message" => $this->status_payment['pay']['07'],
                            "price" => $inputData['vnp_Amount']
                        ];
                        return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
                    } else {
                        $data_token_request = json_decode($data_token->request);
                        $SKU_tmp = "";
                        for ($i = 0; $i < count($array_id) - 1; $i++) {
                            $SKU_tmp .= $array_id[$i] . "-";
                        }
                        $SKU_tmp = trim($SKU_tmp, "-");
                        if (!$data_token_request->SKU != $SKU_tmp) {
                            DB::rollBack();
                            $response = [
                                "status" => '07',
                                "message" => $this->status_payment['pay']['07'],
                                "price" => $inputData['vnp_Amount']
                            ];
                            return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
                        }
                    }
                    $this->update_pay_for_partner($inputData, $array_id);

                    $data_token->delete();

                    $new_notifi = new NotificationSystem();
                    $new_notifi->content = 'Order PT' . $ProposaleForPartner->proposale->order_id . ": " . $ProposaleForPartner->partner->name . ' đã thêm môt thanh toán của đối tác có báo giá (' . $array_id[4] . ')';
                    $new_notifi->tag = NotificationSystem::EVENT_TYPE["PAYMENT.ADD.HISTORY"];
                    $new_notifi->type = Order::class;
                    $new_notifi->type_id = $ProposaleForPartner->proposale->order_id;
                    $new_notifi->save();
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());
            $response = [
                "status" => '07',
                "message" => $this->status_payment['pay']['07'],
                "price" => 0
            ];
            return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
        }

        $response = [
            "status" => $inputData['vnp_ResponseCode'],
            "message" => $this->status_payment['pay'][$inputData['vnp_ResponseCode']],
            "price" => $inputData['vnp_Amount']
        ];
        return AdapterHelper::sendResponse(false, $response, 403, $response['message']);
    }

    /**
     * History payment for customer
     * Danh sách lịch sử tất cả các order báo giá của khách hàng, các param dưới đây là đề search.
     * @bodyParam status int trạng thái của báo giá cho khách hàng. Example: 1
     * @bodyParam status_order int trạng thái của của order. Example: 1
     * @bodyParam id int Search id báo giá. Example: 1
     * @bodyParam order_id int search id của order. Example: 1
     * @bodyParam is_pay int search trạng thái 0: chưa thanh toán, 1: đã thanh toán. Example: 1
     * @bodyParam price int search gia. Example: 20000
     * @bodyParam customer_name string search ten khach hang. Example: quy nt
     *
     */
    public function history_payment_for_customer(Request $request)
    {
        $query = ProposaleForCustomer::with(['proposale.order', 'customer'])
            ->whereHas('proposale')
            ->select('*');
        $data_request = $request->all();
        unset($data_request['page']);
        if ($request->status) {
            $query = $query->where('status', $request->status);
        }

        if ($request->id) {
            $query = $query->where('id', 'LIKE', '%' . $request->id . '%');
        }

        if ($request->proposale_id) {
            $query = $query->where('proposale_id', 'LIKE', '%' . $request->proposale_id . '%');
        }

        if ($request->order_id) {
            $order_id = $request->order_id;
            $query = $query = $query->whereHas('proposale.order', function ($q) use ($order_id) {
                $q->where('id', 'LIKE', '%' . $order_id . '%');
            });
        }

        if ($request->customer_id) {
            $customer_id = $request->customer_id;
            $query = $query->whereHas('customer', function ($q) use ($customer_id) {
                $q->where('id', 'LIKE', '%' . $customer_id . '%');
            });
        }

        if ($request->is_pay !== null) {
            $query = $query->where('is_pay', $request->is_pay);
        }

        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $query = $query->whereHas('customer', function ($q) use ($customer_name) {
                $q->where('name', 'LIKE', '%' . $customer_name . '%');
            });
        }

        if ($request->price) {
            $query = $query->where('price', 'LIKE', "%" . $request->price . "%");
        }

        if ($request->status_order !== null) {
            $status_order = $request->status_order;
            $query = $query->whereHas('proposale.order', function ($q) use ($status_order) {
                $q->where('status', $status_order);
            });
        }

        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * History payment for partner
     * Danh sách lịch sử tất cả các order báo giá của đối tác, các param dưới đây là đề search.
     * @bodyParam status int trạng thái của báo giá cho đối tác. Example: 1
     * @bodyParam status_order int trạng thái của của order. Example: 1
     * @bodyParam id int Search id báo giá. Example: 1
     * @bodyParam order_id int search id của order. Example: 1
     * @bodyParam is_pay int search trạng thái 0: chưa thanh toán, 1: đã thanh toán. Example: 1
     * @bodyParam price int search gia. Example: 20000
     * @bodyParam partner_name string search ten khach hang. Example: ta vet
     *
     */
    public function history_payment_for_partner(Request $request)
    {
        $query = Proposale::with(['proposale_for_partner', 'order'])->paginate(15);
        return AdapterHelper::sendResponsePaginating(true, $query, 200, 'success');

        $query = ProposaleForPartner::with(['sub_order', 'proposale.order', 'partner'])
            ->whereHas('proposale')
            ->select('*');
        if ($request->user()->type_role == User::$const_type_role['PARTNER']) {
            $query = $query->where('partner_id', $request->user()->id);
        }
        $data_request = $request->all();
        unset($data_request['page']);
        if ($request->status) {
            $query = $query->where('status', $request->status);
        }

        if ($request->id) {
            $query = $query->where('id', 'LIKE', '%' . $request->id . '%');
        }

        if ($request->order_id) {
            $order_id = $request->order_id;
            $query = $query = $query->whereHas('proposale.order', function ($q) use ($order_id) {
                $q->where('id', 'LIKE', '%' . $order_id . '%');
            });
        }

        if ($request->partner_id) {
            $partner_id = $request->partner_id;
            $query = $query->whereHas('partner', function ($q) use ($partner_id) {
                $q->where('id', 'LIKE', '%' . $partner_id . '%');
            });
        }

        if ($request->is_pay !== null) {
            $query = $query->where('is_pay', $request->is_pay);
        }

        if ($request->partner_name) {
            $partner_name = $request->partner_name;
            $query = $query->whereHas('partner', function ($q) use ($partner_name) {
                $q->where('name', 'LIKE', '%' . $partner_name . '%');
            });
        }

        if ($request->price) {
            $query = $query->where('price', 'LIKE', "%" . $request->price . "%");
        }

        if ($request->status_order !== null) {
            $status_order = $request->status_order;
            $query = $query->whereHas('proposale.order', function ($q) use ($status_order) {
                $q->where('status', $status_order);
            });
        }

        $data = $query->orderBy('updated_at', 'desc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
    }

    /**
     * Detail history of proposale
     * chi tiết lịch sử thanh toán của báo giá.
     * @bodyParam proposale int id của báo giá theo đối tương. Example: 10
     * @bodyParam type_role int loại đối tương CUSTOMER,PARTNER. Example: CUSTOMER
     */

    public function detail_history_payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'type_role' => 'required'
        ]);

        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        // if ($request->type_role == User::$const_type_role['PARTNER']) {
        //     $data = HistoryRevenueForPartner::where('proposale_id', $request->proposale_id)
        //         ->where('partner_id', $request->user()->id)
        //         ->orderBy('id', 'desc')
        //         ->paginate($request->per_page ? $request->per_page : 15);
        //     return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
        // }
        if ($request->type_role == User::$const_type_role['CUSTOMER']) {
            // kiem tra bao gia co ton tai ko
            $check = ProposaleForCustomer::where('id', $request->proposale_id)->first();
            if (!$check) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Báo giá của khách hàng không tồn tại.');
            }

            $data = HistoryRevenueForCustomer::where('proposale_id', $request->proposale_id)
                ->orderBy('id', 'desc')
                ->paginate($request->per_page ? $request->per_page : 15);
            return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
        } else {
            $check = ProposaleForPartner::where('id', $request->proposale_id)->first();

            if (!$check) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Báo giá của đối tác không tồn tại.');
            }

            $data = HistoryRevenueForPartner::where('proposale_id', $request->proposale_id)
                ->orderBy('id', 'desc')
                ->paginate($request->per_page ? $request->per_page : 15);
            return AdapterHelper::sendResponsePaginating(true, $data, 200, 'success');
        }
    }

    /**
     * Detail history of proposale
     * chi tiết lịch sử thanh toán của báo giá.
     * @bodyParam proposale_id int id của báo giá theo đối tương. Example: 10
     * @bodyParam type_role int loại đối tương CUSTOMER,PARTNER. Example: CUSTOMER
     */
    private function add_history_payment_for_customer(Request $request)
    {
        $ProposaleForCustomer = ProposaleForCustomer::find($request->proposale_id);

        $SKU = $ProposaleForCustomer->customer_id;
        $total_price = (int) $request->amount;
        $SKU .= "-CUSTOMER-" . $ProposaleForCustomer->proposale->order_id . "-" . $ProposaleForCustomer->proposale_id . "-" . $ProposaleForCustomer->id;
        $count = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $SKU . "%")->get();
        $SKU .= "-" . count($count) . "-" . time();

        $new_history = new HistoryRevenueForCustomer();
        $new_history->customer_id = $ProposaleForCustomer->customer_id;
        $new_history->proposale_id = $ProposaleForCustomer->id;
        $new_history->price = (int) $request->amount;
        $new_history->DVT = "VNĐ";
        $new_history->status = '00';
        $new_history->description = $request->description;
        $new_history->SKU = $SKU;

        $new_history->field_more = json_encode([]);
        $new_history->save();
        $Customer = User::find($ProposaleForCustomer->customer_id);
        $Customer->customer()->update(['point' => (int) $new_history->price / 100000]);
        $search_sku = $ProposaleForCustomer->customer_id . "-CUSTOMER-" . $ProposaleForCustomer->proposale->order_id . "-" . $ProposaleForCustomer->proposale_id . "-" . $ProposaleForCustomer->id;
        $list_price = HistoryRevenueForCustomer::where('SKU', 'LIKE', "%" . $search_sku . "%")->where('status', '00')->get();
        foreach ($list_price as $key => $value) {
            $total_price += (int) $value->price;
        }

        if ($total_price >= (int) $ProposaleForCustomer->price) {
            $ProposaleForCustomer->is_pay = 1;
            $ProposaleForCustomer->save();
        }
    }

    private function add_history_payment_for_partner(Request $request)
    {
        $ProposaleForPartner = ProposaleForPartner::find($request->proposale_id);
        $SKU = $ProposaleForPartner->partner_id;
        $total_price = (int) $request->amount;
        $SKU .= "-PARTNER-" . $ProposaleForPartner->proposale->order_id . "-" . $ProposaleForPartner->proposale_id . "-" . $ProposaleForPartner->id;

        $count = HistoryRevenueForPartner::where('SKU', 'LIKE', "%" . $SKU . "%")->get();
        $SKU .= "-" . count($count) . "-" . time();

        $new_history = new HistoryRevenueForPartner();
        $new_history->partner_id = $ProposaleForPartner->partner_id;
        $new_history->proposale_id = $ProposaleForPartner->id;
        $new_history->price = (int) $request->amount;
        $new_history->DVT = "VNĐ";
        $new_history->status = '00';
        $new_history->description = $request->description;
        $new_history->SKU = $SKU;

        $new_history->field_more = json_encode([]);
        $new_history->save();

        $search_sku = $ProposaleForPartner->partner_id . "-PARTNER-" . $ProposaleForPartner->proposale->order_id . "-" . $ProposaleForPartner->proposale_id . "-" . $ProposaleForPartner->id;
        $list_price = HistoryRevenueForPartner::where('SKU', 'LIKE', "%" . $search_sku . "%")->where('status', '00')->get();
        foreach ($list_price as $key => $value) {
            $total_price += (int) $value->price;
        }

        // if ($total_price == (int) $ProposaleForPartner->price) {
        //     $ProposaleForPartner->is_pay = 1;
        //     $ProposaleForPartner->save();
        // }
    }

    /**
     * History payment for partner
     * Danh sách lịch sử tất cả các order báo giá của đối tác, các param dưới đây là đề search.
     * @bodyParam proposale_id int required id bao gia rieng cua doi tuong user. Example: 18
     * @bodyParam type_role string required đối tượng của user. Example: PARTNER
     * @bodyParam amount int required tiền thanh toán. Example: 1
     * @bodyParam description string nội dung hay mô tả thanh toán. Example: thanh toán
     *
     */

    public function add_history_payment(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'type_role' => 'required',
            'amount' => 'required',
            'description' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            if ($request->type_role == User::$const_type_role['CUSTOMER']) {
                $ProposaleForCustomer = ProposaleForCustomer::with('proposale')->find($request->proposale_id);
                if (!$ProposaleForCustomer) {
                    return AdapterHelper::sendResponse(false, 'Not found', 404, 'Báo giá không tồn tại');
                }
                $this->add_history_payment_for_customer($request);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $ProposaleForCustomer->proposale->order_id . ": " . $user->name . ' đã thêm môt thanh toán của khách hàng có báo giá (' . $request->proposale_id . ')';
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PAYMENT.ADD.HISTORY"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $ProposaleForCustomer->proposale->order_id;
                $new_notifi->save();
            } else {
                $ProposaleForPartner = ProposaleForPartner::with('proposale')->find($request->proposale_id);
                if (!$ProposaleForPartner) {
                    return AdapterHelper::sendResponse(false, 'Not found', 404, 'Báo giá không tồn tại');
                }
                $this->add_history_payment_for_partner($request);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $ProposaleForPartner->proposale->order_id . ": " . $user->name . ' đã thêm môt thanh toán của đối tác có báo giá (' . $request->proposale_id . ')';
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PAYMENT.ADD.HISTORY"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $ProposaleForPartner->proposale->order_id;
                $new_notifi->save();
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Undefined error', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * delete history payment item
     * Danh sách lịch sử tất cả các order báo giá của đối tác, các param dưới đây là đề search.
     * @bodyParam history_payment_id int required id của thanh toán đó. Example: 1
     * @bodyParam type_role string required đối tượng của user. Example: PARTNER
     */
    public function delete(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'history_payment_id' => 'required',
            'type_role' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            if ($request->type_role == User::$const_type_role['CUSTOMER']) {
                $check = HistoryRevenueForCustomer::find($request->history_payment_id);
                if (!$check) {
                    return AdapterHelper::sendResponse(false, 'Not found', 404, 'Lịch sử thanh toán này không tồn tại');
                }
                $check->delete();
                $ProposaleForCustomer = ProposaleForCustomer::with('proposale')->find($check->proposale_id);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $ProposaleForCustomer->proposale->order_id . ": " . $user->name . ' đã xoá thanh toán (' . $check->id . ') của khách hàng có báo giá (' . $check->proposale_id . ')';
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PAYMENT.DELETE.HISTORY"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $ProposaleForCustomer->proposale->order_id;
                $new_notifi->save();
            } else {
                $check = HistoryRevenueForPartner::find($request->history_payment_id);
                if (!$check) {
                    return AdapterHelper::sendResponse(false, 'Not found', 404, 'Lịch sử thanh toán này không tồn tại');
                }
                $check->delete();
                $ProposaleForPartner = ProposaleForPartner::with('proposale')->find($check->proposale_id);
                $new_notifi = new NotificationSystem();
                $new_notifi->content = 'Order PT' . $ProposaleForPartner->proposale->order_id . ": " . $user->name . ' đã xoá thanh toán (' . $check->id . ') của đối tác có báo giá (' . $check->proposale_id . ')';
                $new_notifi->tag = NotificationSystem::EVENT_TYPE["PAYMENT.DELETE.HISTORY"];
                $new_notifi->type = Order::class;
                $new_notifi->type_id = $ProposaleForPartner->proposale->order_id;
                $new_notifi->save();
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th, "Mobile", $request->getRequestUri());

            return AdapterHelper::sendResponse(false, 'Undefined error', 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }

    /**
     * Change status
     * Danh sách lịch sử tất cả các order báo giá của đối tác, các param dưới đây là đề search.
     * @bodyParam history_payment_id int required id của thanh toán đó. Example: 1
     * @bodyParam type_role string required đối tượng của user. Example: PARTNER
     */
    public function change_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposale_id' => 'required',
            'type_role' => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        $proposale_parent = '';
        if ($request->type_role == User::$const_type_role['CUSTOMER']) {
            $proposale_parent_id = ProposaleForCustomer::where('id', $request->proposale_id)
                ->first()->proposale_id;
            ProposaleForCustomer::where('id', $request->proposale_id)->update(['is_pay' => $request->status]);
        } else {
            $proposale_parent_id = ProposaleForPartner::where('id', $request->proposale_id)
                ->first()->proposale_id;
            ProposaleForPartner::where('id', $request->proposale_id)->update(['is_pay' => $request->status]);
        }

        // if()
        // CẬP NHẬT STATUS CHO ORDER
        $proposale_parent = Proposale::with('order')->find($proposale_parent_id);
        $check_proposale_customer = $proposale_parent->proposale_for_customer()->where('is_pay', '<>', 1)->first();
        $check_proposale_partner = $proposale_parent->proposale_for_partner()->where('is_pay', '<>', 1)->first();

        if (!$check_proposale_customer && !$check_proposale_partner && $proposale_parent->order->status == 9) {
            $proposale_parent->order->status = 11;
            $proposale_parent->order->save();
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'success');
    }
}
