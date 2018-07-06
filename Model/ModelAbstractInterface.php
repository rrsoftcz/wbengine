<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 06/07/2018
 * Time: 11:40
 */

namespace Wbengine\Model;


interface ModelAbstractInterface
{
    public function query($query);
    public function getConnectionx();
}