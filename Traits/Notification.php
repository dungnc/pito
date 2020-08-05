<?php
namespace App\Traits;

use App\Traits\NotificationHelper;

class Notification{
    use NotificationHelper;
    private static $app_id;
    private static $rest_api_key ;
    private static $user_auth_key;
    private static $api_access_key;

    /**
     * @return mixed
     */
    public static function getAppId()
    {
        return self::$app_id;
    }

    /**
     * @return mixed
     */
    public static function getRestApiKey()
    {
        return self::$rest_api_key;
    }

    /**
     * @return mixed
     */
    public static function getUserAuthKey()
    {
        return self::$user_auth_key;
    }

    /**
     * @return api_access_key
     */
    public static function getApiAccessKey()
    {
        return self::$api_access_key;
    }

    /**
     * @param mixed $app_id
     */
    public static function setAppId($app_id)
    {
        self::$app_id = $app_id;
    }

    /**
     * @param mixed $rest_api_key
     */
    public static function setRestApiKey($rest_api_key)
    {
        self::$rest_api_key = $rest_api_key;
    }

    /**
     * @param mixed $user_auth_key
     */
    public static function setUserAuthKey($user_auth_key)
    {
        self::$user_auth_key = $user_auth_key;
    }

    /**
     * @param mixed $api_access_key
     */
    public static function setApiAccsessKey($api_access_key)
    {
        self::$api_access_key = $api_access_key;
    }

    public function __construct()
    {
        self::$app_id = env('NOTI_APP_ID','fb60a1d8-6e30-4fb7-b570-d4c91227e343');
        self::$rest_api_key = env('NOTI_REST_API_KEY','NGUzZjAxODAtNjYwMC00YzIzLWIzOGQtZGFiMTdhODhmOGM5');
        self::$user_auth_key = env('NOTI_USER_AUTH_KEY','MDA4ZDJjMmItZGQ2Yy00YjI2LTgyNDktYTFhYTRkMjhjODg3');
        self::$api_access_key = env('NOTI_API_ACCESS_KEY','AAAANPWk4pU:APA91bHcuO6kBGi8xmhE95w3Ed5J5-ncXKy1mi4auXkC0t_YCGTiGhAQMVY8WuRJXg_Pc6ua1u8E3Aj0MNF59Y-Sgbuf0kNN_XW8SvQMHo8vGvWTOk0e2fJhRsLJuF9F2T_J9JP2dEb6GgHetF7riYe1yTFfXZS2Aw');
    }

}