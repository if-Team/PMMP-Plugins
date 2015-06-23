<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:07
 */

namespace chalk\cameraman\movement;

use pocketmine\level\Location;

abstract class Movement {
    /** @var Location */
    private $origin;

    /** @var Location */
    private $destination;

    /**
     * @param Location $origin
     * @param Location $destination
     */
    public function __construct(Location $origin, Location $destination){
        $this->origin = $origin;
        $this->destination = $destination;
    }

    /**
     * @return Location
     */
    public function getOrigin(){
        return $this->origin;
    }

    /**
     * @return Location
     */
    public function getDestination(){
        return $this->destination;
    }

    public function __toString(){
        return "Movement(" . $this->getOrigin() . " -> " . $this->getDestination() . ")";
    }

    /**
     * @param number $slowness
     * @return Location|null
     */
    public abstract function tick($slowness);
}