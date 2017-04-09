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
    private $_parent = NULL;


    /**
     * Given site class object
     * @var object
     */
    private $_site = NULL;


    /**
     * HTML formater
     * @var object
     */
    private $_formater = NULL;


    /**
     * Default renderer class anme
     * @var string
     */
    private $_rendererName = 'Smarty';


    /**
     * Default formater class name
     * @var string
     */
    private $_formaterName = 'texy';


    /**
     * Default template path
     * @var string
     */
    private $_templatePath = null;
    private $_formaterPath = 'vendor/Texy/';


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
            $this->_parent = $App;
//            $this->_site = $App->getSite();
//            $this->_templatePath = $App->getTemplatesDir();
        } else {
            throw new Exception\RuntimeException('Require instance of Wbengine\Application, but NULL given.');
        }
//var_dump($this->getTemplatesPath());
        $this->setAdapterName($this->_rendererName);
        $this->setCompileDir(APP_DIR . '/Cache/Renderer/');
        $this->setTemplateDir($this->getTemplatesPath());
        $this->setConfigDir(APP_DIR . 'Config/');

//        var_dump($App->getException());
    }


    /**
     * Return site instance object
     * @return Application
     */
    public function getParent()
    {
        return $this->_parent;
    }


    /**
     * Return site instance object
     * @return Class_Site
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
     * Return set templates path by parent application
     * @return string
     */
    public function getTemplatesPath()
    {
        return $this->getParent()->getTemplatesDir();
//        if($this->_app->getTemplateDir() === null) {
//            $this->_templatePath = APP_DIR . '/Src/View/Front/';
////            var_dump(APP_DIR . '/Src/View/Front/');
////            var_dump(var_dump(APP_DIR .$this->_app->getTemplateDir().$this->_app->getAppTypeId()));die();
//        }else{
//            $this->_templatePath = $this->_app->getTemplateDir().$this->_app->getAppTypeId()."/";
//        }
//
//        return $this->_templatePath;
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
     * @return string as HTML content
     */
    public function dispatch(\Wbengine\Application\Application $App)
    {
//	var_dump($webengine->getVars());
        try {
            if ($App instanceof \Wbengine\Application\Application) {
                // assign all needed vars to templater..
                $this->assign($App->getVars(), NULL, 'global');
//                var_dump($this->_templatePath);
                // ...and show content...
//                $this->display('site' . $this->getExtension());
//
                if(Config::minimizeHtml()) {
//                //@todo JUST TESTING TO MINIFI HTML...
                    $source = preg_replace("'\s+'ms", " ", $this->fetch('site' . $this->getExtension()));
                    echo($source);
                }else{
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
        $_path = $this->getTemplatesPath() . $template . $this->getExtension();

        if (NULL === $template) {
            throw New Exception\RuntimeException(__METHOD__
                . ': Template name string expected, but null given.');
        }


        if (!file_exists($_path)) {
            throw New Exception\RuntimeException(__METHOD__
                . ': Template file "' . $_path . '" not exist.');
        }

        // Assign given vars ..?
        if (!empty($vars)) {
            // Remove slashes from the given path...
            $valueName = preg_replace('/^(.*)(\/)(\w+)/i', '$3', $template);
            $this->assign($valueName, $vars);
        }

        if (is_null($template)) {
            return $this->fetch('index.tpl');
        } else {
            return $this->fetch($template . $this->_extension);
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
        if ((int)$exception->getCode() === 0){
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
