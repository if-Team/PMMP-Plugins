<?php

namespace hm\SteveWarn\task;

use pocketmine\scheduler\PluginTask;
use hm\SteveWarn\SteveWarn;

class SteveWarn extends PluginTask {
	public $player;
	public function __construct(SteveWarn $owner, Player $player) {
		parent::__construct ( $owner );
		$this->player = $player;
	}
	public function onRun($currentTick) {
		$this->player->kick ( "기본닉네임" );
	}
}

?>