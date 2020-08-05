<?php

namespace App\Traits;

use App\Jobs\SendNotifyMessage;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Propaganistas\LaravelPhone\PhoneNumber;

class AdapterHelper
{

/**
 * @param $array
 * @param null $key
 * @return array
 */
public static function unique_array($array,$key = null){
    if(null === $key){
        return array_unique($array);
    }
    $keys=[];
    $ret = [];
    foreach($array as $elem){
        $arrayKey = (is_array($elem))?$elem[$key]:$elem->$key;
        if(in_array($arrayKey,$keys)){
            continue;
        }
        $ret[] = $elem;
        array_push($keys,$arrayKey);
    }
    return $ret;
}
    public static function detect_phone($phone, $code_country = null)
    {
        $arrayCountry = ['BE', 'VN', 'US', 'AU', 'PH'];
        if ($code_country) {
            $arrayCountry[] = $code_country;
        }
        $detech = PhoneNumber::make($phone)->ofCountry($arrayCountry);

        try {
            $code_country = $detech->getCountry();
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            $phone = $detech->formatForMobileDialingInCountry($code_country);
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            $phone_format = $detech->get;
        } catch (\Throwable $th) {
            //throw $th;
        }
        return ['phone_code' => $code_country, 'phone' => $phone, 'phone_format' => $detech];
    }

    public static function delete_file($path)
    {
        try {
            //code...
            $storage_path = $path;
            $tmp = explode(env("APP_URL") . "storage/", $path);
            if (count($tmp) == 2) {
                $storage_path = trim($tmp[1], '/');
            }
            if (Storage::disk('public')->exists($storage_path)) {
                return Storage::disk('public')->delete($storage_path);
                // return true;
            }
            return false;
        } catch (\Throwable $th) {
            throw $th;
            return false;
        }
    }

    // upload base 64
    public static function upload_file($file, $dir, $file_change = null)
    {
        try {
            //code...
            if ($file_change) {
                AdapterHelper::delete_file($file_change);
            }
            $tmp = explode(',', $file);

            $exception = explode(';', $tmp[0]);
            $exception = explode('/', $exception[0]);
            $type_file = $exception[0];
            $exception = $exception[1];
            if ($type_file != 'data:image') {
                $final_exception = explode('.', $exception);
                if ($final_exception[count($final_exception) - 1] == 'sheet') {
                    $exception = 'xlsx';
                }
                if ($final_exception[count($final_exception) - 1] == 'document') {
                    $exception = 'docx';
                }
            }
            $file = $tmp[1];
            $file = base64_decode($file);
            $dir .= '.' . $exception;
            $status = Storage::disk('public')->put($dir, $file);
            if (!$status) {
                throw new Exception('Lỗi upload ảnh');
            }
        } catch (\Throwable $th) {
            throw $th;
        }
        return $dir;
    }
    public static function createSlug($text)
    {
        $text = mb_convert_encoding($text, 'utf-8', 'utf-8');
        return strtolower(preg_replace('/[^A-Za-z0-9-]+/', ' ', $text));
    }
    public static function createSKU($array, $id)
    {
        $res = "";
        foreach ($array as $value) {
            $res .= AdapterHelper::getFirstCharacter($value) . "-";
        }
        return $res . $id;
    }
    public static function getFirstCharacter($string)
    {
        // $string = AdapterHelper::createSlug($string);
        $string = static::url_slug($string);
        $string = trim($string, " ");
        $string = strtoupper($string);

        $res = $string[0];
        for ($i = 1; $i < strlen($string); $i++) {
            if (($string[$i - 1] == ' ' && $string[$i] != ' ') || ($string[$i - 1] == '-' && $string[$i] != '-')) {
                $res .= "-" . $string[$i];
            }
        }
        $res = trim($res, " ");
        return $res;
    }

    public static function json_object_request($request)
    {
        return json_decode(json_encode($request->json()->all()));
    }

    public static function sendResponse($status, $data, $code, $message = 'None message')
    {
        if ($status === true)
            return response()->json([
                'status' => $status,
                'message' => $message,
                'data' => $data
            ], $code);
        return response()->json([
            'status' => $status,
            'error' => $data,
            'message' => $message
        ], $code);
    }

    public static  function write_log_error($exception, $device, $url)
    {
        $message = "\n ------------" . date('H:i:s') . "-" . $device . "-------------\n";
        $message .= "url: " . $url;
        $message .= "\nMessage: " . $exception->getMessage();
        $message .= "\nFile: " . $exception->getFile();
        $message .= "\nLine: " . $exception->getLine();
        $message .= "\n ------------end------------\n";
        Log::stack(['state'])->info($message);
    }

    public static function ViewAndLogError($exception, $request)
    {
        static::write_log_error($exception, "Mobile", $request->getRequestUri());
        return static::sendResponse(false, 'Undefined Error', 500, $exception->getMessage());
    }
    public static function sendResponsePaginating($status = true, $data, $code, $message = 'None message')
    {
        $data = json_decode($data->toJson());

        $payload = $data->data;
        $temp = [];
        if (is_object($payload)) {
            foreach ($payload as $p) {
                $temp[] = $p;
            }
            $payload = $temp;
        }

        $pagination = array([
            "current_page" =>  $data->current_page,
            "from_record" =>  $data->from,
            "to_record" =>  $data->to,
            "total_record" =>  $data->total,
            "record_per_page" =>  (int) $data->per_page,
            "total_page" =>  $data->last_page,
        ])[0];
        return response()->json(
            [
                'status' => $status,
                'message' => $message,
                'pagination' => $pagination,
                'data' => $payload,
            ],
            $code
        );
    }

    public static function notifi_more($data, $content, $list_user = null, $more_any)
    {
        $filter = [];
        $filter['included_segments'] = [];
        $filter['filters'] = [];
        foreach ($list_user as $key => $value) {
            if (!$key) {
                $filter['filters'][] = ['key' => $key, 'value' => $value, 'relation' => '='];
            } else {
                $filter['filters'][] = ['key' => $key, 'value' => $value, 'relation' => '=', 'operator' => 'OR'];
            }
        }
        $more = [
            'data' => $data,
        ];
        foreach ($more_any as $key => $value) {
            $more[$key] = $value;
        }
        SendNotifyMessage::dispatch($filter, $content, $more);
    }

    public static function CurrencyIntToString($price)
    {
        $tmp = $price;
        $tmp = \str_replace('.', '', $tmp);
        $tmp = \str_replace(' ', '', $tmp);
        $tmp = \str_replace(',', '', $tmp);
        return (int) $tmp;
    }

    public static function url_slug($text)
    {
        $replace = [
            '&lt;' => '', '&gt;' => '', '&#039;' => '', '&amp;' => '',
            '&quot;' => '', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae',
            '&Auml;' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A', 'Æ' => 'Ae',
            'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Ď' => 'D', 'Đ' => 'D',
            'Ð' => 'D', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E',
            'Ę' => 'E', 'Ě' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ĝ' => 'G', 'Ğ' => 'G',
            'Ġ' => 'G', 'Ģ' => 'G', 'Ĥ' => 'H', 'Ħ' => 'H', 'Ì' => 'I', 'Í' => 'I',
            'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I',
            'İ' => 'I', 'Ĳ' => 'IJ', 'Ĵ' => 'J', 'Ķ' => 'K', 'Ł' => 'K', 'Ľ' => 'K',
            'Ĺ' => 'K', 'Ļ' => 'K', 'Ŀ' => 'K', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N',
            'Ņ' => 'N', 'Ŋ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ö' => 'Oe', '&Ouml;' => 'Oe', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O', 'Ŏ' => 'O',
            'Œ' => 'OE', 'Ŕ' => 'R', 'Ř' => 'R', 'Ŗ' => 'R', 'Ś' => 'S', 'Š' => 'S',
            'Ş' => 'S', 'Ŝ' => 'S', 'Ș' => 'S', 'Ť' => 'T', 'Ţ' => 'T', 'Ŧ' => 'T',
            'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ū' => 'U',
            '&Uuml;' => 'Ue', 'Ů' => 'U', 'Ű' => 'U', 'Ŭ' => 'U', 'Ũ' => 'U', 'Ų' => 'U',
            'Ŵ' => 'W', 'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ž' => 'Z',
            'Ż' => 'Z', 'Þ' => 'T', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
            'ä' => 'ae', '&auml;' => 'ae', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
            'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c',
            'ď' => 'd', 'đ' => 'd', 'ð' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
            'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĕ' => 'e', 'ė' => 'e',
            'ƒ' => 'f', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h',
            'ħ' => 'h', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ĩ' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j',
            'ķ' => 'k', 'ĸ' => 'k', 'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ļ' => 'l',
            'ŀ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n', 'ŉ' => 'n',
            'ŋ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'oe',
            '&ouml;' => 'oe', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ŏ' => 'o', 'œ' => 'oe',
            'ŕ' => 'r', 'ř' => 'r', 'ŗ' => 'r', 'š' => 's', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ü' => 'ue', 'ū' => 'u', '&uuml;' => 'ue', 'ů' => 'u', 'ű' => 'u',
            'ŭ' => 'u', 'ũ' => 'u', 'ų' => 'u', 'ŵ' => 'w', 'ý' => 'y', 'ÿ' => 'y',
            'ŷ' => 'y', 'ž' => 'z', 'ż' => 'z', 'ź' => 'z', 'þ' => 't', 'ß' => 'ss',
            'ſ' => 'ss', 'ый' => 'iy', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F',
            'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '',
            'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a',
            'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
            'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya'
        ];

        // make a human readable string
        $text = strtr($text, $replace);

        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d.]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // remove unwanted characters
        $text = preg_replace('~[^-\w.]+~', '', $text);

        $text = strtolower($text);

        return $text;
    }
}
