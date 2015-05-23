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
 * @since 2015-05-23 12:17
 * @copyright Apache-v2.0
 */
namespace chalk\clannish\command\activity;

use chalk\clannish\Clannish;
use chalk\clannish\ClannishCommand;

abstract class ActivityCommand extends ClannishCommand {
    /**
     * @param Clannish $plugin
     * @param string $name
     * @param string $description
     * @param string $usage
     */
    public function __construct(Clannish $plugin, $name, $description = "", $usage = ""){
        parent::__construct($plugin, $name, $description, $usage, "clannish.activity");
    }
}