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
 * @since 2015-05-02 11:10
 * @copyright Apache-v2.0
 */

namespace chalk\clannish\clan;

class Clan {
    /** @var string */
    private $name = "";

    /** @var string */
    private $leader = "";

    /** @var string[] */
    private $members = [];

    /**
     * @param string $name
     * @param string $leader
     * @param string[] $members
     */
    public function __construct($name, $leader, $members = []){
        $this->name = $name;
        $this->leader = $leader;
        $this->members = $members;
    }

    /**
     * @return string
     */
    public function getName(){
        return $this->name;
    }

    /**
     * @return string
     */
    public function getLeader(){
        return $this->leader;
    }

    /**
     * @return \string[]
     */
    public function getMembers(){
        return $this->members;
    }

    /**
     * @return array
     */
    public function toArray(){
        return ["name" => $this->getName(), "leader" => $this->getLeader(), "members" => $this->getMembers()];
    }
}