<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:56
 */

namespace Wbengine;
use Wbengine\Api\Section;
use Wbengine\Application\Env\Http;

class Api
{
    private $_instances = array();


    const API_SECTION   = 'section';


    private function _createApiObject($name){
        return new $name($this);
    }

    private function _getApiNameSpace($objectApi){
        return "Wbengine\\Api\\".ucfirst($objectApi);
    }

    public function Section(){
        return $this->_createApiObject($this->_getApiNameSpace(self::API_SECTION));
    }

    public function toJson($value)
    {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        die(json_encode($value));
    }

    public function toString($value)
    {
        Http::PrintHeader(Http::HEADER_TYPE_PLAIN_TEXT);
        die((string)$value);
    }

    public function getApiError($msg)
    {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        die(json_encode(array('error' => $msg)));
    }



}