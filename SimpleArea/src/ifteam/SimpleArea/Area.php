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
 * @since 2015-05-10 14:57
 */

namespace ifteam\SimpleArea;

use chalk\utils\Arrayable;
use pocketmine\math\Vector2;

class Area implements Arrayable {
    /** @var int */
    private $id = -1;

    /** @var Vector2 */
    private $start, $end;

    /** @var string[] */
    private $residents = [];

    /** @var array */
    private $options = [];

    /**
     * @param $id
     * @param Vector2 $start
     * @param Vector2 $end
     * @param array $residents
     * @param $options
     */
    public function __construct($id, Vector2 $start, Vector2 $end, $residents = [], $options){
        $this->id = $id;

        $this->start = $start;
        $this->end = $end;

        $this->residents = $residents;
        $this->options = $options;
    }

    /**
     * @param int $index
     * @param array $array
     * @return Area
     */
    public static function createFromArray($index, $array){
        return new Area($index,
            new Vector2($array["start"][0], $array["start"][1]),
            new Vector2($array["end"][0], $array["end"][1]),
            $array["residents"],
            $array["options"]
        );
    }

    /**
     * @return array
     */
    public function toArray(){
        return [
            "start" => [$this->getStart()->getX(), $this->getStart()->getY()],
            "end" => [$this->getEnd()->getX(), $this->getEnd()->getY()],
            "residents" => $this->getResidents(),
            "options" => $this->getOptions()
        ];
    }

    /**
     * @return int
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return Vector2
     */
    public function getStart(){
        return $this->start;
    }

    /**
     * @return Vector2
     */
    public function getEnd(){
        return $this->end;
    }

    /**
     * @return string[]
     */
    public function getResidents(){
        return $this->residents;
    }

    /**
     * @return array
     */
    public function getOptions(){
        return $this->options;
    }
}