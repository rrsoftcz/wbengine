<?php

/**
 * $Id: Story.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Class Central Story module class.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */
//require_once 'Class/Site/Box/Abstract.php';

namespace Wbengine\Box\Central;

use Wbengine\Box\BoxTemplate;

class Admin extends BoxTemplate
{


    /**
     * Return story content from table article
     * @return string
     */
    public function getAdminBox()
    {
        return $this->getRenderer()->render('story_box');
    }


}