<?php
/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-21 16:10
 */

namespace chalk\cameraman;

use chalk\cameraman\movement\Movement;
use chalk\cameraman\task\CameraTask;
use pocketmine\entity\Entity;

class Camera {
    /** @var Entity */
    private $target;

    /** @var Movement[] */
    private $movements = [];

    /** @var number */
    private $slowness;

    /** @var int */
    private $taskId = -1;

    /**
     * @param Entity $target
     * @param Movement[] $movements
     * @param number $slowness
     */
    function __construct(Entity $target, array $movements, $slowness){
        $this->target = $target;
        $this->movements = $movements;
        $this->slowness = $slowness;
    }

    /**
     * @return Entity
     */
    public function getTarget(){
        return $this->target;
    }

    /**
     * @return Movement[]
     */
    public function getMovements(){
        return $this->movements;
    }

    /**
     * @return number
     */
    public function getSlowness(){
        return $this->slowness;
    }

    public function isRunning(){
        return $this->taskId !== -1;
    }

    public function start(){
        if(!$this->isRunning()){
            reset($this->getMovements());
            $this->taskId = Cameraman::getInstance()->getServer()->getScheduler()->scheduleRepeatingTask(new CameraTask($this), 20 / Cameraman::TICKS_PER_SECOND)->getTaskId();
        }
    }

    public function stop(){
        if($this->isRunning()){
            Cameraman::getInstance()->getServer()->getScheduler()->cancelTask($this->taskId);
            $this->taskId = -1;
        }
    }
}