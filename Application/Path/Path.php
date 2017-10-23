<?php
/**
 * Created by PhpStorm.
 * User: bajt
 * Date: 15.03.15
 * Time: 13:34
 */

namespace Wbengine\Application\Path;


use Wbengine\Application\Env\Stac\Utils;

class Path {

    CONST TYPE_BASE             = 'app_base_dir';
    CONST TYPE_CONFIG           = 'app_config_dir';
    CONST TYPE_TEMPLATES        = 'app_templates_dir';
    CONST TYPE_CACHE            = 'app_cache_dir';
    CONST TYPE_APP_BRAND        = 'app_type_dir';
    CONST TYPE_RENDERER_TEMP    = 'app_renderer_temp_dir';
    CONST TYPE_PUBLIC_ROOT      = 'app_public_root_dir';
    CONST TYPE_IMAGES           = 'app_images_dir';
    CONST TYPE_MEDIA            = 'app_media_dir';
    CONST TYPE_CSS              = 'app_css_dir';
    CONST TYPE_JS               = 'app_js_dir';


    private $_name              = null;
    private $_path              = null;
    private $_appBaseDir        = null;
    private $_tmp               = null;
    private $_paths             = null;


    /**
     * Create object Path
     * @param $name
     * @param $path
     */
    private function _createPath($name, $path)
    {
        $path = (empty($path)) ? "/" : $path;
        $this->_paths[] = New self($name, $path);
    }


    /**
     * Merge two paths togather...
     * @param string $path1
     * @param string $path2
     * @return string
     */
    private function _merge($path1, $path2){
        return sprintf('%s/%s',rtrim($path1, '/'), ltrim($path2, '/'));
    }


    /**
     * Store ptah arguments to local variables...
     * Path constructor.
     * @param null $name
     * @param null $path
     * @param bool $appBaseDir
     */
    public function __construct($name = null, $path = null, $appBaseDir = false)
    {
        $this->_name = strtolower($name);
        $this->_path = $path;
        if($appBaseDir){
            $this->addPath($name, $path, $appBaseDir);
        }
        return $this;
    }

    /**
     * Object getter ...
     * @param $name
     * @return mixed
     * @throws PathException
     */
    public function __get($name)
    {
        if(is_array($this->_paths)) {
            foreach ($this->_paths as $i => $p) {
                if ($p->_name == $name) {
                    return $p->_path;
                }
            }
        }
        return null;
    }


    /**
     * Return raw patn value as string ...
     * @return null
     */
    public function toString(){
        return $this->_path;
    }

    /**
     * Get path name
     * @return string
     */
    public function getName(){
        return $this->_name;
    }


    /**
     * Add slash to path ...
     * @param $path
     * @return string
     */
    public function addSlash($path){
        return sprintf('%s/', $path);
    }


    /**
     * Add path to collection od all paths...
     * @param $name
     * @param $path
     * @param bool $appBaseDir
     */
    public function addPath($name, $path, $appBaseDir = false){
        if($appBaseDir === true){
            $this->_appBaseDir = $path;
        }
        $this->_createPath($name, $path);
    }


    /**
     * Return requested path...
     * @param $name
     * @param null $includePath
     * @param bool $baseDir
     * @return null|string
     */
    public function getPath($name, $includePath = null, $baseDir = false)
    {
        $this->_tmp = null;

        if($baseDir === true){
            $this->_tmp = $this->getAppBaseDir();
        }

        if(!is_null($includePath)){
            $this->_tmp = $this->_merge($this->_tmp, $this->__get($includePath));
        }
        $this->_tmp = $this->_merge($this->_tmp, $this->__get($name));

        return $this->_tmp;
    }


    /**
     * Return all paths ...
     * @return array
     */
    public function getAllPaths(){
        return $this->_paths;
    }


    /**
     * Return paths count ...
     * @return int
     */
    public function getPathCount(){
        return (int)sizeof($this->_paths);
    }


    /**
     * Initialize based app directory pah ...
     * @param $appBaseDir
     */
    public function addBaseAppDir($appBaseDir)
    {
        $this->_appBaseDir = $appBaseDir;
        $this->addPath(self::TYPE_BASE, $appBaseDir);
    }


    /**
     * Retuen app base dir.
     * @return null|string
     */
    public function getAppBaseDir(){
        return $this->getPath(self::TYPE_BASE);
    }


    /**
     * Return based path ...
     * @return null|string
     */
    public function getBasePath(){
        return $this->getPath(self::TYPE_BASE);
    }


    /**
     * Return app cache directory
     * @return null|string
     */
    public function getCacheDir(){
        return $this->getPath(self::TYPE_CACHE, true);
    }


    /**
     * Return app renderer base path ...
     * @return null|string
     */
    public function getRendererCompiledDir(){//Utils::dump($this->_paths);
        return $this->getPath(self::TYPE_RENDERER_TEMP, self::TYPE_CACHE, true);
    }


    /**
     * Return app config path ...
     * @return null|string
     */
    public function getConfigDir(){
        return $this->getPath(self::TYPE_CONFIG, null, true);
    }


    /**
     * Return templates directory ...
     * @return null|string
     */
    public function getTemplatesDir(){
        return $this->getPath(self::TYPE_TEMPLATES, true);
    }

}