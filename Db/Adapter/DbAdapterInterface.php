<?php

/**
 * Description of AdapterInterface
 *
 * @author roza
 */

namespace Wbengine\Db\Adapter;

interface DbAdapterInterface {
    public function getConnection();
    public function query($sqlString);
    public function fetchRow($sqlString);
}
