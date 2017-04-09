<?php

/**
 * Description of Adapter
 *
 * @author roza
 */

namespace Wbengine\Db\Adapter;

use Zend\Db\TableGateway\Feature\GlobalAdapterFeature;

class Zend implements \Wbengine\Db\Adapter\DbAdapterInterface {


    /**
     * Db adapter
     * @var \Zend\Db\Adapter\AdapterInterface
     */
    protected $adapter = null;

    /**
     * Config adapter
     * @var \Wbengine\Config\Adapter\AdapterAbstract
     */
    protected $config = null;



    /**
     * Configuration injection
     * @param \Wbengine\Config\Adapter\AdapterInterface $config
     */
    public function __construct($config)
    {
	if (is_array($config)) {
	    $this->config = $config;
	} elseif ($config instanceof \Wbengine\Config\Adapter\AdapterInterface) {
	    $this->config = $config->toArray();
	} else {

	    throw new Exception\DbAdapterException(__METHOD__
	    . ': expects config object implements '
	    . '\Wbengine\Config\Adapter\ConfigAdapterInterface or Array.');
	}
    }



    /**
     * Return Zend adapter
     * @return \Zend\Db\Adapter\AdapterInterface
     * @throws Exception\DbAdapterException
     */
    public function getAdapter()
    {
	if ($this->adapter === null) {
	    $this->createAdapter($this->config);
	}

	return $this->adapter;
    }



    /**
     * Create Zend instance adapter
     * by given config params.
     * 
     * @param array| Wbengine\Config\Adapter\ConfigAdapterInterface $parameters
     * @throws \Wbengine\Db\Adapter\Exception
     * @throws Exception\DbAdapterException
     */
    protected function createAdapter($parameters)
    {
	try {

	    $this->adapter = New \Zend\Db\Adapter\Adapter($parameters);

	    if ($this->adapter !== null && $this->adapter instanceof \Zend\Db\Adapter\AdapterInterface) {
		GlobalAdapterFeature::setStaticAdapter($this->adapter);
	    } else {
		throw new Exception\DbAdapterException(__METHOD__ .
		': Excepts argument must be instance of Zend\Db\Adapter\AdapterInterface.');
	    }
	}
	catch (Exception\DbAdapterException $e) {
	    throw new \Wbengine\Db\Adapter\Exception(__METHOD__ . $e->getMesage());
	}
    }



}
