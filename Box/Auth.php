<?php

namespace Wbengine\Box;

use Wbengine\Application\Env\Http;
use Wbengine\Config;
use Wbengine\I18n;
use Wbengine\Box\Exception\BoxException;
use Wbengine\Box\WbengineBoxAbstract;
use Wbengine\Site\SiteException;
use Wbengine\User;

class Auth extends WbengineStaticBox
{
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
            if (Http::getParam('a') === 'logout' || Http::PostString('a') === 'logout') {
                if (!Http::getRequestMethod(Http::TYPE_POST)) {
                    header('Allow: POST');
                    http_response_code(405);
                    return 'Method Not Allowed';
                }
                if (!$this->getSession()->getCsrf()->validateRequest('auth.logout')) {
                    http_response_code(403);
                    return I18n::t('wb.login.invalid_token');
                }
                (new User($this))->logout();
                header('Location: ' . I18n::url('/login/'));
                exit();
            }
            if (Http::isAjaxCall() === true) {
                if (!Http::getRequestMethod(Http::TYPE_POST)) {
                    header('Allow: POST');
                    http_response_code(405);
                    return 'Method Not Allowed';
                }
                $this->_ajaxLogin();
            }

            $usr = new User($this);
            if (Http::getRequestMethod(Http::TYPE_POST) && !$this->_checkToken()) {
                http_response_code(403);
                $this->getRenderer()->assign('error', I18n::t('wb.login.invalid_token'));
            } elseif (Http::getRequestMethod(Http::TYPE_POST) === true) {
                // ...grab failed logins from session...
                $failed_logins = (int)$this->getSession()->getValue(self::FAILED_LOGINS);
                // ... success captcha if need...?
                if ($this->_checkCaptcha() === true) {
                    // ..success token...?
                    if ($this->_checkToken() === true) {
                        try {
                            // Authenticate...
                            $loginResponse = $usr->login((string)Http::PostRaw('userName'), (string)Http::PostRaw('userPassword'));
                            // ...success login...?
                            if ($loginResponse === true) {
                                $this->getSession()->unsetKey(self::FAILED_LOGINS);

                                if (Http::isAjaxCall() === true) {
                                    die('OK');
                                } else {
                                    header('Location: /');
                                    exit();
                                }
                            } else {
                                $this->getRenderer()->assign('error', I18n::t('wb.login.failed'));
                                $this->getSession()->setValue(self::FAILED_LOGINS, ++$failed_logins);
                            }
                        } catch (User\TooManyAttemptsException $e) {
                            $this->getRenderer()->assign('error', I18n::t('wb.login.too_many'));
                        } catch (User\UserException $e) {
                            $this->getRenderer()->assign('error', (string)$e->getMessage());
                        }
                    } else {
                        $this->getRenderer()->assign('error', I18n::t('wb.login.invalid_token'));
                    }
                } else {
                    $this->getRenderer()->assign('error', I18n::t('wb.login.invalid_captcha'));
                }
            }
            // The labels of the form, in the language of the request.
            $this->getRenderer()->assign('formAction', I18n::url('/login/'));
            $this->getRenderer()->assign('t', array(
                'title' => I18n::t('wb.login.title'),
                'username' => I18n::t('wb.login.username'),
                'password' => I18n::t('wb.login.password'),
                'submit' => I18n::t('wb.login.submit'),
            ));
            $this->getRenderer()->assign('token', $this->getSession()->getCsrf()->token('auth.login'), true);
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

    /** Render this before entering a shared template cache block. */
    public function getLogoutBox()
    {
        $this->getRenderer()->assign('logoutAction', I18n::url('/login/'));
        $this->getRenderer()->assign('logoutLabel', I18n::t('wb.logout.submit'));
        $this->getRenderer()->assign('token', $this->getSession()->getCsrf()->token('auth.logout'), true);
        return $this->getRenderer()->render('Login/View/Logout', null, true);
    }

    private function _ajaxLogin()
    {
        $response = array();
        try {
            if (!$this->_checkToken()) {
                http_response_code(403);
                header('Content-type: application/json');
                die(json_encode(['error' => I18n::t('wb.login.invalid_token')]));
            }
            $usr = new User($this);
            // ...grab failed logins from session...
            $failed_logins = (int)$this->getSession()->getValue(self::FAILED_LOGINS);
            // ... success captcha if need...?
            if ($this->_checkCaptcha() === true) {
                // ..success token...?
                if ($this->_checkToken() === true) {
                    $res = $usr->login((string)Http::PostRaw('userName'), (string)Http::PostRaw('userPassword'));
                    if ($res === true) {
                        $this->getSession()->unsetKey(self::FAILED_LOGINS);
                        die('OK');
                    } else {
                        $this->getSession()->setValue(self::FAILED_LOGINS, ++$failed_logins);
                        $response['error'] = I18n::t('wb.login.failed');
                        $response['tries'] = $failed_logins;
                        header('Content-type: application/json');
                        die(json_encode($response));
                    }
                } else {
                    $response['error'] = I18n::t('wb.login.invalid_token');
                    $response['tries'] = $failed_logins;
                    header('Content-type: application/json');
                    die(json_encode($response));
                }
            } else {
                $response['error'] = I18n::t('wb.login.invalid_captcha');
                $response['code'] = 4;
                $response['tries'] = $failed_logins;
                header('Content-type: application/json');
                die(json_encode($response));
            }
        } catch (User\TooManyAttemptsException $e) {
            http_response_code(429);
            header('Retry-After: ' . $e->getRetryAfter());
            header('Content-type: application/json');
            die(json_encode(array('error' => $e->getMessage(), 'retry_after' => $e->getRetryAfter())));
        } catch (User\UserException $e) {
            die($e->getMessage());
        }
    }

    private function _checkCaptcha()
    {
        if (Http::isAjaxCall() === true) {
            $captcha = Http::PostString(self::CAPTCHA_RESPONSE_AJAX);
        } else {
            $captcha = Http::PostString(self::CAPTCHA_RESPONSE);
        }
        if ($this->_showCaptcha() === false) {
            return true;
        }
        if (null === $captcha || '' === $captcha) {
            return false;
        }
        $secret = Config::getRecaptchaSecret();
        if (!$secret) {
            // Not configured: fail closed.
            return false;
        }
        return $this->verifyCaptcha($secret, $captcha);
    }

    /** Network boundary; malformed input is rejected by _checkCaptcha before this call. */
    protected function verifyCaptcha($secret, string $captcha): bool
    {
        $raw = @file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?" . http_build_query(array(
                'secret' => $secret,
                'response' => (string)$captcha,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            )),
            false,
            stream_context_create(array('http' => array('timeout' => 5)))
        );
        $response = ($raw === false) ? null : json_decode($raw, true);
        return is_array($response) && !empty($response['success']);
    }

    private function _checkToken()
    {
        return $this->getSession()->getCsrf()->validateRequest('auth.login');
    }

    private function _showCaptcha()
    {
        return ($this->getSession()->getValue(self::FAILED_LOGINS) > self::FAILED_LOGINS_LIMIT) ? true : false;
    }
}
