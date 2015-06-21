<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-21 16:36
 */

namespace chalk\cameraman\task;

use chalk\cameraman\Camera;
use chalk\cameraman\Cameraman;
use pocketmine\scheduler\PluginTask;

class CameraTask extends PluginTask {
    /** @var Camera */
    private $camera;

    function __construct(Camera $camera){
        parent::__construct(Cameraman::getInstance());
        $this->camera = $camera;
    }

    /**
     * @param $currentTick
     */
    public function onRun($currentTick){
        $movement = current($this->getCamera()->getMovements());
        if($movement === false){
            $this->getCamera()->stop();
            return;
        }

        $position = $movement->tick($this->getCamera()->getSlowness());
        if($position === false){
            next($this->getCamera()->getMovements());
            return;
        }

        $this->getCamera()->getTarget()->setPosition($position);
    }

    /**
     * @return Camera
     */
    public function getCamera(){
        return $this->camera;
    }
}