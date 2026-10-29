<?php

namespace Wbengine\Api;

use Wbengine\Api\Exception\ApiException;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\Section\ApiSectionInterface;
use Wbengine\Application\Env\Http;
use Wbengine\User\Role;

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
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getSectionModel()->getSections($active)),
            Role::ADMIN
        );
    }

    public function updateSection($id, $data)
    {
        $this->isAuthenticated(
            fn($payload, $actor) => $this->Api()->toJson($this->getSectionModel()->updateSection($id, $data, $this->changeActor($actor))),
            Role::ADMIN
        );
    }

    public function deleteSection($id)
    {
        $this->isAuthenticated(
            fn($payload, $actor) => $this->Api()->toJson($this->getSectionModel()->deleteSection($id, $this->changeActor($actor))),
            Role::ADMIN
        );
    }

    public function getSectionById($id)
    {
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getSectionModel()->getSectionById($id)),
            Role::ADMIN
        );
    }

    public function addNewSection($sectionData)
    {
        $this->isAuthenticated(function ($payload, $actor) use ($sectionData) {
            if (!is_array($sectionData)) {
                throw new ApiException("No section data found.", 1);
            }
            $_lastId = $this->getSectionModel()->addSection($sectionData, $this->changeActor($actor));
            if (!$_lastId) {
                throw new ApiModelException("Something went wrong, seems data has added but any ID has returned.", 1);
            }
            $this->Api()->toJson($this->getSectionModel()->getSectionById($_lastId), Http::CREATED);
        }, Role::ADMIN);
    }

}
