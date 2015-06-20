<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:07
 */

namespace chalk\cameraman\movement;

use pocketmine\entity\Entity;
use pocketmine\level\Position;

abstract class Movement {
    /** @var Entity */
    private $target;

    /** @var Position */
    private $origin;

    /** @var boolean */
    private $moving = false;

    /**
     * @param Entity $target
     * @param Position $origin
     */
    public function __construct(Entity $target, Position $origin = null){
        $this->target = $target;
        $this->origin = ($origin === null) ? $target->getPosition() : $origin;
    }

    /**
     * @return Entity
     */
    public function getTarget(){
        return $this->target;
    }

    /**
     * @return Position
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
     * @return boolean
     */
    public abstract function tick();
}