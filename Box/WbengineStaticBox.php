<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 06/07/2018
 * Time: 12:37
 */

namespace Wbengine\Box;

use Wbengine\Box\Article\ArticleModel;

class WbengineStaticBox
{
    private $_boxAbstract;


    const BOX_ARTICLE       = 'article';
    const BOX_LOGIN         = 'login';

    public function __construct(WbengineBoxAbstract $box){
        $this->_boxAbstract = $box;
    }

    public function getSession(){
        return $this->getParent()->getSession();
    }

    public function getSectionPath($name, $subfolder = null){
        return $this->getParent()->getSectionPath($name, $subfolder);
    }

    public function getParent(){
        return $this->_boxAbstract;
    }

    public function getRenderer(){
        return $this->getParent()->getRenderer();
    }

    public function getArticleModel($story){
        return new ArticleModel($story);
    }

    public function getStaticBoxTemplatePath($boxName)
    {
        switch (strtolower($boxName)){
            case self::BOX_ARTICLE:
                return 'Article/View/' . ucfirst($boxName);
                break;
            case self::BOX_LOGIN:
                return 'Login/View/' . ucfirst($boxName);
                break;
            default: return __DIR__;
        }
    }

}