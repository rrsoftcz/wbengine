<?php

/**
 * $Id: Abstract.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Abstract Class for the site models.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Model;

use Wbengine;
use Wbengine\Config;
use Wbengine\Db;


class ModelAbstract implements ModelAbstractInterface
{


    /**
     * @var Db
     */
    private $_db = NULL;


    private function _setDb(){
        $this->_db = Db::setCredentials(Config::getDbCredentials());
    }


    public function dumpAll(){
        foreach ($this->_db->getAllQueries() as $query){
            print_r("<pre>".$query."</pre>");
        }
    }

    private function _getDb(){
        if (null === $this->_db) {
            $this->_setDb();
        }
        return $this->_db;
    }


    /**
     * Return database connection.
     * @return Db
     */
    public function getConnectionx(){
        return $this->_getDb()->getConnection();
    }

    public function getSiteId($model){
        return $model->site->getSiteid();
    }

    public function query($query){
        return Db::query($query);
    }



}
