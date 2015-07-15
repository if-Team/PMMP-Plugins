<?php

namespace ifteam\Gentleman\task;

use pocketmine\scheduler\AsyncTask;
use ifteam\Gentleman\Gentleman;
use pocketmine\Server;

class GentlemanAsyncTask extends AsyncTask {
	public $name, $format, $message;
	public $badQueue, $dictionary, $dictionaryCheck, $eventType, $find = null;
	public $isFullChat;
	public function __construct($name, $format, $message, &$badQueue, &$dictionary, $eventType, $dictionaryCheck = false, $isFullChat = false) {
		$this->name = $name;
		$this->format = $format;
		$this->message = $message;
		$this->badQueue = $badQueue;
		$this->dictionary = $dictionary;
		$this->dictionaryCheck = $dictionaryCheck;
		$this->eventType = $eventType;
		$this->isFullChat = $isFullChat;
	}
	public function onRun() {
		if ($this->isFullChat) {
			$chat = explode ( ">", $this->message );
			array_shift ( $chat );
			$chat = implode ( $chat );
			if ($chat == null) {
				$this->find = $this->checkSwearWord ( $this->message, $this->dictionaryCheck );
			} else {
				$this->find = $this->checkSwearWord ( $chat, $this->dictionaryCheck );
			}
		} else {
			$this->find = $this->checkSwearWord ( $this->message, $this->dictionaryCheck );
		}
	}
	public function onCompletion(Server $server) {
		$this->badQueue = null;
		$this->dictionary = null;
		$plugin = $server->getPluginManager ()->getPlugin ( "Gentleman" );
		if ($plugin instanceof Gentleman) {
			$plugin->asyncProcess ( $this->name, $this->format, $this->message, $this->find, $this->eventType );
		}
	}
	public function removeDictionaryText($text) {
		foreach ( $this->dictionary ["dictionary"] as $word )
			$text = str_replace ( $word, "", $text );
		return $text;
	}
	public function cutWords($str) {
		$cut_array = [ ];
		for($i = 0; $i < mb_strlen ( $str, "UTF-8" ); $i ++)
			$cut_array [] = mb_substr ( $str, $i, 1, 'UTF-8' );
		return $cut_array;
	}
	public function checkSwearWord($word, $dictionaryCheck = false) {
		if ($dictionaryCheck)
			$word = $this->removeDictionaryText ( $word );
		$word = $this->cutWords ( $word );
		foreach ( $this->badQueue as $queue ) { // 비속어단어별 [바,보]
			$wordLength = count ( $queue );
			$find_count = [ ];
			foreach ( $queue as $match_alpha ) { // 비속어글자별 [바], [보]
				foreach ( $word as $used_alpha ) // 유저글자별 [ 나,는,바,보,다]
					if (strtolower ( $match_alpha ) == strtolower ( $used_alpha )) {
						$find_count [$match_alpha] = 0; // ["바"=>0 "보"=0]
						break;
					}
				if ($wordLength == count ( $find_count ))
					return implode ( "", $queue );
			}
		}
		return null;
	}
}

?>