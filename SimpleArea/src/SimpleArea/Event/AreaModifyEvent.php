<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace SimpleArea\Event;

use pocketmine\event\Event;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\event\Cancellable;

class AreaModifyEvent extends Event implements Cancellable {
	public static $handlerList = null;
	public static $eventPool = [ ];
	public static $nextEvent = 0;
	private $block, $player, $type;
	/**
	 * Actual Denied Event Type
	 */
	const PLACE_PROTECT_AREA = 1;
	const PLACE_FORBID = 1;
	const PLACE_WHITE = 2;
	const PLACE_WHITE_FORBID = 3;
	// -------------------------------------
	const BREAK_PROTECT_AREA = 4;
	const BREAK_FORBID = 5;
	const BREAK_WHITE = 6;
	const BREAK_WHITE_FORBID = 7;
	public function __construct(Player $player, Block $block, $type) {
		$this->block = $block;
		$this->player = $player;
		$this->type = $type;
	}
	public function getBlock() {
		return $this->block;
	}
	public function getPlayer() {
		return $this->player;
	}
	public function getType() {
		return $this->type;
	}
}

?>