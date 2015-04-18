<?php

/**
 * @author ChalkPE
 * @since 2015-04-18 20:47
 */

namespace chalk\vipplus;

use pocketmine\Player;
use pocketmine\Server;

class VIP {
    /** @var string */
    private $name;

    /** @var array */
    private $data;

    /** @var Player */
    private $player = null;

    /**
     * @param string $name
     * @param array $data
     */
    public function __construct($name, array $data){
        $this->name = $name;
        $this->data = $data;
    }

    public function __toString(){
        return $this->getName();
    }

    /**
     * @return string
     */
    public function getName(){
        return $this->name;
    }

    /**
     * @return array
     */
    public function getData(){
        return $this->data;
    }

    /**
     * @return null|Player
     */
    public function getPlayer(){
        if($this->player === null){
            foreach(Server::getInstance()->getOnlinePlayers() as $player){
                if($this->getName() === strToLower($player->getName())){
                    $this->player = $player;
                    break;
                }
            }
        }
        return $this->player;
    }
}