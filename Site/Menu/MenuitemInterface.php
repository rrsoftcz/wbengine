<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04.04.15
 * Time: 16:10
 */

namespace Wbengine\Site\Menu;


interface MenuitemInterface {

    public function getParent();
    public function getSite();

}