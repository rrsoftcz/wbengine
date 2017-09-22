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
     * Return instance of session object
     * @return SessionAbstract
     */
    public function getSession()
    {
        return $this;
    }
}
