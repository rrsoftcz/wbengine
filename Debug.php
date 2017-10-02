<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 01.10.17
 * Time: 17:22
 */

namespace Wbengine;


use Wbengine\Application\Application;

class Debug
{
    CONST COLS_LG_MODIFIER       = '2';
    CONST CONTAINER_STYLE = '
        padding: 5px;
        border-top: 1px solid #c0c0c0;
        border-bottom: 1px solid #c0c0c0;
        font-size: 12px;
        margin-top: 20px;';

    private $estimate_time   = 0;
    private $queries_count   = 0;
    private $start_time      = 0;
    private $end_time        = 0;
    private $application     = null;
    private $queries         = array();
    public $enabled         = false;


    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->start_time = $application->getStartTime();
        $this->end_time = $application->getEndTime();
        $this->enabled = $application->isDebugOn();
//        $this->estimate_time = floor(($this->end_time - $this->start_time) * 1000);
    }

    private function _getContainer($content){
        $html = '';
        $html .= '<div class="container" style="'.self::CONTAINER_STYLE.'">';
        $html .= '<div class="row">';
        $html .= $content;
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function _getAppTime($value){
        return sprintf('<div class="col-lg-%s"><b>App time:</b> %s ms</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getPhpCoreTime($value){
        return sprintf('<div class="col-lg-%s"><b>PHP:</b> %s ms</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getDbQueries($value){
        return sprintf('<div class="col-lg-%s"><b>Queries:</b> %s</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getDbQTime($value){
        return sprintf('<div class="col-lg-%s"><b>Db:</b> %s ms</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    public function show(){
        $_cols = '';
        $_cols .= $this->_getAppTime($this->getSumTime());
        $_cols .= $this->_getPhpCoreTime($this->getEstimatedTime());
        $_cols .= $this->_getDbQueries($this->getDbQueriesCount());
        $_cols .= $this->_getDbQTime($this->getDbQueriesTimeSum());

        return $this->_getContainer($_cols);
    }

    public function getValues(){
        $std = new \stdClass();
        $std->estimatedtime = $this->getEstimatedTime();
        return $std;
    }

    public function setStartTime($start_time){
        $this->start_time = $start_time;
    }

    public function setEndTime($end_time){
        $this->end_time = $end_time;
    }

    public function getEstimatedTime(){
        return round(($this->end_time - $this->start_time) * 1000,2);
    }

    public function getDbQueriesCount(){
        return $this->application->getAllQueriesCount();
    }

    public function getSumTime(){
        return round(($this->application->getAllQueriesTime() + ($this->end_time - $this->start_time))*1000,2);
    }

    public function getDbQueriesTimeSum(){
        return round($this->application->getAllQueriesTime()*1000,2);
    }


}