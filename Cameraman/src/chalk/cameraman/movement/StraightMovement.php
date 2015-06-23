<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:24
 */

namespace chalk\cameraman\movement;

use chalk\cameraman\Cameraman;
use pocketmine\level\Location;

class StraightMovement extends Movement {
    /** @var Location */
    private $distance;

    /** @var int */
    private $current = 0, $length = 0;

    /**
     * @param Location $origin
     * @param Location $destination
     */
    function __construct(Location $origin, Location $destination){
        parent::__construct($origin, $destination);

        $this->distance = new Location($this->getDestination()->getX() - $this->getOrigin()->getX(), $this->getDestination()->getY() - $this->getOrigin()->getY(), $this->getDestination()->getZ() - $this->getOrigin()->getZ(), $this->getDestination()->getYaw() - $this->getOrigin()->getYaw(), $this->getDestination()->getPitch() - $this->getOrigin()->getPitch());
        $this->length = Cameraman::TICKS_PER_SECOND * max(abs($this->distance->getX()), abs($this->distance->getY()), abs($this->distance->getZ()), abs($this->distance->getYaw()), abs($this->distance->getPitch()));
    }

    /**
     * @param number $slowness
     * @return Location|null
     */
    public function tick($slowness){
        if(($length = $this->length * $slowness) < 0.0000001){
            return null;
        }

        if(($progress = $this->current++ / $length) > 1){
            return null;
        }

        return new Location($this->getOrigin()->getX() + $this->distance->getX() * $progress, 1.62 + $this->getOrigin()->getY() + $this->distance->getY() * $progress, $this->getOrigin()->getZ() + $this->distance->getZ() * $progress, $this->getOrigin()->getYaw() + $this->distance->getYaw() * $progress, $this->getOrigin()->getPitch() + $this->distance->getPitch() * $progress);
    }

}