<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 21:19
 */

namespace Wbengine\Api;


use Wbengine\Api\Exception\ApiException;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\Section\ApiSectionInterface;

class Section extends WbengineRestapiAbstract implements ApiSectionInterface
{

//    public function getInstanceOfApiRoutes(){
//        $class = $this->createNameSpace($this->getLastPartFromNamespace(__CLASS__));
//        return new $class($this);
//    }
//    public function getInstanceOfApiRoutesddd(){
//     $this->Api()->toJson($this->getSectionModel()->getSections('ddd'));
    
//     }


    public function getSections($active = false)
    {
        $this->Api()->toJson($this->getSectionModel()->getSections($active));
    }

    public function updateSection($id, $data)
    {
        $this->Api()->toJson($this->getSectionModel()->updateSection($id, $data));
    }

    public function deleteSection($id)
    {
        $this->Api()->toJson($this->getSectionModel()->deleteSection($id));
    }

    public function getSectionById($id)
    {
        $this->Api()->toJson($this->getSectionModel()->getSectionById($id));
    }

    public function addNewSection($sectionData)
    {
        if (is_array($sectionData)) {
            $_lastId = $this->getSectionModel()->addSection($sectionData);
            if ($_lastId) {
                return $this->Api()->toJson($this->getSectionById($_lastId));
            } else {
                throw new ApiModelException("Something went wrong, seems data has added but any ID has returned.", 1);
            }
        } else {
            throw new ApiException("No section data found.", 1);
        }
    }

}