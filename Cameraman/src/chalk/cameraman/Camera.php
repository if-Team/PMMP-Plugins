<?php
/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-21 16:10
 */

namespace chalk\cameraman;

use chalk\cameraman\movement\Movement;
use chalk\cameraman\task\CameraTask;
use pocketmine\entity\Entity;
use pocketmine\Player;

class Camera {
    /** @var Entity */
    private $target;

    /** @var Movement[] */
    private $movements = [];

    /** @var number */
    private $slowness;

    /** @var boolean */
    private $running = false;

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

    public function start(){
        if(!$this->running){
            $this->running = true;

            Cameraman::getInstance()->getServer()->getScheduler()->scheduleRepeatingTask(new CameraTask($this), 20 / Cameraman::TICKS_PER_SECOND);
        }
    }

    public function onFinished(){
        if($this->running){
            $this->running = false;

            $target = $this->getTarget();
            if($target instanceof Player){
                $target->sendMessage("Done!");
            }
        }
    }
}