<?php

/**
 * Description of Enviroment
 *
 * @author bajt
 */

namespace Wbengine\Application\Env\Stac;


use Wbengine\Application\ApplicationException;

class Utils
{


    /**
     * Static class - cannot be instantiated.
     */
    final public function __construct(){
        require_once 'Class/SessionException.php';
        throw new ApplicationException("Cannot instantiate static class " . get_class($this));
    }


    /**
     * return User's agent.
     * @return string
     */
    static function getUserAgent(){
        return $_SERVER['HTTP_USER_AGENT'];
    }


    /**
     * Return Default hostname include protocol
     * @param type $protocol
     * @return string
     */
    static function getHost($protocol = NULL){
        return (empty($protocol)) ? "http://" . $_SERVER['HTTP_HOST'] : $protocol . "://" . $_SERVER['HTTP_HOST'];
    }


    /**
     * Return user's IP.
     * @return string
     */
    static function getUserIp()
    {
        if (isset($_SERVER["REMOTE_ADDR"])) {
            return $_SERVER["REMOTE_ADDR"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
            return $_SERVER["HTTP_CLIENT_IP"];
        }
    }


    /**
     * Check for valid email address.
     *
     * @param string $email
     * @return boolean
     */
    static function checkValidEmail($email)
    {
        if (preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $email)) {
            list($username, $domain) = explode('@', $email);
            if (!checkdnsrr($domain, 'MX')) {
                return false;
            }
            return true;
        }
        return false;
    }


    /**
     * Create URL from given string related to url.
     * @param string $url
     * @param string $html
     * @return string
     */
    static function createUrl($url, $html = false){
        return $tmp = self::createSeo($url) . (($html) ? '.html' : '/');
    }


    public static function compareStrings($expected, $actual)
    {
        $expected = (string)$expected;
        $actual = (string)$actual;
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }
        $lenExpected = strlen($expected);
        $lenActual = strlen($actual);
        $len = min($lenExpected, $lenActual);
        $result = 0;
        for ($i = 0; $i < $len; $i++) {
            $result |= ord($expected[$i]) ^ ord($actual[$i]);
        }
        $result |= $lenExpected ^ $lenActual;
        return ($result === 0);
    }


    /**
     * This function takes 2 arguments, an IP address and a "range" in several
     * different formats.
     *
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     *
     * The function will return true if the supplied IP is within the range.
     * Note little validation is done on the range inputs - it expects you to
     * use one of the above 3 formats.
     */
    static function ipInRange($ip, $range)
    {
        if (strpos($range, '/') !== false) {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);
            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                return ((ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec));
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                while (count($x) < 4) $x[] = '0';
                list($a, $b, $c, $d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);

                # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

                # Strategy 2 - Use math to create it
                $wildcard_dec = pow(2, (32 - $netmask)) - 1;
                $netmask_dec = ~$wildcard_dec;

                return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
            }
        } else {
            // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
            if (strpos($range, '*') !== false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }

            if (strpos($range, '-') !== false) { // A-B format
                list($lower, $upper) = explode('-', $range, 2);
                $lower_dec = (float)sprintf("%u", ip2long($lower));
                $upper_dec = (float)sprintf("%u", ip2long($upper));
                $ip_dec = (float)sprintf("%u", ip2long($ip));
                return (($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec));
            }

            return false;
        }

    }

    static function resolveEnvironmentByHostname($hostname, $keyword){
        if(preg_match("/{$keyword}/", $hostname, $mathes)){
            return (boolean)$mathes;
        }
    }

    public static function dump($var, $stop = false)
    {
        echo('<pre>');
        print_r($var);
        echo('</pre>');
        if ($stop) {
            $e = new \Exception;
            echo('<pre>');
            print_r($e->getTraceAsString());
            echo('</pre>');
            die('__STOPPED__');
        }
    }
}
