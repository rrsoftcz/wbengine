<?php

/**
 * Description of AdapterInterface
 *
 * @author roza
 */

namespace Wbengine\Db\Adapter;

interface DbAdapterInterface {
    public function getConnection();
    public function getDbVersion();
    public function getAllAssoc($sqlString);
}
