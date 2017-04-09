<?php

/**
 * $Id: Session.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Session initial class
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\Session\SessionAbstract;


class Session extends SessionAbstract
{


    /**
     * Stored class session instance.
     * @var Class_Session_Abstract
     */
    private $_session = null;


    /**
     * Static class - cannot be instantiated.
     */
    final public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }


    /**
     * Return instance of session object
     * @return Class_Session
     */
    public function getSession()
    {
        return $this;
    }


}
