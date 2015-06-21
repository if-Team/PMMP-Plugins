<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-21 16:36
 */

namespace chalk\cameraman\task;

use chalk\cameraman\Camera;
use chalk\cameraman\Cameraman;
use pocketmine\scheduler\PluginTask;
use pocketmine\network\protocol\MovePlayerPacket;

class CameraTask extends PluginTask {
    /** @var Camera */
    private $camera;

    /** @var int */
    private $i = 0;

    function __construct(Camera $camera){
        parent::__construct(Cameraman::getInstance());
        $this->camera = $camera;
    }

    /**
     * @param $currentTick
     */
    public function onRun($currentTick){
        if($this->i >= count($this->getCamera()->getMovements())){
            $this->getCamera()->stop();
            return;
        }

        $location = $this->getCamera()->getMovements()[$this->i]->tick($this->getCamera()->getSlowness());
        if($location === false){
            $this->i++;
            return;
        }

        $target = $this->getCamera()->getTarget();
        $target->setPositionAndRotation($location, $location->getYaw(), $location->getPitch());

        $pk = new MovePlayerPacket();
        $pk->eid = 0;
        $pk->x = $target->getX();
        $pk->y = $target->getY();
        $pk->z = $target->getZ();
        $pk->yaw = $target->getYaw();
        $pk->bodyYaw = $target->getYaw();
        $pk->pitch = $target->getPitch();
        $pk->onGround = false;

        $target->dataPacket($pk);
    }

    /**
     * @return Camera
     */
    public function getCamera(){
        return $this->camera;
    }
}
