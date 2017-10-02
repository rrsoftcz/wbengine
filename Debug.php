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
    CONST COLS_LG_MODIFIER = '2';
    CONST CONTAINER_STYLE = '
        padding: 6px 0 6px 6px;
        border-top: 1px solid #c0c0c0;
        border-bottom: 1px solid #c0c0c0;
        font-size: 12px;
        margin-top: 20px;
        width: 1170px;
        margin:0 auto;';

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

    private function _getContainer($content,$queries = null){
        $html = '';
        $html .= '<div style="'.self::CONTAINER_STYLE.'">';
        $html .= '<div class="row">';
        $html .= $content;
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div id="sql" style="width: 1140px;margin: 0 auto;display: none;">';
        $html .= $this->getQueries();
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

    private function _getDbQueries($count, $time){
        return sprintf('<div class="col-lg-%s"><a id="db" href="#"><b>DB:</b> %s queries (%s ms)</a></div>',
            self::COLS_LG_MODIFIER,
            $count,
            $time
        );
    }

    private function _getDbQTime($value){
        return sprintf('<div class="col-lg-%s"><b>Db:</b> %s ms</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getSectionsCount($value){
        return sprintf('<div class="col-lg-%s"><b>Sections:</b> %s</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getBoxesCount($value){
        return sprintf('<div class="col-lg-%s"><b>Boxes:</b> %s</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getEnvironment($env, $config){
        return sprintf('<div class="col-lg-%s"><b>Devel:</b> %s (%s)</div>',
            self::COLS_LG_MODIFIER,
            ($env)?'True':'False',
            $config
        );
    }

    private function _getSiteInfo($siteid, $sections,$boxes){
        return sprintf('<div class="col-lg-%s"><b>Site ID:</b> %s | Sectons: %s | Boxes: %s</div>',
            self::COLS_LG_MODIFIER,
            $siteid,
            $sections,
            $boxes
        );
    }

    public function show(){
        $_cols = '';
        $_cols .= $this->_getAppTime($this->getSumTime());
        $_cols .= $this->_getPhpCoreTime($this->getEstimatedTime());
        $_cols .= $this->_getDbQueries($this->getDbQueriesCount(),$this->getDbQueriesTimeSum());
        $_cols .= $this->_getSiteInfo($this->application->getSite()->getSiteId(),$this->getSectionsCount(),$this->getBoxesCount());
        $_cols .= $this->_getEnvironment($this->getEnv(), $this->getConFigFile());

        $_cols.= $this->_getJs();

        return $this->_getContainer($_cols);
    }

    public function getValues(){
        $std = new \stdClass();
        $std->estimatedtime = $this->getEstimatedTime();
        return $std;
    }

    public function getConFigFile(){
        return $this->application->getConfigFile();
    }

    public function getEnv(){
        return $this->application->getEnv();
    }

    public function getSectionsCount(){
        return $this->application->getSectionsCount();
    }

    public function getBoxesCount(){
        return $this->application->getBoxesCount();
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

    public function getQueries(){
        $queries = Db::getAllQueries();
        $tmp = '';
        $i = 0;
        foreach ($queries as $query){
            $i++;
            ($i%2) ? $bg = '#E6E6FA' : $bg='#E0FFFF';
            $tmp.='<div class="row" style="font-size: 12px;background-color:'.$bg.';">
                    <div class="col-lg-12" style="border-bottom: 1px solid #DCDCDC; padding: 6px;">'
                        .$query['query']
                        .' <span style="color: #9B410E">('
                        .$query['time']
                        .' ms</span>)
                    </div>
                   </div>';
        }
        return $tmp;
    }

    /**
     * @return string
     */
    private function _getJs(){
        $tmp = '';
        $tmp .= '<script>
        $( "#db" ).click(function() {
            $( "#sql" ).toggle();
        });
        </script>';
        return $tmp;
    }


}