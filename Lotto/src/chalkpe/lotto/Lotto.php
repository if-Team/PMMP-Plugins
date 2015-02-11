<?php

namespace chalkpe\lotto;

use onebone\economyapi\EconomyAPI;

use pocketmine\block\Block;
use pocketmine\block\SignPost;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;

class Lotto extends PluginBase implements Listener {
	const tag = "[Lotto]";
	const axisToken = ",";
	const interval = 288; //5min * 288 = 1days

	/** @var EconomyAPI */
	private $economyAPI;

	private $price;
	private $shops;

	private $lastWinner;
	private $lastWinningAmount;

	/** @var Player */
	private $lastInteractedPlayer = null;

	/** @var int */
	private $tick = self::interval;

	/** @var array */
	private $box = [];

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->economyAPI = EconomyAPI::getInstance();

		$this->load();

		$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new CallbackTask([$this, "onTick"], []), 6000, 6000); //5min
		$this->getLogger()->notice("plugin loaded - price: " . $this->price);
	}

	public function onDisable(){
		$this->save();
	}

	public function load(){
		$this->saveDefaultConfig();

		$config = $this->getConfig();
		$this->price             = $config->get("price");
		$this->lastWinner        = $config->get("lastWinner");
		$this->lastWinningAmount = $config->get("lastWinningAmount");
		$this->shops             = $config->get("shops");
	}

	private function save(){
		$config = $this->getConfig();
		$config->set("lastWinner",        $this->lastWinner);
		$config->set("lastWinningAmount", $this->lastWinningAmount);
		$config->set("shops",             $this->shops);
		$config->save();
	}

	public function onTick(){
		if(--$this->tick > 0) {
			if($this->tick % 6 === 0){
				$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . self::tag . $this->getTimeText() . "lefts to result announcement!");
			}
		}else{
			$count = count($this->box);
			if($count > 0){
				$winner = $this->box[array_rand($this->box)];
				$winningAmount = $count * $this->price;

				$this->economyAPI->addMoney($winner, $winningAmount);

				$winnerInst = $this->getServer()->getPlayer($winner);
				if($winnerInst !== null){
					$winnerInst->sendMessage(TextFormat::GREEN . self::tag . " Congratulations! You've won the lottery!");
					$winnerInst->sendMessage(TextFormat::GREEN . self::tag . " Please check your money :)");
				}

				$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . self::tag . " The winner is " . $winner . "! Congratulations!");
				$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . self::tag . " Today's winning amount: ". $winningAmount);
			}

			$this->save();

			$this->box = [];
			$this->tick = self::interval;
		}
	}

	public function onSignChange(SignChangeEvent $event){
		if(strToLower($event->getLine(0)) !== strToLower(self::tag)){
			return;
		}

		if($event->getPlayer()->isOp() === false){
			$event->getPlayer()->sendMessage(TextFormat::DARK_RED . self::tag . " You don't have permission to create a shop!");
			$event->setCancelled(true);
			return;
		}

		$key = $this->getAxisKey($event->getBlock(), self::axisToken);
		if(isSet($this->shops[$key])){
			$event->getPlayer()->sendMessage(TextFormat::DARK_RED . self::tag . " Cannot create shop - Here are already shop exists!");
			$event->setCancelled(true);
			return;
		}

		$event->setLine(0, self::tag);
		$event->setLine(1, "");
		$event->setLine(2, "");
		$event->setLine(3, "");

		$this->shops[$key] = $event->getPlayer()->getName();
		$event->getPlayer()->sendMessage(TextFormat::DARK_AQUA . self::tag . " Shop created!");
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$key = $this->getAxisKey($event->getBlock(), self::axisToken);
		if(isSet($this->shops[$key]) === false){
			return;
		}

		$this->economyAPI = EconomyAPI::getInstance();

		$event->setCancelled(true);
		if($event->getItem()->isPlaceable()) {
			$this->lastInteractedPlayer = $event->getPlayer();
		}

		if($event->getBlock() instanceof SignPost === false){
			unset($this->shops[$key]);
			$event->getPlayer()->sendMessage(TextFormat::DARK_RED . self::tag . " Shop has been removed - Shop must be a sign block!");
			return;
		}

		$player = $event->getPlayer();

		if($this->economyAPI->myMoney($player) < $this->price){
			$event->getPlayer()->sendMessage(TextFormat::DARK_RED . self::tag . "You don't have enough money to buy!");
			return;
		}

		$this->economyAPI->reduceMoney($player, $this->price);
		array_push($this->box, $player->getName());

		$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . self::tag . " " . $player->getName() . " bought a lotto ticket!");

		if(count($this->box) == 101){
			$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . self::tag . "The winning amount exceeded " . ($this->price * 100) . " - Hurry to buy a lotto ticket!");
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		if($this->lastInteractedPlayer !== null && $event->getPlayer()->getName() === $this->lastInteractedPlayer->getName()){
			$event->setCancelled(true);
			$this->lastInteractedPlayer = null;
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$key = $this->getAxisKey($event->getBlock(), self::axisToken);
		if(isSet($this->shops[$key]) === false){
			return;
		}

		if($event->getPlayer()->isOp() === false){
			$event->getPlayer()->sendMessage(TextFormat::DARK_RED . self::tag . " You don't have permission to remove a shop!");
			$event->setCancelled(true);
			return;
		}

		unset($this->shops[$key]);
		$event->getPlayer()->sendMessage(TextFormat::DARK_AQUA . self::tag . " Shop has been removed!");
	}

	public function onCommandSend(CommandSender $sender, Command $command, $string, array $args){
		$sender->sendMessage(TextFormat::DARK_AQUA . self::tag . " Last winner: " . $this->lastWinner);
		$sender->sendMessage(TextFormat::DARK_AQUA . self::tag . " Last winning amount: " . $this->lastWinningAmount);
		$sender->sendMessage(TextFormat::DARK_AQUA . self::tag . " Current winning amount: " . (count($this->box) * $this->price));
		$sender->sendMessage(TextFormat::DARK_AQUA . self::tag . " Now " . $this->getTimeText() . " lefts");
	}

	/**
	 * @param Block $block
	 * @param string $token
	 * @return string
	 */
	public function getAxisKey(Block $block, $token){
		return $block->getX() . $token . $block->getY() . $token . $block->getZ();
	}

	/**
	 * @return string
	 */
	public function getTimeText(){
		$totalMinutes = $this->tick * 5;

		$hours = floor($totalMinutes / 60);
		$minutes = $totalMinutes - $hours;

		return $hours . " hour(s) " . $minutes . " minute(s)";
	}
}