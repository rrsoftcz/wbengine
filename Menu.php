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

    /**
     * Menu constructor.
     * @param $menu
     */
    public function __construct($menu){
        $this->_menu = $menu;
    }

    /**
     * Class setter ...
     * @param $name
     * @param $value
     */
    public function __set($name, $value){
        $this->_menu[$name] = $value;
    }

    /**
     * Class getter...
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if(is_array($this->_menu)){
            if(array_key_exists($name, $this->_menu)){
                return $this->_menu[$name];
            }
        }
        return null;
    }


    /**
     * Return menu state
     * @return bool
     */
    public function selected(){
        if($this->_site instanceof Site) {
            if((int) $this->site_id === $this->getSite()->getSiteId()){
                $this->selected = true;
                return true;
            }
        }
        return false;
    }


    /**
     * Set instance of class Site
     * @param $site
     * @throws MenuException
     */
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


    /**
     * Reurn instance of object Site
     * @return Site
     */
    public function getSite(){
        return $this->_site;
    }


    /**
     * Return Menu Site ID
     * @return string|null
     */
    public function getMenuSiteId(){
        return $this->site_id;
    }


    /**
     * Return Menu ID
     * @return integer|null
     */
    public function getMenuId(){
        return $this->menu_id;
    }


    /**
     * Return Menu name
     * @return string|null
     */
    public function getName(){
        return $this->name;
    }


    /**
     * Return menu description
     * @return string|null
     */
    public function getDescription(){
        return $this->description;
    }


    /**
     * Return menu visible state
     * @return integer|null
     */
    public function isVisible(){
        return $this->visible;
    }


    /**
     * Return menu order
     * @return int|null
     */
    public function GetMenuOrder(){
        return $this->order;
    }


    /**
     * Return menu url link
     * @return string|null
     */
    public function GetMenuLink(){
        return $this->link;
    }


    /**
     * Return Menu parent ID as integer
     * @return int|null
     */
    public function GetMenuParent(){
        return $this->parent;
    }


}