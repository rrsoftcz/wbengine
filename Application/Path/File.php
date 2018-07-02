<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 25.03.15
 * Time: 12:26
 */

namespace Wbengine\Application\Path;


use Wbengine\Application\Path\File\Exception\FileException;
use Wbengine\Application\Env\Stac\Utils;

class File {

    CONST STATUS_SUCCESS        = 1;
    CONST STATUS_FAILED         = 0;
    CONST FILE_TYPE_JPEG        = 'image/jpg';
    CONST FILE_TYPE_PNG         = 'image/png';
    CONST FILE_TYPE_GIF         = 'image/gif';
    CONST FILE_TYPE_ICO         = 'image/ico';
    CONST FILE_TYPE_PDF         = 'document/pdf';
    CONST FILE_TYPE_HTML        = 'text/html';
    CONST FILE_TYPE_CSS         = 'text/css';
    CONST FILE_TYPE_JS          = 'text/javascript';
    CONST FILE_TYPE_PHP         = 'text/php';
    CONST FILE_TYPE_SVG         = 'image/svg+xml';
    CONST FILE_TYPE_JSON        = 'text/json';
    CONST FILE_TYPE_SMARTY_TPL  = 'text/smarty+html';
    CONST FILE_TYPE_YAML        = 'text/yaml';
    CONST FILE_TYPE_INI         = 'text/ini';
    CONST FILE_TYPE_LINUX_CONF  = 'text/linux+conf';
    CONST FILE_TYPE_MINIMIZED   = 'text/minimized';
    CONST FILE_TYPE_ETAG        = 'text/etag';
    CONST FILE_TYPE_UNKNOWN     = 'unknown';

    CONST MINIMIZED             = true;

    private $_file_types    = array
    (
        'jpg'   => self::FILE_TYPE_JPEG,
        'jpeg'  => self::FILE_TYPE_JPEG,
        'png'   => self::FILE_TYPE_PNG ,
        'gif'   => self::FILE_TYPE_GIF ,
        'ico'   => self::FILE_TYPE_ICO ,
        'pdf'   => self::FILE_TYPE_PDF ,
        'htm'   => self::FILE_TYPE_HTML ,
        'html'  => self::FILE_TYPE_HTML ,
        'css'   => self::FILE_TYPE_CSS ,
        'js'    => self::FILE_TYPE_JS ,
        'php'   => self::FILE_TYPE_PHP ,
        'svg'   => self::FILE_TYPE_SVG ,
        'json'  => self::FILE_TYPE_JSON ,
        'tpl'   => self::FILE_TYPE_SMARTY_TPL ,
        'yaml'  => self::FILE_TYPE_YAML ,
        'ini'   => self::FILE_TYPE_INI ,
        'conf'  => self::FILE_TYPE_LINUX_CONF ,
        'etag'  => self::FILE_TYPE_ETAG ,

    );

    private $_file              = null;

    private $_fullPath          = null;

    private $_minimized         = false;

    private $_minimizedName     = null;

    private $_writed            = null;

    private $_file_exist        = null;

    private $_status            = false;

    private $_asNew             = false;


    public function __construct($file, $asNew = false)
    {
        $this->_file = pathinfo($file);

        if(!empty($file) && is_array($this->_file)) {
            $this->_setFullPathName();
        }

        if($this->exist())
        {
            $this->_file_exist = TRUE;
            $this->_file['info'] = stat($this->getFile());
        }else{
            $this->_file_exist = FALSE;
            if($asNew === FALSE) {
                Throw New FileException(
                    sprintf('%s->%s: File "%s" not exist.'
                        , __CLASS__
                        , __FUNCTION__
                        , $file
                    )
                );
            }else{
                $this->_asNew = true;
            }
        }
    }

    public function _setFlagIsMinimized(){
        $this->_minimized = true;
    }

    private function _setMinimizedFileName($name){
        $this->_minimizedName = $name;
    }

    public function isMinimizedType($handler = '-min'){
        return strpos($handler, $this->getFileBaseName());
    }

    private function _getFilesize($bytes, $decimals = 2)
    {
        $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    public function getFiletType($extension = null)
    {
        if(empty($extension)){
            $extension = $this->getFileExtension();
        }

        if(array_key_exists($extension, $this->_file_types)){
            return $this->_file_types[$extension];
        }else{
            return self::FILE_TYPE_UNKNOWN;
        }
    }

    public function getMinimizedName($handler = '.min' )
    {
        return $this->getFileBaseName().$handler. '.' .$this->getFileExtension();
    }

    public function isMinimized(){
        return $this->_minimized;
    }

    public function minimize($string){
        return preg_replace("'\\s+'ms", " ", $string);
    }


    /**
     * Rename a file...
     *
     * @param $newFileName
     * @return File
     * @throws FileException
     */
    public function rename($newFileName)
    {
        $this->_status = rename($this->getFileName(), $newFileName);

        if($this->_status === true){
            $_new = New File($newFileName);
            $_new->_status = self::STATUS_SUCCESS;

            return $_new;
        }else{
            Throw New FileException(
                sprintf('%s->%s: Cannot rename file %s .'
                    , __CLASS__
                    , __FUNCTION__
                    , $this->getFile()
                )
            );
        }

    }


    /**
     * Copy a file..
     *
     * @param $newName
     * @return $this|File
     * @throws FileException
     */
    public function copy($newName){

        $_old = $this->getFile();
        $_new = $this->getDirectory().'/'.$newName;

        try {
            if(copy($_old, $_new)){
                return New File($_new);
            }else{
                $this->_status = self::STATUS_FAILED;
                return $this;
            }
        }catch (Exception $e){
            Throw New FileException(
                sprintf('%s->%s: The copy operation failed with error: %s .'
                    , __CLASS__
                    , __FUNCTION__
                    , $e->getMessage()
                )
            );
        }
    }


    /**
     * Save current file as minimized...
     *
     * @return File
     * @throws FileException
     */
    public function saveAsMinimized()
    {
        $newFile = New File($this->getDirectory(). '/' .$this->getMinimizedName(), true);

        if ($this->exist()) {
            if($newFile->isWritable()) {
                $newFile->writeToFile($this->getContent(), self::MINIMIZED);
            }else{
                Throw New FileException(
                    sprintf('%s->%s: Cannot write to file "%s".'
                        , __CLASS__
                        , __FUNCTION__
                        , $newFile->getFile()
                    )
                );
            }
        }else{
            Throw New FileException(
                sprintf('%s->%s: Source file %s not exist'
                    , __CLASS__
                    , __FUNCTION__
                    , $this->getFile()
                )
            );

        }
        return $newFile;
    }


    /**
     * Create a new instance of object file...
     *
     * @param string $fileType
     * @param string $directory
     * @param string $fileName
     * @return File
     * @throws FileException
     */
    public function newFileName($fileType = null, $directory = null, $fileName = null)
    {
        if(is_null($fileType)){
            $fileType = $this->getFileExtension();
        }
        if(is_null($directory)){
            $directory = $this->getDirectory();
        }
        if(is_null($fileName)){
            $fileName = $this->getFileBaseName();
        }

        $_file_name = sprintf('%s/%s.%s', $directory, $fileName, $this->getExtensionByFileType($fileType));
        return new self($_file_name, true);
    }


    /**
     * Create etag file in compiled directory.
     *
     * @param null $file
     * @return File
     * @throws FileException
     */
    public function saveEtag($file = null)
    {
        if($file instanceof File){
            return $this->writeToFile(md5_file($file->getFile()));
        }else{
            if($file !== null && is_string($file))
            {
                $eTagfile = New File($file, true);
                if($eTagfile->exist()) {
                    return $this->writeToFile(md5_file($file));
                }
            }
        }
        Throw New FileException(
            sprintf('%s->%s: Expected object type Wbengine\Application\Path\File or string as filename, but "%s" given'
                , __CLASS__
                , __FUNCTION__
                , gettype($file)
            )
        );
    }


    /**
     * Replace an content in file.
     *
     * @param $search
     * @param $replace
     * @throws FileException
     */
    public function replaceInFile($search, $replace)
    {
        $this->writeToFile(str_replace($search, $replace, $this->getContent()));
    }


    /**
     * Write content to a file...
     *
     * @param $fileContent
     * @param bool $minimize
     * @return $this
     * @throws FileException
     */
    public function writeToFile($fileContent, $minimize = false)
    {
        if($minimize === true){
            $fileContent = $this->minimize($fileContent);
        }

        if($this->getFile()) {
            $this->_status = file_put_contents($this->getFile(), $fileContent);
            return $this;
        }else{
            Throw New FileException(
                sprintf('%s->%s: Cannot write to file "%s".'
                    , __CLASS__
                    , __FUNCTION__
                    , $this->getFile()
                )
            );
        }
    }


    /**
     * Return file extension...
     *
     * @param $fileType
     * @return false|int|string
     */
    public function getExtensionByFileType($fileType){
        return array_search($fileType, $this->_file_types);
    }


    /**
     * Return size of content writable to a file...
     * @param bool $human
     * @return null|string
     */
    public function getWritedData($human = false){
        return ($human === TRUE)? $this->_getFilesize($this->_writed):$this->_writed;
    }


    /**
     * Return content from an file...
     * @return bool|string
     * @throws FileException
     */
    public function getContent()
    {
        if($this->isReadable()) {
            return file_get_contents($this->getFile());
        }else{
            Throw New FileException(
                sprintf('%s->%s: Cannot read file "%s".'
                    , __CLASS__
                    , __FUNCTION__
                    , $this->getFile()
                )
            );
        }
    }


    private function _setFullPathName(){
        $this->_fullPath = $this->getDirectory().'/'.$this->getFileName();
    }

    public function getFileSize(){
        return filesize($this->getFile());
    }

    public function getHumanFileSize(){
        return $this->_getFilesize(filesize($this->getFile()));
    }

    public function getFileExtension(){
        return $this->_file['extension'];
    }

    public function getDirectory(){
        return $this->_file['dirname'];
    }

    public function getFileName(){
        return $this->_file['basename'];
    }

    public function getFileBaseName(){
        return $this->_file['filename'];
    }

    public function getFile(){
        return $this->_fullPath;
    }

    public function exist(){
        return file_exists($this->getFile());
    }

    public function isReadable(){
        return is_readable($this->getFile());
    }

    public function isWritable(){
        return is_writable($this->getFile());
    }

    public function isRegularFile(){
        return is_file($this->getFile());
    }

    public function isDiresctory(){
        return is_dir($this->getDirectory());
    }

    public function isSymlink(){
        return is_link($this->getFileName());
    }

    public function getStatus(){
        return (boolean)$this->_status;
    }
}