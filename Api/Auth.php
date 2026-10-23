<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 21:19
 */

namespace Wbengine\Api;


use Wbengine\Api;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Application\Env\Http;
use Wbengine\User;
use Wbengine\User\TooManyAttemptsException;
use Wbengine\Api\Auth\Exception\AuthException;
use Wbengine\Api\Auth\Exception\InvalidRefreshTokenException;
use Wbengine\Api\Auth\RefreshTokens;

class Auth extends WbengineRestapiAbstract implements WbengineRestapiInterface {

    const PWD_COMPLEXITY_MIN_LENGTH = 6;

    private $_user = null;
    protected $_username = null;
    protected $_password = null;


    private function User() {
        if(null === $this->_user) {
            $this->setUser(new User($this));
            return $this->getUser()->useJwt(true);
        } else {
            return $this->_user;
        }
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->_user;
    }

    public function setUser(User $user){
        $this->_user = $user;
    }

    public function login($data) {
        try {
            $status = $this->User()->login($this->validate($data)->_username, $this->validate($data)->_password);
            if(!$status) {
                throw new Api\Exception\ApiException("Login failed, wrong username or password.");
            }

            $this->_issueRefreshCookie((int) $this->User()->getUserId());

            $this->Api()->toJson(
                array(
                    "success" => $status,
                    "token" => $this->User()->getJwtToken($this->Api()->getJwtTokenExpiration()),
                    "uid" => $this->User()->getUserId(),
                ), ($status)? Http::OK : Http::UNAUTHORIZED
            );

        }catch (TooManyAttemptsException $e){
            Http::PrintHeader('Retry-After: ' . $e->getRetryAfter());
            $this->Api()->toJson(Array("success"=>false, "message"=>$e->getMessage(), "retry_after"=>$e->getRetryAfter()), Http::TOO_MANY_REQUESTS);
        }catch (\Exception $e){
            $this->Api()->toJson(Array("success"=>false, "message"=>$e->getMessage()), Http::UNAUTHORIZED);
        }
    }

    /**
     * Log out this device: revoke the refresh token family of the cookie, clear the
     * cookie and end the PHP session. A short lived access token that was already
     * issued stays valid until it expires.
     */
    public function logout() {
        $this->_revokeCookieToken();
        $this->_clearRefreshCookie();

        $this->Api()->toJson(
            Array(
                "success" => $this->User()->logout(),
                "message"=> "Successfully logged out"
            )
        );
    }

    /**
     * Log out everywhere: revoke every refresh token of the authenticated user.
     */
    public function logoutAll() {
        $this->isAuthenticated(function ($payload, $actor) {
            $count = $this->_refreshTokens()->revokeAllForUser((int) $actor['user_id']);
            $this->_clearRefreshCookie();
            $this->Api()->toJson(Array("success" => true, "revoked" => $count));
        }, \Wbengine\User\Role::USER);
    }

    /**
     * Check an access token and that it belongs to the user id in the request body.
     * Stateless: it does not depend on a PHP session.
     */
    public function validateJwtToken(?string $token = null, $body = null){
        if(null === $body || !is_array($body) || !array_key_exists("user_id", $body)) {
            $this->Api()->toJson(
                Array(
                    "success" => false,
                    "message"=> "Empty UID"
                ),Http::UNAUTHORIZED
            );
        }

        try {
            $payload = $this->wbAuth()->getDecodedData((string) $token);
        } catch (\Throwable $e) {
            $this->Api()->toJson(Array("success" => false, "message" => "Invalid token."), Http::UNAUTHORIZED);
        }

        $ok = (int) $body["user_id"] === (int) ($payload["data"]->user_id ?? 0);
        $this->Api()->toJson(
            Array(
                "success" => $ok,
                "user_id" => (int) ($payload["data"]->user_id ?? 0),
                "message" => $ok ? "The token is valid for this user." : "The token belongs to another user."
            ), $ok ? Http::OK : Http::UNAUTHORIZED
        );
    }

    /**
     * Exchange the refresh token cookie for a new access token. The refresh token is
     * rotated: the cookie is replaced by a new token and the old one stops working.
     * Presenting an old token again revokes the whole family (see RefreshTokens).
     * It needs no PHP session, only the cookie.
     */
    public function validateRefreshToken(){
        $token = $_COOKIE[self::REFRESH_COOKIE] ?? null;

        try {
            $rotated = $this->_refreshTokens()->rotate(
                $token,
                $this->Api()->getRefreshTokenTtl(),
                (string) \Wbengine\Application\Env\Stac\Utils::getUserIp(),
                (string) \Wbengine\Application\Env\Stac\Utils::getUserAgent()
            );
        } catch (InvalidRefreshTokenException $e) {
            error_log(__METHOD__ . ': refresh refused (' . $e->getReason() . ')');
            $this->_clearRefreshCookie();
            $this->Api()->toJson(
                Array("success" => false, "message" => "Invalid refresh token, has expired or unavailable."),
                Http::UNAUTHORIZED
            );
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage() . ' (is cms_refresh_tokens present?)');
            $this->Api()->toJson(Array("success" => false, "message" => "Refresh is not available."), Http::UNAUTHORIZED);
        }

        $this->_setRefreshCookie($rotated['token'], time() + $this->Api()->getRefreshTokenTtl());
        $this->User()->loadUserDataFromModel($rotated['user_id']);

        $this->Api()->toJson(
            Array(
                "success" => true,
                "user_id" => $rotated['user_id'],
                "token" => $this->User()->createJwtToken($this->Api()->getJwtTokenExpiration()),
                "message" => "The new JWT token has successfuly created."
            ), Http::OK
        );
    }

    const REFRESH_COOKIE = 'refresh_token';

    private $_refreshTokens = null;

    private function _refreshTokens() {
        return $this->_refreshTokens ?? ($this->_refreshTokens = new RefreshTokens());
    }

    /**
     * Start a refresh token family for a user who just logged in. If the token table
     * is missing the login still works, only refreshing is unavailable.
     */
    private function _issueRefreshCookie($userId) {
        try {
            $token = $this->_refreshTokens()->issue(
                $userId,
                $this->Api()->getRefreshTokenTtl(),
                null,
                (string) \Wbengine\Application\Env\Stac\Utils::getUserIp(),
                (string) \Wbengine\Application\Env\Stac\Utils::getUserAgent()
            );
            $this->_setRefreshCookie($token, time() + $this->Api()->getRefreshTokenTtl());
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': no refresh token issued: ' . $e->getMessage() . ' (is cms_refresh_tokens present?)');
        }
    }

    private function _revokeCookieToken() {
        $token = $_COOKIE[self::REFRESH_COOKIE] ?? null;
        if (is_string($token)) {
            try {
                $this->_refreshTokens()->revoke($token);
            } catch (\Throwable $e) {
                error_log(__METHOD__ . ': ' . $e->getMessage());
            }
        }
    }

    private function _setRefreshCookie($value, $expires) {
        setcookie(self::REFRESH_COOKIE, $value, array(
            'expires' => $expires,
            'path' => $this->Api()->getCookieUrl(),
            'domain' => $this->Api()->getCookieDomain(),
            'secure' => $this->Api()->getCookieIsSecured(),
            'httponly' => $this->Api()->getCookieIsHttpOnly(),
            'samesite' => $this->Api()->getCookieSameSite(),
        ));
    }

    private function _clearRefreshCookie() {
        $this->_setRefreshCookie('', time() - 3600);
    }

    private function validate($credentials) {
        if(!is_array($credentials)){
            throw new AuthException(
                sprintf(
                    "Invalid provided credentials. Expected an Array, but '%s' given.",
                    gettype($credentials)
                )
            );
        }

        if(array_key_exists("username", $credentials) && !empty($credentials["username"])) {
            $this->_username = $credentials["username"];
        } else {
            $this->throwError("Empty username");
        }

        if(array_key_exists("password", $credentials) && !empty($credentials["password"])) {
//            if(strlen($credentials["password"]) < self::PWD_COMPLEXITY_MIN_LENGTH){
//                $this->throwError(
//                    sprintf(
//                        "Password complexity error, the password is too short. The expected minimum length is %s characters long.",
//                        self::PWD_COMPLEXITY_MIN_LENGTH
//                    )
//                );
//            }
            $this->_password = $credentials["password"];
        } else {
            $this->throwError("Empty password");
        }
        return $this;
    }

    public function throwError(string $message){
        throw new \Wbengine\Auth\Exception\AuthException($message, 400);
    }
}