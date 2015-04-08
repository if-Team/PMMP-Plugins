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
 * @since 2015-04-07 21:53
 * @copyright Apache-v2.0
 */
namespace chalk\clannish;

use pocketmine\plugin\PluginBase;

class Clannish extends PluginBase implements Listener {
    /** @var Config */
    private $data = null;
    
    public function onEnable(){
        @mkdir($this->getDataFolder());
        
        $dataConfig = new Config($this->getDataFolder() . "data.json", Config::JSON);
        $dataConfig->save();
        
        $this->data = $dataConfig->getAll();
    }
    
    public function onPlayerChat(PlayerChatEvent $event){
        $sender = $event->getPlayer();
        if(!$sender->hasPermission("Clannish")){
            return;
        }
        
        $key = strToLower($sender->getName());
        if(isset($this->data[$key]) and $this->data[$key]["room"] !== "main"){
            $event->setCancelled(true);
            foreach($this->data[$key]["roomMembers"] as $member){
                //TODO: Implements this stuff
            }
        }
    }
}