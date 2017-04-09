<?php

/**
 * $Id$ - CLASS
 * --------------------------------------------
 * Locales class manage all avaliable locales in
 * CMS.
 * Return locale by given id or class with existing
 * locale translations.
 *
 * @package RRsoft-CMS
 * @version $Rev$ $Date$ $Author$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Locale;

use Wbengine\Model\ModelAbstract;

class Model extends ModelAbstract
{


    /**
     * Create locale class by given locale class name
     *
     * @param $locale_id
     * @internal param string $class
     * @return object
     */
    public function getLocaledataRow($locale_id)
    {
        $statement = array($locale_id);

        $sql = sprintf("SELECT class AS locale FROM %s
			WHERE locale_id = ? LIMIT 1;"
            , S_TABLE_LOCALES
        );

        return $this->getDbAdapter()->query($sql, $statement)->current()->locale;
    }


}
