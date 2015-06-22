<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:24
 */

namespace chalk\cameraman\movement;

use chalk\cameraman\Cameraman;
use pocketmine\level\Location;

class StraightMovement extends Movement {
    private $dx, $dy, $dz;
    private $distance, $d = 0;

    /**
     * @param Location $origin
     * @param Location $destination
     */
    function __construct(Location $origin, Location $destination){
        parent::__construct($origin, $destination);

        $this->dx = $this->getDestination()->getX() - $this->getOrigin()->getX();
        $this->dy = $this->getDestination()->getY() - $this->getOrigin()->getY();
        $this->dz = $this->getDestination()->getZ() - $this->getOrigin()->getZ();

        $this->distance = Cameraman::TICKS_PER_SECOND * max(abs($this->dx), abs($this->dy), abs($this->dz));
        if($this->distance === 0){
            throw new \InvalidArgumentException("distance cannot be zero");
        }
    }

    /**
     * @param number $slowness
     * @return Location|boolean
     */
    public function tick($slowness){
        $distance = $this->distance * $slowness;
        if($distance < 0.0000001){
            return false;
        }

        $progress = $this->d++ / $distance;
        if($progress > 1){
            return false;
        }

        return new Location($this->getOrigin()->getX() + $this->dx * $progress, $this->getOrigin()->getY() + $this->dy * $progress, $this->getOrigin()->getZ() + $this->dz * $progress);
    }

}