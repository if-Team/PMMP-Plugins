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

    /** @var boolean */
    private $moving = false;

    /**
     * @param Vector3 $origin
     */
    public function __construct(Vector3 $origin){
        $this->origin = $origin;
    }

    /**
     * @return Vector3
     */
    public function getOrigin(){
        return $this->$origin;
    }

    /**
     * @return boolean
     */
    public function isMoving(){
        return $this->moving;
    }

    /**
     * @param number $slowness
     * @return Vector3|null
     */
    public abstract function tick($slowness);
}