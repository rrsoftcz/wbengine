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
    CONST COLS_LG_MODIFIER  = '3';
    CONST CONTAINER_STYLE   = '
            <style>
                .debug{
                    width: 100%;
                    text-align: center;
                    border-top: 1px solid #CCCCCC;
                }
                .row{
                    width: 100%;
                    border-bottom: 1px solid #CCCCCC;
                }
                .debug .col, .sql .row{
                    display: inline-block;
                    font-size: 12px;
                    padding: 4px 6px;
                }
                .sql .col{

                }
                .sql{
                    width: 100%;
                    margin: 0 auto;
                    display: none;
                }
                .odd{
                    background-color: #EEEEEE;
                }
                .even{
                    background-color: #F5F5F5;
                }
                .ltime{
                    color: #9B410E;
                }
                @media screen and (max-width: 600px){
                    .col{
                        text-align: left;
                        display: flex;
                        width: 100%;
                    }
                }
            </style>';

    private $start_time     = 0;
    private $end_time       = 0;
    private $application    = null;
    public $enabled         = false;


    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->start_time = $application->getStartTime();
        $this->end_time = $application->getEndTime();
        $this->enabled = $application->isDebugOn();
    }

    private function _getContainer($content,$queries = null){
        $html = '';
        $html = self::CONTAINER_STYLE;
        $html .= '<div class="debug">';
        $html .= '<div class="row">';
        $html .= $content;
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div id="sql" class="sql" stylex="width: 1140px;margin: 0 auto;display: none;">';
        $html .= $this->getQueries();
        $html .= '</div>';

        return $html;
    }

    private function _getAppTime($value){
        return sprintf('<div class="col"><b>App time:</b> %s ms</div>',
            $value
        );
    }

    private function _getPhpCoreTime($value){
        return sprintf('<div class="col"><b>PHP:</b> %s ms</div>',
            $value
        );
    }

    private function _getDbQueries($count, $time){
        return sprintf('<div class="col"><a id="db" href="#"><b>DB:</b> %s queries (%s ms)</a></div>',
            $count,
            $time
        );
    }

    private function _getDbQTime($value){
        return sprintf('<div class="col"><b>Db:</b> %s ms</div>',
            self::COLS_LG_MODIFIER,
            $value
        );
    }

    private function _getSectionsCount($value){
        return sprintf('<div class="col"><b>Sections:</b> %s</div>',
            $value
        );
    }

    private function _getBoxesCount($value){
        return sprintf('<div class="col"><b>Boxes:</b> %s</div>',
            $value
        );
    }

    private function _getEnvironment($env, $config){
        return sprintf('<div class="col"><b>Devel:</b> %s (%s)</div>',
            ($env)?'True':'False',
            $config
        );
    }

    private function _getSiteInfo($siteid, $sections, $boxes){
        return sprintf('<div class="col"><b>Site ID:</b> %s | Sectons: %s | Boxes: %s</div>',
            $siteid,
            $sections,
            $boxes
        );
    }

    private function _getAppSpeedInfo(){
        return sprintf('<div class="col"><b>Speed:</b> PHP: %s ms | Sum: %s ms</div>',
            $this->getEstimatedTime(),
            $this->getSumTime()
        );
    }

    private function _getSystemInfo($info){
        return sprintf('<div class="col"><b>Env:</b> PHP %s | Config: %s</div>',
            $info,
            $this->getConFigFile()
        );
    }

    public function show(){
        $_cols = '';
        $_cols .= $this->_getSystemInfo($this->getPhpVersion());
        $_cols .= $this->_getAppSpeedInfo($this->getSumTime());
        $_cols .= $this->_getDbQueries($this->getDbQueriesCount(),$this->getDbQueriesTimeSum());
        $_cols .= $this->_getSiteInfo($this->application->getSite()->getSiteId(),$this->getSectionsCount(),$this->getBoxesCount());

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

    public function getPhpVersion(){
        return phpversion();
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
            ($i%2) ? $cls = 'even' : $cls='odd';
            $tmp.= sprintf('
                    <div class="row %s">
                        <div class="col"><b>'
                            .$i.'.</b> '.$query['query']
                            .'&nbsp;<span class="ltime">('
                            .$query['time']
                            .' ms</span>)
                        </div>
                   </div>', $cls);
        }
        return $tmp;
    }

    /**
     * @return string
     */
    private function _getJs(){
        $tmp = '<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
                    integrity="sha256-3edrmyuQ0w65f8gfBsqowzjJe2iM6n0nKciPUp8y+7E="
                    crossorigin="anonymous">
                </script>';
        $tmp .= '<script>
        $( "#db" ).click(function() {
            $( "#sql" ).toggle();
        });
        </script>';
        return $tmp;
    }


}