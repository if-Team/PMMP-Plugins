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

    /** @var int */
    private $index = 0;

    function __construct(Camera $camera){
        parent::__construct(Cameraman::getInstance());
        $this->camera = $camera;
    }

    /**
     * @param $currentTick
     */
    public function onRun($currentTick){
        if($this->index >= count($this->getCamera()->getMovements())){
            $this->getCamera()->stop();
            return;
        }

        if(($location = $this->getCamera()->getMovement($this->index)->tick($this->getCamera()->getSlowness())) === null){
            $this->index++;
            return;
        }

        $this->getCamera()->getTarget()->setPositionAndRotation($location, $location->getYaw(), $location->getPitch());
        Cameraman::sendMovePlayerPacket($this->getCamera()->getTarget());
    }

    /**
     * @return Camera
     */
    public function getCamera(){
        return $this->camera;
    }
}
