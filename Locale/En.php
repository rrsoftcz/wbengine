<?php
/**
 * Description of En
 *
 * @author bajt
 */
require 'Class/Site/Locale/Abstract.php';

class Class_Locale_En extends Class_Locale_Abstract {

    protected $_locales = array(
	'SAVE' => 'Save',
	'AUTH_LOGIN' => 'Login',
	'AUTH_LOGOUT' => 'Logout',
	'AUTH_NEW_ACC' => 'Registration',
	'AUTH_USERNAME' => 'Username:',
	'AUTH_PASSWORD' => 'Password',
	'AUTH_LOGIN_FORM_TITLE' => 'Sign In',
	'AUTH_LOGIN_FORM_LABEL' => 'Enter your username and password.',
	'AUTH_LOGIN_WRONG_PASSWORD' => 'Wrong username or password.',
	'AUTH_LOGIN_EMPTY_PASSWORD' => 'Password can\'t be empty.',
	'AUTH_LOGIN_EMPTY_USERNAME' => 'The username cam\'t be empty.',
	'AUTH_LOGIN_ERROR_INACTIVE' => 'Account is inactive. To activae account please click to link in registration email.<br /><a href="/login/resend/">Znovu zaslat aktivační email</a>"',
	'AUTH_LOGIN_SUCCESS' => 'Login success, you will be redirested to homepage in second<a href="%s" >%s</a>',
	'AUTH_LOGOUT_FORM_TITLE' => 'Logout from system',
	'AUTH_LOGOUT_SUCCESS' => 'You are success logout from system, in secon you will be redirested to <a href="%s" >%s</a>',

	'STORE_BASKET_ADD_SUCCESS' => 'Zboží bylo přidáno do košíku. Prohlédnout <a href="%s" >obsah košíku</a>, nebo <a href="%s" >pokračovat v nákupu</a>.',
	'STORE_BASKET_TITLE' => 'Nákupní košík',
	'STORE_BASKET_ERROR_COUNT' => 'Byl zadán chybný počet zboží. Zboží NEBYLO přidáno do košíku',
    );

}
