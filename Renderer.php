<?php

/**
 * $Id$
 * ----------------------------------------------
 * This renderer class manage and render all HTML
 * templates used in CMS.
 * Class use default HTML template system defined
 * in site class.
 *
 * @package RRsoft-CMS
 * @version $Rev$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\Application\Application;
use Wbengine\Application\ApplicationException;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Application\Path\Path;
use Wbengine\Box\WbengineBoxAbstract;
use Wbengine\Components\ComponentParentInterface;
use Wbengine\Exception\RuntimeException;
use Wbengine\Renderer\Exception\RendererException;

//use Wbengine\Renderer;

class Renderer extends Renderer\Adapter
{

    /**
     * Default tamplates files extensions
     * @var string
     */
    private $_extension = '.tpl';


    /**
     * Given CMS class object
     * @var object
     */
    private $_app = NULL;


    /**
     * HTML formater
     * @var object
     */
    private $_formater = NULL;


    /**
     * Default formater class name
     * @var string
     */
    private $_formaterName = 'texy';


    /**
     * Default html text formater is implemented...
     * @var string
     */
    private $_formaterPath = 'vendor/Texy/';

    private $_rendererTemplatesDir = null;
    private $_rendererCompiledDir = null;
    private $_rendererConfigDir = null;
    private $_rendererAdapterName = null;

    private $_path  = null;


    /**
     * Create HTML templater object and
     * assign all defined variables.
     *
     * @param object|Application $parent
     * @throws RuntimeException
     */
    function __construct(ComponentParentInterface $parent)
    {
//Utils::dump(get_class($parent));
        if ($parent instanceof Application) {
            $this->_app = $parent;
            $this->_path = $parent->_getObjectPath();
        }
//        var_dump(Config::getRendererCompiledDir());

        $this->setAdapterName($this->getRendererAdapterName());
        $this->setTemplateDir($this->getRendererTemplatesPath());
        $this->setCompileDir($this->getRendererCacheDir());
        $this->setConfigDir($this->getConfigDir());
    }


    /**
     * Return site instance object
     * @return Application
     */
    public function getParent()
    {
        return $this->_app;
    }

    /**
     * Return instance of object Path created by parent Application
     * @return null|Path
     */
    public function Path(){
        if($this->_path instanceof Path) {
            return $this->_path;
        }
        return $this->_path = new Path();
    }

    /**
     * Return site instance object
     * @return Site
     */
    public function getSite()
    {
        return $this->getParent()->getSite();
    }


    /**
     * Return default teplate extension
     * @return string
     */
    public function getExtension()
    {
        return $this->_extension;
    }


    /**
     * Return Renderer adapter name
     * Smary | Yum | etc
     * @return string
     */
    public function getRendererAdapterName()
    {
        return Config::getRendererAdapterName();
    }


    /**
     * Return default config directory
     * @return null|string
     */
    public function getConfigDir()
    {
        if(null === $this->_rendererConfigDir) {
            $this->Path()->addPath(Path::TYPE_CONFIG, Config::getRendererConfigDir(), true);
            $this->_rendererConfigDir = $this->Path()->getConfigDir();
        }
        return $this->_rendererConfigDir;
    }


    /**
     * Return renderer default directory
     * @return null|string
     */
    public function getRendererCacheDir()
    {
        if(null === $this->_rendererCompiledDir) {
            $this->Path()->addPath(Path::TYPE_RENDERER_TEMP, Config::getRendererCompiledDir(), true);
//            var_dump($this->Path()->getPath(Path::TYPE_BASE));
            $this->_rendererCompiledDir = $this->Path()->getRendererCompiledDir();
            if(!file_exists($this->_rendererCompiledDir)){
//                $this->_rendererCompiledDir = '/tmp/';
                 Throw New Exception\RuntimeException(sprintf("%s -> %s: Not accessible renderer compile dir '%s'!",
                 __CLASS__,
                 __FUNCTION__,
                 $this->_rendererCompiledDir));
            }
        }
        return $this->_rendererCompiledDir;
    }

    /**
     * Return set templates path by parent application
     * @return string
     */
    public function getRendererTemplatesPath()
    {
        if(null === $this->_rendererTemplatesDir) {
            $this->Path()->addPath(Path::TYPE_TEMPLATES, Config::getRendererTemplatesDir(), true);
            $this->_rendererTemplatesDir = $this->Path()->getTemplatesDir();
        }
        return $this->_rendererTemplatesDir;
    }


    /**
     * Return declared HTML formater
     * @return object
     */
    public function getFormater()
    {
        if (!$this->_formater) {
            $_formater = new Formater();

            $this->_formater = $_formater->getFormater($this->_formaterName, $this->_formaterPath);
        }

        return $this->_formater;
    }


    /**
     * Return rendered main site template.
     * @param Application $App
     * @return string as HTML content
     * @throws RuntimeException
     */
    public function dispatch(Application $App)
    {
        try {
            if ($App instanceof Application) {
                // assign all application vars to templater ...
                $this->assign($App->getVars(), NULL, 'global');
                // ...and show content...
                if (Config::minimizeHtml()) {
                    //@TODO JUST TESTING TO MINIFY HTML SOURCE CODE...
                    $source = preg_replace("'\s+'ms", " ", $this->fetch('site' . $this->getExtension()));
                    echo($source);
                } else {
                    $this->display('site' . $this->getExtension());
                }
            } else {
                throw New Exception\RuntimeException(__METHOD__
                    . ': Method Excepts Wbengine\Application\Application argument.');
            }
        } catch (Exception\RuntimeException $e) {

            throw New RuntimeException(__METHOD__
                . ": Throwed Exception by object: " . $e->getMessage());
        }
    }


    /**
     * This method return HTML template content by
     * given template name and with variables by
     * given var array.
     * Also we can choice a type of action.
     * Alowed action is display|fetch.
     *
     * @param string $template
     * @param mixed $vars
     * @throws RuntimeException
     * @return string as HTML
     */
    public function render($template = NULL, $vars = NULL)
    {
        if (NULL === $template) {
            throw New RendererException(__METHOD__
                . ': Expected template name as string, but null given.');
        }

        // Assign given vars ..?
        if (!empty($vars)) {
            // Remove slashes from the given path...
            $valueName = preg_replace('/^(.*)(\/)(\w+)/i', '$3', $template);

            $this->assign($valueName, $vars);
        }
        
        // Check if file extension presents...
        if(!preg_match('/\..+$/', $template)){
            $template .= $this->getExtension();
        }

        // First, try to locate template source file inside application folder ...
        if (file_exists($this->getAppTeplatePath($template))){
            return $this->fetch($this->getAppTeplatePath($template));
            // second, try to locate source template file localy ...
        }elseif(file_exists($this->getLocalTeplatePath($template))){
            return $this->fetch($this->getLocalTeplatePath($template));
        }else{
            throw New RendererException(__METHOD__
                    . ': Box template file "' . $this->getAppTeplatePath($template) . '" not found.');
        }
        
    }


    /**
     * @param $box
     * @throws RuntimeException
     */
    public function renderBox($templateName, $vars = null)
    {
        if(empty($templateName)){
            throw New RendererException(__METHOD__
                . ': The template name can not be empty.');
        }
        // Assign given vars ..?
        if (!empty($vars)) {
            // Remove slashes from the given path...
            $valueName = preg_replace('/^(.*)(\/)(\w+)/i', '$3', $templateName);
//            var_dump(strtolower($valueName));
            $this->assign(strtolower($valueName), $vars);
        }

        if(!preg_match('/\..+$/', $templateName)){
            $templateName .= $this->getExtension();
        }


        if (file_exists($this->getAppTeplatePath($templateName))){
            return $this->fetch($this->getAppTeplatePath($templateName));
            // second, try to locate source template file localy ...
        }else{
            throw New RendererException(__METHOD__
                . ': Box template file "' . $this->getAppTeplatePath($templateName) . '" not found.');
        }
    }



    /**
     * Assign variabel to template.
     *
     * @param string $name
     * @param mixed $value
     * @throws Class_Renderer_Exception
     */
    public function setVar($name, $value)
    {
        if (empty($name)) {
            include_once 'Class/Renderer/SessionException.php';
            throw new Class_Renderer_Exception('The var name should be an string.');
        }

        $this->assign($name, $value);
    }


    /**
     * Return filename with local templates path
     *
     * @param string $filename
     * @return string
     */
    public function getLocalTeplatePath($filename){
        return __DIR__ . '/Application/' . $filename;
    }

    public function getAppTeplatePath($filename){
        return $this->Path()->getPath(Path::TYPE_TEMPLATES, null, true) . $filename;
    }


    public function showException(\Exception $e){
        die(
        sprintf(
            file_get_contents(
                $this->getLocalTeplatePath('exception.tpl')),
            get_class($e),
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()));
    }

    /**
     * Return html error box created from given
     * exception object.
     * Method also fire HTML header with appropriate
     * html error related to error number taken from
     * the given exception.
     *
     * @param Exception $exception
     * @return string as HTML
     */
    public function getErrorBox($exception = NULL)
    {
        if ((int)$exception->getCode() === 0) {
            // @todo Do catch right if exception error code does not exist...
        }

        $error['code'] = $exception->getCode();
        $error['title'] = 'Cauth exception:';

        if ($this->getParent()->isDebugOn()) {
            $error['msg'] = $exception->__toString();
        } else {
            $error['msg'] = $exception->getMessage();
        }

        // Here we can manage templates for an specify errors.
        switch ((int)$exception->getCode()) {
            case HTML_ERROR_404:
                header("HTTP/1.1 404 Not Found");

                $this->getSite()->setHtmlTitle('HTTP/1.1 404 Not Found');
                $tmp = $this->render(HTML_ERROR_404);
                break;

            case HTML_ERROR_401:
                header("HTTP/1.1 401 Unauthorized");

                $this->getSite()->setHtmlTitle('HTTP/1.1 401 Unauthorized');
                $tmp = $this->render(HTML_ERROR_401);
                break;

            case HTML_ERROR_410:
                $this->getSite()->setHtmlTitle('HTTP/1.1 410 Gone');
                $tmp = $this->render(HTML_ERROR_410);
                break;

            case HTML_ERROR_500:
                $this->getSite()->setHtmlTitle('HTTP/1.1 500 Internal Server Error');
                $tmp = $this->render(HTML_ERROR_500);
                break;

            default:
                $tmp = $this->showException($exception);
                break;
        }

        return $tmp;
    }

}
