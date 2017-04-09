<?php
/**
 * Created by PhpStorm.
 * User: bajt
 * Date: 15.03.15
 * Time: 13:34
 */

namespace Wbengine\Application\Path;


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


    public function __construct($name = null, $path = null, $appBaseDir = false)
    {
        $this->_name = strtolower($name);
        $this->_path = $path;
        if($appBaseDir){
            $this->addPath($name, $path, $appBaseDir);
        }
    }

    public function __get($name)
    {
        if(empty($name)) {
            Throw New PathException(__CLASS__.': Empty path name');
        }

        if(is_array($this->_paths)) {
            foreach ($this->_paths as $i => $p) {
                if ($p->_name == $name) {
                    return $p->_path;
                }
            }
        }
        // @todo We should remove exception from this place...
//        Throw New PathException(sprintf('%s : Path type "%s" has not been defined yet.', __CLASS__, $name));
    }

    private function _craetePath($name, $path)
    {//var_dump($path);
        $path = (empty($path)) ? "/"  : $path;
        $this->_paths[] = New self($name, $path);
    }

    public function getName(){
        return $this->_name;
    }

    public function addSlash($path){
        return sprintf('%s/', $path);
    }

    public function addPath($name, $path, $appBaseDir = false)
    {//var_dump($name."->".$path);
        if($appBaseDir === true){
            $this->_appBaseDir = $path;
//            return;
        }
        $this->_craetePath($name, $path);
    }

    public function getPath($name, $includePath = null, $baseDir = false)
    {
        $this->_tmp = null;

        if($baseDir === true){
            $this->_tmp = $this->getAppBaseDir();
//            $this->_tmp = $this->_merge($this->_tmp, $this->getAppBaseDir());
        }
        if(!is_null($includePath)){
//            $this->_tmp .= $this->__get($includePath);
            $this->_tmp = $this->_merge($this->_tmp, $this->__get($includePath));
        }

//        $this->_tmp .= $this->__get($name);
        $this->_tmp = $this->_merge($this->_tmp, $this->__get($name));

        return $this->_tmp;
    }

    private function _merge($path1, $path2){
        return sprintf('%s/%s',rtrim($path1, '/'), ltrim($path2, '/'));
    }

    public function getAllPaths(){
        return $this->_paths;
    }

    public function getPathCount(){
        return (int)sizeof($this->_paths);
    }

    public function addBaseAppDir($appBaseDir)
    {
        $this->_appBaseDir = $appBaseDir;
        $this->addPath(self::TYPE_BASE, $appBaseDir);
    }

    public function getAppBaseDir(){
        return $this->getPath(self::TYPE_BASE);
    }

    public function getBasePath(){
        return $this->getPath(self::TYPE_BASE);
    }

    public function getCacheDir(){
        return $this->getPath(self::TYPE_CACHE, self::TYPE_BASE, true);
    }

    public function getRendererTempDir(){//var_dump($this->_paths);
        return $this->getPath(self::TYPE_RENDERER_TEMP, self::TYPE_CACHE, true);
    }

    public function getConfigDir(){
        return $this->getPath(self::TYPE_CONFIG, null, true);
    }

    public function getTemplatesDir(){
        return $this->addSlash($this->getPath(self::TYPE_APP_BRAND, self::TYPE_TEMPLATES, true));
    }

}