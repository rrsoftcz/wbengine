<?php
//
///**
// * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
// * ----------------------------------------------
// * Config class adapter
// *
// * @package RRsoft-CMS * @version $Rev: 30 $
// * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
// * @license GNU Public License
// *
// * Minimum Requirement: PHP 5.1.x
// */
//
//
//class Class_Config_Adapter {
//
//    /**
//     * Instance of adapter object
//     * @var Class_Config_Adapter
//     */
//    private $_adapter	    = NULL;
//
//    /**
//     * The adapter name
//     * @var string
//     */
//    private $_adapterName   = NULL;
//
//
//
//
//    private function _getAdapterClassName()
//    {
//	return "Class_Config_Adapter_"
//		. ucfirst((string)  $this->getAdapterName());
//    }
//
//
//    private function _getAdapterFilePath()
//    {
//        return "Class/Config/Adapter/"
//		. ucfirst((string)  $this->getAdapterName()) . ".php";
//    }
//
//
//
//    /**
//     * Return created object instance
//     * @return Class_Config_Interface
//     */
//    private function getAdapter()
//    {
//	if ($this->_adapter && is_object($this->_adapter))
//		return $this->_adapter;
//
//        $name = $this->_getAdapterClassName();
//
//        $adaFile = $this->_getAdapterFilePath();
//
//        if( is_readable($adaFile) )
//        {
//            require_once $adaFile;
//
//            $this->_adapter = new $name();
//
//	    return $this->_adapter;
//
//        } else {
//            throw new \Wbengine\Config\Adapter\Exception\ConfigException("Can't read config adapter file " . $adaFile . ".");
//        }
//    }
//
//
//    /**
//     * Set adapter name
//     * @param string $name
//     */
//    public function setAdapterName($name){
//	$this->_adapterName = $name;
//    }
//
//
//    /**
//     * Return adapter name
//     * @return string
//     */
//    public function getAdapterName(){
//	return $this->_adapterName;
//    }
//
//
//    /**
//     * Return assigned Config object
//     * @return Class_Config
//     */
//    public function getConfig(){
//	return $this->_config;
//    }
//
//
//    public function getDbCredentials(){
////	return $this->getAdapter()->getDbCredentials();
//    }
//
//
//    public function getDefaultCodePage(){
//	return (string)$this->getAdapter()->getDefaultCodePage();
//    }
//
//
//    public function getDefaultCssName(){
//	return (string)$this->getAdapter()->getDefaultCssName();
//    }
//
//
//    public function getAdminsIp(){
//	return $this->getAdapter()->getAdminsIp();
//    }
//
//
//    public function getDbAdapterName(){
//	return (string)$this->getAdapter()->getDbAdapterName();
//    }
//
////    public function getTest(){
//////        return $this->getAdapter()->getTest();
////    }
//
//
//}