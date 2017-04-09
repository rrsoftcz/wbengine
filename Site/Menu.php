<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 01.04.15
 * Time: 18:17
 */

namespace Wbengine\Site;


//use Wbengine\Site\Menu;

use Wbengine\Site\Menu\MenuitemInterface;
use Wbengine\Site;
use Zend\Db\Adapter\Driver\Pdo\Result;
use Zend\Db\Adapter\StatementContainerInterface;
use Zend\Db\Sql\Ddl\Column\Integer;

class Menu implements MenuitemInterface  {

    CONST ROOT_MENU_ID      = 0;

    private $_menuitem      = null;
    private $_menuitems = null;
    private $site = null;
    private $_siteId   = null;
    private $_menuType  = null;

    /**
     * DB model class of Menu
     * @var \Wbengine\Site\Menu\Model
     */
    private $_model     = null;
    private $_parent    = null;


    public function __construct(Site $site)
    {
        if($site) {
//            $this->_menuType = $site->getSiteParentId();
//        }else{
        $this->_parent = $site;
//            $this->_loadMenuResource($menuId);
        }
//        var_dump($menuId);
    }

    public function __get($menuItemName)
    {
        if(is_array($this->_menuitem)) {
            if (array_key_exists($menuItemName, $this->_menuitem)) {
                return $this->_menuitem[$menuItemName];
            } else {
                //@todo Exception...
            }
        }else{
            //@todo Exception...
            return null;
        }
    }

    public function __set($key, $value){//var_dump($key);
        $this->_menuitem[$key] = $value;
//        var_dump($this->selected);
    }

//    private function _loadMenuResource($menuId)
//    {
//        $resultSet = $this->getModel()->getMenu($menuId);
//
//        if($resultSet instanceof Result) {
//            $this->_menuitem = $resultSet->current();
//        }else{
//            //@todo Exception
//        }
//        return $this->_menuitem;
//    }

//    private function _getMenuItemsxx($parent){
////var_dump($this->getSiteId());
//        $menuitems = $this->getModel()->getMenuItems($this);
////        var_dump($menuitems);
////        if($menuitems instanceof ResultInterface) {
////die(ddd);
//            if ($menuitems->count()) {
//                foreach ($menuitems as $menu) {
////                    var_dump($menu);
//                    $this->_menuitem = $menu;
//
////                    $this->menu = New Site\Menu\Menuitem($this);
//                    $this->_menuitem[selected] = ($this->isSelected($this->_menuitem[site_id]));
////                    var_dump($this->_siteId);
////                    var_dump($_menu->site_id);
////                    var_dump($this->isSelected((int)$_menu->site_id));
//
////                    $_menu->menuitems = New self($_menu->menu_id);
////                var_dump((int)$this->menu->subitems);
////                    if ((int)$_menu->subitems > 0) {
////                    var_dump($_menu->menu_id);
////                        $_menu->menuitems= $this->_getMenuItems((int)$this->getMenuId());
////                        $_menu->menuitems->getItems(222);
////                    }
//                    $this->_menuitems[$menu[menu_id]] = New Site\Menu\Menuitem($this);
//
////                    $this->menu->selected = ($this->site->getSiteId() === $this->menu->getSiteId());
////                    $this->menuitems[$menu[menu_id]] = $this->menu;
//                }
//            } else {
//                //@Todo Exception...
//            }
////        }else{
//            //@Todo Exception...
////        }
////        var_dump($this->_menuitems);
//        return $this->_menuitems;
//    }

    public function getMenuitem($menuId = null)
    {//var_dump($this->count());
        $this->menu_id = $menuId;
        return New Site\Menu\Menuitem($this);
//        if($this->count()>0) {//var_dump($this->count());
//            if (is_array($this->_menuitems) && array_key_exists($menuId, $this->_menuitems)) {
//                return $this->_menuitems[$menuId];
//            }
//        }

//        if($this->getMenuId() === (int)$menuId){
//            return New Site\Menu\Menuitem($this);
//        }else {
//            $this->_loadMenuResource($menuId);
//        }
//        var_dump($this->_menuitem);
//        var_dump($this->getMenuId());
//        return New Site\Menu\Menuitem($this);
//        var_dump($this->_menuitem);
//        return New Site\Menu\Menuitem($this);
    }

    public function current(){
        return $this->getMenuitem($this->getSite()->getSiteMenuId());
    }

    public function getSiteId(){
        return (int)$this->_menuitem->site_id;
    }

    public function getSite(){
        return $this->_parent;
    }

    public function getParent(){
        return $this->parent;
    }

    public function getSiteRootItems($id = null)
    {
        if($id === null){
            $id = self::ROOT_MENU_ID;
        }
        return $this->getMenuitem($id)->getMenuItems();
    }

//    public function getItems($siteId)
//    {
////        $this->_siteId = (int)$siteId;
//
//        if($this->_menuitems === null){
//            return $this->_getMenuItems($this->getMenuId());
//        }
//
//        return $this->_menuitems;
//    }

//    public function getModel()
//    {
//        if (NULL === $this->_model) {
//            $this->setModel();
//        }
//
//        return $this->_model;
//    }
//
//    private function setModel()
//    {
//        $this->_model = New Menu\Model($this);
//    }

    public function getMenuSubitems($id = null)
    {
        if(empty($id)) {
            return;
        }

        return $this->getMenuitem($id)->getMenuItems();
    }

    public function isSelected($siteId)
    {
        if(!empty($siteId) && $this->getSite()->getSiteId()){
            return ((int)$siteId === $this->getSite()->getSiteId());
        }
    }

    public function count(){
        return sizeof($this->_menuitems);
    }

    public function getMenuId(){
        return $this->menu_id;
    }

    public function toArray(){
        return $this->_menuitem;
    }

}