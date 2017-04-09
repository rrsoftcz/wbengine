<?php

/**
 * Templater interface.
 * All implemented methods list.
 * @author roza
 */

namespace Wbengine\Renderer;

interface RendererInterface {


    /**
     * Returns the template output
     */
    public function fetch(
    $template, $cache_id = NULL, $compile_id = NULL);

    /**
     * Displays the template
     */
    public function display(
    $template, $cache_id = NULL, $compile_id = NULL);

    /**
     * Assign values to the templates
     */
    public function assign(
    $varname, $var = NULL, $scope = NULL);

    /**
     * Set compiling files directory
     */
    public function setCompileDir($path);

    /**
     * Set Template files directory
     */
    public function setTemplateDir($path);

    /**
     * set Config directory
     */
    public function setConfigDir($path);

    /**
     * set cache directory
     */
    public function setCacheDir($path);

    /**
     * register object to template
     */
    public function registerObject($name, $value);
}
