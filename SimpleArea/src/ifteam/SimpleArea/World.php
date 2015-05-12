<?php

/*
 * Copyright 2014-2015 if(Team);
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
 * @since 2015-05-12 20:39
 */

namespace ifteam\SimpleArea;

use chalk\utils\Arrayable;
use pocketmine\level\Level;

class World implements Arrayable {
    /** @var Level */
    private $level;

    /** @var Area[] */
    private $areas = [];

    /**
     * @param Level $level
     * @param Area[] $areas
     */
    public function __construct($level, $areas){
        $this->level = $level;
        $this->areas = $areas;
    }

    /**
     * @param Level $level
     * @param array $array
     * @return World
     */
    public static function createFromArray($level, $array){
        $areas = [];
        foreach($array["areas"] as $index => $area){
            $areas[] = Area::createFromArray($index, $area);
        }

        return new World($level, $areas);
    }

    /**
     * @return array
     */
    public function toArray(){
        $array = ["areas" => []];
        foreach($this->getAreas() as $area){
            $array["areas"][$area->getId()] = $area->toArray();
        }

        return $array;
    }

    /**
     * @return Level
     */
    public function getLevel(){
        return $this->level;
    }

    /**
     * @return Area[]
     */
    public function getAreas(){
        return $this->areas;
    }
}