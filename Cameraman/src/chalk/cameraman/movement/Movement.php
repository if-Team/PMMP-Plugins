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
     * @param number $slowness
     * @return Vector3|boolean
     */
    public abstract function tick($slowness);
}