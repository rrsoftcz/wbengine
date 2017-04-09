<?php

/**
 * Description of AdapterInterface
 *
 * @author roza
 */

namespace Wbengine\Db\Adapter;

interface DbAdapterInterface {


    /**
     * @return Driver\DriverInterface
     */
    public function getAdapter();
}
