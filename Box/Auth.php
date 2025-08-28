<?php

namespace Wbengine\Box;

use Wbengine\Application\Env\Http;
use Wbengine\Box\Exception\BoxException;
use Wbengine\Box\WbengineBoxAbstract;
use Wbengine\Site\SiteException;
use Wbengine\User;

class Auth extends WbengineStaticBox
{
    // Google recaptcha secret key
    const SECRET_KEY = '6LeDc0UUAAAAAGTStMVRhObItiIWuGZn3XDS_e-Q';
    const CAPTCHA_RESPONSE = 'g-recaptcha-response';
    const CAPTCHA_RESPONSE_AJAX = 'grecaptcha';
    const FAILED_LOGINS = 'failed_logins_count';
    const SECRET_TOKEN = 'token';
    const FAILED_LOGINS_LIMIT = 3;

    public static function getIndexBox()
    {
        return __CLASS__ . __METHOD__;
    }

    /**
     * @return string
     * @throws \Wbengine\Exception\RuntimeException
     */
    public function getLoginBox()
    {
        try {
            // Ajax handling...
            if (Http::isAjaxCall() === true) {
                $this->_ajaxLogin();
            }

            $usr = new User($this);
            // checking for logout requets...
            if (Http::getParam('a') === "logout") {
                $usr->logout();
                header('Location: /login/');
                exit();
            }

            if (Http::getRequestMethod(Http::TYPE_POST) === true) {
                // ...grab failed logins from session...
                $failed_logins = (int)$this->getSession()->getValue(self::FAILED_LOGINS);
                // ... success captcha if need...?
                if ($this->_checkCaptcha() === true) {
                    // ..success token...?
                    if ($this->_checkToken() === true) {
                        try {
                            // Authenticate...
                            $loginResponse = $usr->login((string)Http::Post('userName'), (string)Http::Post('userPassword'));
                            // ...success login...?
                            if ($loginResponse === true) {
                                $this->getSession()->unsetKey(self::FAILED_LOGINS);
                                $this->getSession()->unsetKey(self::SECRET_TOKEN);

                                if (Http::isAjaxCall() === true) {
                                    die('OK');
                                } else {
                                    header('Location: /');
                                    exit();
                                }
                            } else {
                                $this->getRenderer()->assign('error', 'Login failed');
                                $this->getSession()->setValue(self::FAILED_LOGINS, ++$failed_logins);
                            }
                        } catch (User\UserException $e) {
                            $this->getRenderer()->assign('error', (string)$e->getMessage());
                        }
                    } else {
                        $this->getRenderer()->assign('error', 'Invalid Token.');
                    }
                } else {
                    $this->getRenderer()->assign('error', 'Invalid Captcha.');
                }
            }
            // Generate token for lates check...
            $token = $this->_generateToken();
            // Store generated token to session (db)...
            $this->getSession()->setValue('token', $token);
            // Set token to form template...
            $this->getRenderer()->assign('token', $token, true);
            // Tell to template whatever we need to show captcha...
            $this->getRenderer()->assign('captcha', $this->_showCaptcha(), true);

            if (Http::isAjaxCall() === true) {
                die($this->getRenderer()->render($this->getStaticBoxTemplatePath(self::BOX_LOGIN), $_POST, true));
            } else {
                return $this->getRenderer()->render($this->getStaticBoxTemplatePath(self::BOX_LOGIN), $_POST, true);
            }
        } catch (BoxException $e) {
            throw new SiteException($e->getMessage());
        }
    }

    private function _ajaxLogin()
    {
        $response = array();
        try {
            $usr = new User($this);
            // ...grab failed logins from session...
            $failed_logins = (int)$this->getSession()->getValue(self::FAILED_LOGINS);
            // ... success captcha if need...?
            if ($this->_checkCaptcha() === true) {
                // ..success token...?
                if ($this->_checkToken() === true) {
                    $res = $usr->login((string)Http::Post('userName'), (string)Http::Post('userPassword'));
                    if ($res === true) {
                        $this->getSession()->unsetKey(self::FAILED_LOGINS);
                        $this->getSession()->unsetKey(self::SECRET_TOKEN);
                        die('OK');
                    } else {
                        $this->getSession()->setValue(self::FAILED_LOGINS, ++$failed_logins);
                        $response['error'] = 'Login failed.';
                        $response['tries'] = $failed_logins;
                        header('Content-type: application/json');
                        die(json_encode($response));
                    }
                } else {
                    $response['error'] = 'Invalid Token.';
                    $response['tries'] = $failed_logins;
                    header('Content-type: application/json');
                    die(json_encode($response));
                }
            } else {
                $response['error'] = 'Invalid captcha.';
                $response['code'] = 4;
                $response['tries'] = $failed_logins;
                header('Content-type: application/json');
                die(json_encode($response));
            }
        } catch (User\UserException $e) {
            die($e->getMessage());
        }
    }

    private function _checkCaptcha()
    {
        if (Http::isAjaxCall() === true) {
            $captcha = Http::Post(self::CAPTCHA_RESPONSE_AJAX);
        } else {
            $captcha = Http::Post(self::CAPTCHA_RESPONSE);
        }
        if ($this->_showCaptcha() === false) {
            return true;
        }
        $response = json_decode(
            file_get_contents(
                sprintf("https://www.google.com/recaptcha/api/siteverify?secret=%s&response=%s&remoteip=%s"
                    , self::SECRET_KEY
                    , $captcha
                    , $_SERVER['REMOTE_ADDR']
                )
            ), true
        );
        return (bool)$response['success'];
    }

    private function _generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    private function _checkToken()
    {
        $token = $this->getSession()->getValue('token');
        $tpost = Http::Post('token');
        if ($token === null || $token === '') {
            return false;
        } elseif ($tpost === null || $tpost === '') {
            return false;
        }
        return (hash_equals($tpost, $token)) ? true : false;
    }

    private function _showCaptcha()
    {
        return ($this->getSession()->getValue(self::FAILED_LOGINS) > self::FAILED_LOGINS_LIMIT) ? true : false;
    }
}