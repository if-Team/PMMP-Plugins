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
 * @since 2015-04-18 18:44
 * @copyright Apache-v2.0
 */

namespace chalk\utils;

class Messages {
    /** @var int */
    private $version;

    /** @var string */
    private $defaultLanguage;

    /** @var array */
    private $messages = [];

    /**
     * @param array $config
     */
    public function __construct(array $config){
        $version = $config["default-language"];
        $this->version = (isset($version) and is_int($version)) ? $version : 0;

        $defaultLanguage = $config["default-language"];
        $this->defaultLanguage = (isset($defaultLanguage) and is_string($defaultLanguage)) ? $defaultLanguage : "en";

        $messages = $config["messages"];
        $this->messages = (isset($messages) and is_array($messages)) ? $messages : [];
    }

    /**
     * @return int
     */
    public function getVersion(){
        return $this->version;
    }

    /**
     * @return string
     */
    public function getDefaultLanguage(){
        return $this->defaultLanguage;
    }

    /**
     * @return array
     */
    public function getMessages(){
        return $this->messages;
    }

    /**
     * @param string $key
     * @param string[] $format
     * @param string $language
     * @return null|string
     */
    public function getMessage($key, $format = [], $language = ""){
        if($language === ""){
            $language = $this->getDefaultLanguage();
        }

        $message = $this->getMessages()[$key];
        if(!isset($message)){
            return null;
        }

        $string = $message[$language];
        if(!isset($string) and $language !== $this->getDefaultLanguage()){
            $string = $message[$this->getDefaultLanguage()];
        }

        if(isset($string)){
            foreach($format as $key => $value){
                $string = str_replace("{%" . $key . "}", $value, $string);
            }
            return $string;
        }
        return null;
    }
}