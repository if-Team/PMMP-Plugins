<?php

/**
 * @author ChalkPE
 * @since 2015-04-18 18:44
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