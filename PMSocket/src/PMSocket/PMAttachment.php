<?php

namespace PMSocket;

use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class PMAttachment extends \ThreadedLoggerAttachment implements \LoggerAttachment {
	public function __construct(PMResender $resender){
		$this->resender = $resender;
	}
    public function log($level, $message) {
    	$this->resender->stream($level, $message);
    }
}