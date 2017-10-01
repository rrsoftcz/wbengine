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
    private $estimate_time   = 0;
    private $queries_count   = 0;
    private $start_time      = 0;
    private $end_time        = 0;
    private $application     = null;
    private $queries         = array();


    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->start_time = $application->getStartTime();
        $this->end_time = $application->getEndTime();
//        $this->estimate_time = floor(($this->end_time - $this->start_time) * 1000);
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