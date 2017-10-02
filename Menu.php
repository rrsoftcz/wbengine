<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 02.10.17
 * Time: 18:46
 */

namespace Wbengine;


use Wbengine\Menu\MenuException;

class Menu
{
    /**
     * @var array
     */
    private $_menu;

    /**
     * @var Site
     */
    private $_site;

    public function __construct($menu)
    {
        $this->_menu = $menu;
    }

    public function __set($name, $value)
    {
        $this->_menu[$name] = $value;
    }

    public function __get($name)
    {
        if(is_array($this->_menu)){
            if(array_key_exists($name, $this->_menu)){
                return $this->_menu[$name];
            }
        }
        return null;
    }

    public function selected(){
        if($this->_site instanceof Site) {
            if((int) $this->site_id === $this->getSite()->getSiteId()){
                $this->selected = true;
                return true;
            }
        }
        return false;
    }


    public function setSite($site){
        if($site instanceof Site) {
            $this->_site = $site;
        }else{
            Throw New MenuException(sprintf("%s -> %s: Expected instance of object Site, but %s given.!",
                __CLASS__,
                __FUNCTION__,
                gettype($site)),
                MenuException::ERROR_OBJECT_INSTANCE_TYPE);

        }
    }

    public function getSite(){
        return $this->_site;
    }

    public function getMenuSiteId(){
        return $this->site_id;
    }

    public function getMenuId(){
        return $this->menu_id;
    }

    public function getName(){
        return $this->name;
    }

    public function getDescription(){
        return $this->description;
    }

    public function isVisible(){
        return $this->visible;
    }

    public function GetMenuOrder(){
        return $this->order;
    }

    public function GetMenuLink(){
        return $this->link;
    }

    public function GetMenuParent(){
        return $this->parent;
    }


}