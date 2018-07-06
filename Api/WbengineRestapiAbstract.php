<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:59
 */
namespace Wbengine\Api;

use Wbengine\Api;
use Wbengine\Api\Model\ApiSectionModel;
use Wbengine\Box\WbengineBoxAbstract;

class WbengineRestapiAbstract extends WbengineBoxAbstract
{
    private $_params;
    private $_api;

    public $api;


    public function __construct($box, $parent) {
        $this->_parent = $parent;
        $this->api = new Api();
    }


    public function Api() {
        if($this->_api){
            return $this->_api;
        }else{
            return $this->_api = new Api();
        }
    }


    public function getSectionModel() {
        return new ApiSectionModel();
    }


}