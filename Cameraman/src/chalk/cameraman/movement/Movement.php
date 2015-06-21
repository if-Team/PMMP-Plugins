<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:07
 */

namespace chalk\cameraman\movement;

use pocketmine\math\Vector3;

abstract class Movement {
    /** @var Vector3 */
    private $origin;

    /** @var Vector3 */
    private $destination;

    /**
     * @param Vector3 $origin
     * @param Vector3 $destination
     */
    public function __construct(Vector3 $origin, Vector3 $destination){
        $this->origin = $origin->round()->add(0.5, 0.5, 0.5);
        $this->destination = $destination->round()->add(0.5, 0.5, 0.5);
    }

    /**
     * @return Vector3
     */
    public function getOrigin(){
        return $this->origin;
    }

    /**
     * @return Vector3
     */
    public function getDestination(){
        return $this->destination;
    }

    public function __toString(){
        return "Movement(" . $this->getOrigin() . " -> " . $this->getDestination() . ")";
    }

    /**
     * @param number $slowness
     * @return Vector3|boolean
     */
    public abstract function tick($slowness);
}