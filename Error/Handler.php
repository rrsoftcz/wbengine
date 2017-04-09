<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site main class.
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Error;

class Handler
{

    /**
     * Instance of Class_Renderer
     * @var Class_Renderer
     */
    private $_errors = NULL;


    /**
     * Do nothink here...
     * @param construct
     */
    public function __construct()
    {

    }


    /**
     * Returns the first error found as string...
     * @return string
     */
    public function __toString()
    {
        if (sizeof($this->_errors)) {

            foreach ($this->_errors as $error) {
                return $error->getCode() . "# - " . $error->getMessage();
            }
        }
        return;
    }


    /**
     * Return all errors as array collection
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }


    /**
     * Return Errors count
     * @return integer
     */
    public function getcount()
    {
        return sizeof($this->_errors);
    }


    /**
     * Create new exception from fired error handler.
     * We just create exception with related message prefix.
     *
     * @param integer $code
     * @param string $message
     * @param string $file
     * @param integer $line
     * @return Exception
     * @throws Exception\ErrorHandlerException
     */
    public function SetErrorHandler($code, $message, $file, $line)
    {
        $code = error_reporting() & $code;


        if ($code === 0) {
	        return;
        }

        switch ($code) {
            case E_ERROR:
                $ePrefix = "Error ";
                break;
            case E_WARNING:
                $ePrefix = "Warning ";
                break;
            case E_PARSE:
                $ePrefix = "Parse Error ";
                break;
            case E_NOTICE:
                $ePrefix = "Notice ";
                break;
            case E_CORE_ERROR:
                $ePrefix = "Core Error ";
                break;
            case E_CORE_WARNING:
                $ePrefix = "Core Warning ";
                break;
            case E_COMPILE_ERROR:
                $ePrefix = "Compile Error ";
                break;
            case E_COMPILE_WARNING:
                $ePrefix = "Compile Warning ";
                break;
            case E_USER_ERROR:
                $ePrefix = "User Error ";
                break;
            case E_USER_WARNING:
                $ePrefix = "User Warning ";
                break;
            case E_USER_NOTICE:
                $ePrefix = "User Notice ";
                break;
            case E_STRICT:
                $ePrefix = "Strict Notice ";
                break;
            case E_RECOVERABLE_ERROR:
                $ePrefix = "Recoverable Error ";
                break;
            default:
                $ePrefix = "Unknown error ($code) ";
                break;
        }

        $message = $ePrefix . $message;

        $_properties = array(
            "code" => $code,
            "message" => $message,
            "file" => $file,
            "line" => $line,
        );

        $this->_errors[] = New Template($_properties);

        if ($code !== E_NOTICE) {
            throw New Exception\ErrorHandlerException(__class__ . ": " . $message .
                " in file: " . $file . " on line: " . $line, $code);
        }
    }

}
