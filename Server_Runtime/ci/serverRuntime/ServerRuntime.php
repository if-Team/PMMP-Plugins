<?php

namespace ci\serverRuntime;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;

class ServerRuntime extends PluginBase implements Listener {
	static $tag = "[Server]";
	
	/** @var int */
	private $time = 0;
	
	$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "threadTick"], []), 20);
	private function threadTick() {
		$this->time++;
		$sec = $this->time;
		$min = 0;
		$hour = 0;
		$day = 0;
		while($sec >= 60) {
			$sec -= 60;
		}
		if($sec === 0) {
			$this->getServer()->broadcastMessage(TextFormat::GRAY . self::tag . "Server run timer: " . $this->getTime());
		}
	}
	private function getTime() {
		$sec = $this->time;
		$min = 0;
		$hour = 0;
		$day = 0;
		$hourB = false;
		while($sec >= 60) {
			$sec -= 60;
			$min++;
		}
		while($min >= 60) {
			$min -= 60;
			$hour++;
			$hourB = true;
		}
		while($hour >= 24) {
			$sec -= 24;
			$day++;
		}
		if($sec === 0) {
			if($day === 0) {
				 return $hour . "hour " . $min . "min";
			}else {
				 return $day . "day " . $hour . "hour " . $min . "min";
			}
		}else {
			if($day === 0) {
				return $hour . "hour " . $min . "min " . $sec . "sec";
			}else {
				return $day . "day " . $hour . "hour " . $min . "min " . $sec . "sec";
			}
		}
	}
}
?>