<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 06/07/2018
 * Time: 12:37
 */

namespace Wbengine\Box;

use Wbengine\Box\Story\StoryModel;

class WbengineStaticBox
{
    private $_boxAbstract;


    const BOX_STORY     = 'story';
    const BOX_LOGIN     = 'login';

    public function __construct(WbengineBoxAbstract $box)
    {
        $this->_boxAbstract = $box;
    }

    public function getParent(){
        return $this->_boxAbstract;
    }

    public function getRenderer(){
        return $this->getParent()->getRenderer();
    }

    public function getStoryModel($story){
        return new StoryModel($story);
    }

    public function getStaticBoxTemplatePath($boxName)
    {
        switch (strtolower($boxName)){
            case self::BOX_STORY:
                return 'Story/View/' . ucfirst($boxName);
                break;
            case self::BOX_LOGIN:
                return 'Login/View/' . ucfirst($boxName);
                break;
        }
    }

}