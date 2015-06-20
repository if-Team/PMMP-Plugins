<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:24
 */

namespace chalk\cameraman\movement;

use pocketmine\entity\Entity;
use pocketmine\level\Position;

class StraightMovement extends Movement {
    /** @var Position */
    private $destination;

    private $dx, $dy, $dz;
    private $distance, $d = 0;

    function __construct(Entity $target, Position $destination, Position $origin = null){
        parent::__construct($target, $origin);
        $this->destination = $destination;

        if($this->getOrigin()->getLevel() !== $this->destination->getLevel()){
            throw new \InvalidArgumentException();
        }

        $this->dx = $this->getDestination()->getFloorX() - $this->getOrigin()->getFloorX();
        $this->dy = $this->getDestination()->getFloorY() - $this->getOrigin()->getFloorY();
        $this->dz = $this->getDestination()->getFloorZ() - $this->getOrigin()->getFloorZ();

        $this->distance = max($this->dx, $this->dy, $this->dz);
    }

    /**
     * @return Position
     */
    public function getDestination(){
        return $this->destination;
    }

    /**
     * @return boolean
     */
    public function tick(){
        $progress = $this->d++ / $this->distance;
        if($progress > 1){
            return true;
        }

        $this->getTarget()->setPosition($this->getOrigin()->add($this->dx * $progress, $this->dy * $progress, $this->dz * $progress));
        return false;
    }

}