<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 01.04.15
 * Time: 18:17
 */

namespace Wbengine\Site\Menu;


//use Wbengine\Site\Menu;

//use Site\Menu\MenuitemInterface;

class Menuitem implements MenuitemInterface {

    private $_menuitem      = null;
    private $_menuitems = null;

    /**
     * @var \Wbengine\Site
     */
    private $_parent    = null;
    private $_model    = null;

    public function __construct($menu = null)
    {
        $this->_parent = $menu;
//        var_dump($this->getParent()->toArray());
        if($this->getParent() instanceof MenuitemInterface)
        {
            $this->_menuitem = $this->getParent()->toArray();
//            var_dump($this->loaded);

            if(!$this->loaded){
                $this->_loadMenuResource($this->getParent()->getMenuId());
            }else{

            }

        }else{
            //@todo Exception..
        }
//        var_dump($this->_menuitem);
    }

    public function __get($menuItemName)
    {
        if(is_array($this->_menuitem) && array_key_exists($menuItemName, $this->_menuitem)){
            return $this->_menuitem[$menuItemName];
        }
    }

    public function __set($key, $value){//var_dump($key);
        $this->_menuitem[$key] = $value;
//        var_dump($this->selected);
    }

    private function _loadMenuResource($menuId)
    {
        $resultSet = $this->getModel()->getMenu($menuId);

        if($resultSet->count()) {
            $this->_menuitem = $resultSet->current();
            $this->loaded = true;
            $this->isSelected($this->site_id);
        }else{
            //@todo Exception
        }
//        return $this->_menuitem;
    }


//    public function getChildMenuitems(){
////var_dump($this->getSiteId());
//    }

    /**
     * @return \Wbengine\Site
     */
    public function getSite(){
        return $this->getParent()->getSite();
    }

    public function getSiteId(){
        return (int)$this->site_id;
    }

    public function getParent(){
        return $this->_parent;
    }

    public function getModel()
    {
        if (NULL === $this->_model) {
            $this->setModel();
        }

        return $this->_model;
    }

    private function setModel()
    {
        $this->_model = New Model($this);
    }


    public function getMenuItems()
    {
//        var_dump($this->toArray());
//        if(empty($this->subitems)){
////            var_dump($this->subitems);
//        }
//        var_dump($this->menu_id);
//        var_dump(debug_backtrace());die();
//        if($this->_menuitem instanceof \ArrayObject){
//            if($this->_menuitem->count()){
//                return $this->_menuitem;
//            }
//        }else {
            $menuitems = $this->getModel()->getMenuItems($this);
//var_dump($menuitems);
            if ($menuitems instanceof \Zend\Db\Adapter\Driver\Pdo\Result) {

                if ($menuitems->count()) {
                    foreach ($menuitems as $menu) {
                        $this->_menuitem = $menu;

//                        $this->selected = ($this->isSelected($this->_menuitem[site_id]));
                        $this->isSelected($this->site_id);
                        $this->loaded = true;
                        $this->_menuitems[$this->menu_id] = New Menuitem($this);
                    }
                } else {
                    //@Todo Exception...
                }
            } else {
                //@Todo Exception...
            }
//        }
//        var_dump($this->_menuitems);
        return $this->_menuitems;
    }

    public function resolve($menu)
    {
        if(is_array($menu))
        {
            $_m = $menu;
//$_m->append($menu);
            if(sizeof($menu) >= 4)
            {//var_dump($_m[menu_id]);
                if(
                    $_m[site_id] &&
                    $_m[menu_id] &&
                    $_m[type] &&
                    $_m[visible]
                ){
                    return true;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
        return false;
    }

    public function count(){
        return sizeof($this->_menuitems);
    }

    public function isSelected($siteId)
    {
//        if(!empty($siteId)){
            $this->selected = ((int)$siteId === $this->getSite()->getSiteId());
//        }else{
//            $this->selested = false;
//        }
    }

    public function getMenuItemxxxxxxx(){
        return New self($this->_menuitem);
    }

    public function getMenuId(){
        return (int)$this->menu_id;
    }

    public function toArray(){
        return $this->_menuitem;
    }

}