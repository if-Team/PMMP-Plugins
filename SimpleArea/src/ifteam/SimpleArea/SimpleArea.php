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

namespace ifteam\SimpleArea;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

class SimpleArea extends PluginBase implements Listener {
    /** @var SimpleArea */
    private static $instance = null;

    /** @var World[] */
    private $worlds = [];

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable(){
        $this->loadConfigs();
        $this->loadAreas();
    }

    /**
     * @return SimpleArea
     */
    public static function getInstance(){
        return self::$instance;
    }

    private function loadConfigs(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
    }

    private function loadAreas(){
        foreach($this->getServer()->getLevels() as $level){
            $worldConfig = new Config($this->getServer()->getDataPath() . "worlds/" . $level->getFolderName() . "/areas.json", Config::JSON);
            $this->worlds[] = World::createFromArray($level, $worldConfig->getAll());
        }
    }

    /**
     * @return World[]
     */
    public function getWorlds(){
        return $this->worlds;
    }
}

?>
