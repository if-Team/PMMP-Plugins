<?php

namespace SimpleArea\Task;

use pocketmine\scheduler\PluginTask;
use SimpleArea\SimpleArea;

class RentTimeOutTask extends PluginTask {
	public $homeOwner, $homeBuyer;
	public function __construct(SimpleArea $owner, $homeOwner, $homeBuyer) {
		parent::__construct ( $owner );
		$this->homeOwner = $homeOwner;
		$this->homeBuyer = $homeBuyer;
	}
	public function onRun($currentTick) {
		$this->owner->rentTimeout ( $this->homeOwner, $this->homeBuyer );
	}
}

?>