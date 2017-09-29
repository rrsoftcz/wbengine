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
use Wbengine\Application\Path\Path;
use Wbengine\Exception\RuntimeException;

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
     * @param object|Application $App
     * @throws RuntimeException
     */
    function __construct(Application $App)
    {
        if ($App instanceof Application) {
            $this->_app = $App;
            $this->_path = $App->_getObjectPath();
        } else {
            throw new Exception\RuntimeException('Require instance of Wbengine\Application, but NULL given.');
        }
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
        return $this->_path;
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
            $this->_rendererCompiledDir = $this->Path()->getRendererCompiledDir();
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
                // assign all needed vars to templater..
                $this->assign($App->getVars(), NULL, 'global');
                // ...and show content...
                if (Config::minimizeHtml()) {
                    //@todo JUST TESTING TO MINIFY HTML...
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
        $_path =  $template . $this->getExtension();

        if (NULL === $template) {
            throw New Exception\RuntimeException(__METHOD__
                . ': Expected template name as string, but null given.');
        }

        if (!file_exists($this->getParent()->_getObjectPath()->getPath(Path::TYPE_TEMPLATES,true).$_path)) {
            throw New Exception\RuntimeException(__METHOD__
                . ': Template file "' . $_path . '" not exist.');
        }

        // Assign given vars ..?
        if (!empty($vars)) {
            // Remove slashes from the given path...
            $valueName = preg_replace('/^(.*)(\/)(\w+)/i', '$3', $template);

            $this->assign($valueName, $vars);
        }

        return $this->fetch($template . $this->_extension);
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
                $tmp = $this->render(HTML_ERROR, $error);
                break;
        }

        return $tmp;
    }

}
