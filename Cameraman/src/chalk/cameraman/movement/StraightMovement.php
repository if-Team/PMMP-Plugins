<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:24
 */

namespace chalk\cameraman\movement;

use chalk\cameraman\Cameraman;
use pocketmine\math\Vector3;

class StraightMovement extends Movement {
    /** @var Vector3 */
    private $destination;

    private $dx, $dy, $dz;
    private $distance, $d = 0;

    function __construct(Vector3 $origin, Vector3 $destination){
        parent::__construct($origin);
        $this->destination = $destination;

        $this->dx = $this->getDestination()->getX() - $this->getOrigin()->getX();
        $this->dy = $this->getDestination()->getY() - $this->getOrigin()->getY();
        $this->dz = $this->getDestination()->getZ() - $this->getOrigin()->getZ();

        $this->distance = Cameraman::TICKS_PER_SECOND * max($this->dx, $this->dy, $this->dz);
    }

    /**
     * @return Vector3
     */
    public function getDestination(){
        return $this->destination;
    }

    /**
     * @param number $slowness
     * @return Vector3|boolean
     */
    public function tick($slowness){
        $progress = $this->d++ / ($this->distance * $slowness);
        if($progress > 1){
            return false;
        }

        return $this->getOrigin()->add($this->dx * $progress, $this->dy * $progress, $this->dz * $progress);
    }

}