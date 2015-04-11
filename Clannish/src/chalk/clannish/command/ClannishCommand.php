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
 * @since 2015-04-08 19:06
 * @copyright Apache-v2.0
 */
namespace chalk\clannish;

use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;

abstract class ClannishCommand extends Command implements PluginIdentifiableCommand {
    /** @var Clannish */
    private $plugin = null;

    /**
     * @param Clannish $plugin
     * @param array $resources
     */
    public function __construct(Clannish $plugin, $resources){
        parent::__construct($resources["command"], $resources["description"]);
        $this->setPermission($resources["permission"]);
        $this->plugin = $plugin;
    }

    /**
     * @return Clannish
     */
    public function getPlugin(){
        return $this->plugin;
    }
}