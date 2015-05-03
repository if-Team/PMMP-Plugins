<?php

/*
 * Copyright 2015 ChalkPE
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-05-02 20:27
 * @copyright Apache-v2.0
 */

namespace chalk\clannish\clan;

use chalk\clannish\Clannish;
use chalk\utils\Arrayable;
use pocketmine\Player;
use pocketmine\Server;

class ClanMember implements Arrayable {
    const GRADE_LEADER = "leader";
    const GRADE_CO_LEADER = "co-leader";
    const GRADE_ELDER = "elder";
    const GRADE_MEMBER = "member";

    /** @var string */
    private $name;

    /** @var array */
    private $data;

    /** @var Clan|null */
    private $clan = null;

    /** @var Player|null */
    private $player = null;

    /**
     * @param string $name
     * @param array $data
     * @param Clan $clan
     */
    public function __construct($name, array $data, $clan = null){
        $this->name = $name;
        $this->data = $data;
        $this->clan = $clan;
    }

    /**
     * @return string
     */
    public function __toString(){
        return $this->getName();
    }

    /**
     * @param string $index
     * @param array $array
     * @return ClanMember
     */
    public static function createFromArray($index, $array){
        return new ClanMember($index, $array);
    }

    /**
     * @return array
     */
    public function toArray(){
        return $this->getData();
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
     * @return Clan|null
     */
    public function getClan(){
        return $this->clan;
    }

    /**
     * @return Player|null
     */
    public function getPlayer(){
        if($this->player === null){
            foreach(Server::getInstance()->getOnlinePlayers() as $player){
                if($this->getName() === Clannish::validateName($player->getName())){
                    $this->player = $player;
                    break;
                }
            }
        }
        return $this->player;
    }

    /**
     * @param Clan $clan
     */
    public function setClan($clan){
        if($this->clan === null){
            $this->clan = $clan;
        }
    }

    public function getGrade(){
        return $this->getData()["grade"];
    }

    public function isLeader(){
        return $this->getGrade() === ClanMember::GRADE_LEADER;
    }
}