<?php

namespace App\Helpers;

use App\Model\ShortLink;
use App\Traits\AdapterHelper;

class Shorty
{
    /**
     * Default characters to use for shortening.
     *
     * @var string
     */
    private static $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Salt for id encoding.
     *
     * @var string
     */
    private static $salt = '';

    /**
     * Length of number padding.
     */
    private static $padding = 1;

    /**
     * Hostname
     */
    private static $hostname = '';


    /**
     * Whitelist of IPs allowed to save URLs.
     * If the list is empty, then any IP is allowed.
     *
     * @var array
     */
    private static $whitelist = array();

    /**
     * Constructor
     *
     * @param string $hostname Hostname
     */
    public function __construct($hostname = null)
    {
        if ($hostname) {
            self::$hostname = $hostname;
        } else {
            self::$hostname = url('');
        }
    }

    /**
     * Gets the character set for encoding.
     *
     * @return string Set of characters
     */
    public function get_chars()
    {
        return self::chars;
    }

    /**
     * Sets the character set for encoding.
     *
     * @param string $chars Set of characters
     */
    public function set_chars($chars)
    {
        if (!is_string($chars) || empty($chars)) {
            throw new Exception('Invalid input.');
        }
        self::$chars = $chars;
    }

    /**
     * Gets the salt string for encoding.
     *
     * @return string Salt
     */
    public function get_salt()
    {
        return self::$salt;
    }

    /**
     * Sets the salt string for encoding.
     *
     * @param string $salt Salt string
     */
    public function set_salt($salt)
    {
        self::$salt = $salt;
    }

    /**
     * Gets the padding length.
     *
     * @return int Padding length
     */
    public function get_padding()
    {
        return self::$padding;
    }

    /**
     * Sets the padding length.
     *
     * @param int $padding Padding length
     */
    public function set_padding($padding)
    {
        self::$padding = $padding;
    }

    /**
     * Converts an id to an encoded string.
     *
     * @param int $n Number to encode
     * @return string Encoded string
     */
    public static function encode($n)
    {
        $k = 0;
        if (self::$padding > 0 && !empty(self::$salt)) {
            $k = self::get_seed($n, self::$salt, self::$padding);
            $n = (int) ($k . $n);
        }
        return self::num_to_alpha($n, self::$chars);
    }

    /**
     * Converts an encoded string into a number.
     *
     * @param string $s String to decode
     * @return int Decoded number
     */
    public static function decode($s)
    {
        $n = self::alpha_to_num($s, self::$chars);

        return (!empty(self::$salt)) ? substr($n, self::$padding) : $n;
    }

    /**
     * Gets a number for padding based on a salt.
     *
     * @param int $n Number to pad
     * @param string $salt Salt string
     * @param int $padding Padding length
     * @return int Number for padding
     */
    public static function get_seed($n, $salt, $padding)
    {
        $hash = md5($n . $salt);
        $dec = hexdec(substr($hash, 0, $padding));
        $num = $dec % pow(10, $padding);
        if ($num == 0) $num = 1;
        $num = str_pad($num, $padding, '0');
        return $num;
    }

    /**
     * Converts a number to an alpha-numeric string.
     *
     * @param int $num Number to convert
     * @param string $s String of characters for conversion
     * @return string Alpha-numeric string
     */
    public static function num_to_alpha($n, $s)
    {
        $b = strlen($s);
        $m = $n % $b;
        if ($n - $m == 0) return substr($s, $n, 1);
        $a = '';
        while ($m > 0 || $n > 0) {
            $a = substr($s, $m, 1) . $a;
            $n = ($n - $m) / $b;
            $m = $n % $b;
        }
        return $a;
    }

    /**
     * Converts an alpha numeric string to a number.
     *
     * @param string $a Alpha-numeric string to convert
     * @param string $s String of characters for conversion
     * @return int Converted number
     */
    public static function alpha_to_num($a, $s)
    {
        $b = strlen($s);
        $l = strlen($a);

        for ($n = 0, $i = 0; $i < $l; $i++) {
            $n += strpos($s, substr($a, $i, 1)) * pow($b, $l - $i - 1);
        }

        return $n;
    }

    /**
     * Looks up a URL in the database by id.
     *
     * @param string $id URL id
     * @return array URL record
     */
    public static function fetch($id)
    {
        return ShortLink::find($id);
    }

    /**
     * Attempts to locate a URL in the database.
     *
     * @param string $url URL
     * @return array URL record
     */
    public static function find($url)
    {
        return ShortLink::where('url', $url)->first();
    }

    /**
     * Stores a URL in the database.
     *
     * @param string $url URL to store
     * @return int Insert id
     */
    public static function store($url)
    {
        $datetime = date('Y-m-d H:i:s');
        return ShortLink::create(['url' => $url])->id;
    }

    /**
     * Updates statistics for a URL.
     *
     * @param int $id URL id
     */
    public static function update($id)
    {
        $datetime = date('Y-m-d H:i:s');

        $short =  ShortLink::find($id);
        $short->hits = (int) $short->hits + 1;
        $short->accessed = $datetime;
        return $short->save();
    }

    /**
     * Sends a redirect to a URL.
     *
     * @param string $url URL
     */
    public static function redirect($url)
    {
        header("Location: $url", true, 301);
        exit();
    }

    /**
     * Sends a 404 response.
     */
    public function not_found()
    {
        header('Status: 404 Not Found');
        exit('<h1>404 Not Found</h1>' .
            str_repeat(' ', 512));
    }

    /**
     * Sends an error message.
     *
     * @param string $message Error message
     */
    public function error($message)
    {
        exit("<h1>$message</h1>");
    }

    /**
     * Adds an IP to allow saving URLs.
     *
     * @param string|array $ip IP address or array of IP addresses
     */
    public function allow($ip)
    {
        if (is_array($ip)) {
            self::$whitelist = array_merge(self::$whitelist, $ip);
        } else {
            array_push(self::$whitelist, $ip);
        }
    }

    public  static function create_short_link($url)
    {
        if (preg_match('/^http[s]?\:\/\/[\w]+/', $url)) {
            $result = self::find($url);
            // Not found, so save it
            if (empty($result)) {
                $id = static::store($url);
                $url = self::$hostname . '/' . self::encode($id);
            } else {
                $url = self::$hostname . '/' . self::encode($result['id']);
            }
            return $url;
        }
        throw new Exception("Bad input", 1);
    }
    /**
     * Starts the program.
     */
    // public function run($q = [])
    // {
    //     $url = '';
    //     if (isset($_GET['url'])) {
    //         $url = urldecode($_GET['url']);
    //     }
    //     $format = '';
    //     if (isset($_GET['format'])) {
    //         $format = strtolower($_GET['format']);
    //     }

    //     // If adding a new URL
    //     if (!empty($url)) {
    //         if (!empty(self::whitelist) && !in_array($_SERVER['REMOTE_ADDR'], $this->whitelist)) {
    //             $this->error('Not allowed.');
    //         }
    //         if (preg_match('/^http[s]?\:\/\/[\w]+/', $url)) {
    //             $result = $this->find($url);
    //             // Not found, so save it
    //             if (empty($result)) {
    //                 $id = $this->store($url);
    //                 $url = $this->hostname . '/short-link/' . $this->encode($id);
    //             } else {
    //                 $url = $this->hostname . '/short-link/' . $this->encode($result['id']);
    //             }
    //             return AdapterHelper::sendResponse(true, $url, 200, 'Success');
    //             // Display the shortened url
    //             // switch ($format) {
    //             //     case 'text':
    //             //         exit($url);

    //             //     case 'json':
    //             //         header('Content-Type: application/json');
    //             //         exit(json_encode(array('url' => $url)));

    //             //     case 'xml':
    //             //         header('Content-Type: application/xml');
    //             //         exit(implode("\n", array(
    //             //             '<?xml version="1.0"?' . '>',
    //             //             '<response>',
    //             //             '  <url>' . htmlentities($url) . '</url>',
    //             //             '</response>'
    //             //         )));
    //             //     default:
    //             //         exit('<a href="' . $url . '">' . $url . '</a>');
    //             // }
    //         } else {
    //             $this->error('Bad input.');
    //         }
    //     }
    //     // Lookup by id
    //     else {
    //         if (empty($q)) {
    //             $this->not_found();
    //             return;
    //         }
    //         if (preg_match('/^([a-zA-Z0-9]+)$/', $q, $matches)) {
    //             $id = self::decode($matches[1]);
    //             $result = $this->fetch($id);

    //             if (!empty($result)) {
    //                 $this->update($id);

    //                 $this->redirect($result['url']);
    //             } else {
    //                 $this->not_found();
    //             }
    //         }
    //     }
    // }
}
