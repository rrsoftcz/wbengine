<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 21:19
 */

namespace Wbengine\Api;


use Wbengine\Api;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Api\Model\Exception\ApiModelException;

class Section extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    private $_api;
    public function __construct(Api $api)
    {
        $this->_api = $api;
    }

    public function getApi(){
        return $this->_api;
    }

    public function getInstanceOfApiRoutes(){
        return new Api\Section\Routes($this);
    }

    public function getSections($active = false)
    {
        $this->getApi()->toJson($this->getSectionModel()->getSections($active));
    }

    public function updateSection($id, $data)
    {
        $this->getApi()->toJson($this->getSectionModel()->updateSection($id, $data));
    }

    public function deleteSection($id)
    {
        $this->getApi()->toJson($this->getSectionModel()->deleteSection($id));
    }

    public function getSectionById($id)
    {
        $this->getApi()->toJson($this->getSectionModel()->getSectionById($id));
    }

    public function addNewSection($sectionData)
    {
        if (is_array($sectionData)) {
            $_lastId = $this->getSectionModel()->addSection($sectionData);
            if ($_lastId) {
                return $this->getApi()->toJson($this->getSectionById($_lastId));
            } else {
                throw new ApiModelException("Something went wrong, seems data has added but any ID has returned.", 1);
            }
        } else {
            throw new ApiException("No session data.", 1);
        }
    }

}