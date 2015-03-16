<?php

namespace CreativeEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\block\Block;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\BlockUpdateEvent;

class CreativeEconomy extends PluginBase implements Listener {
	private static $instance = null;
	public $messages, $db;
	public $marketCount, $marketPrice, $itemName;
	public $economyAPI = null;
	public $purchaseQueue = [ ]; // 상점결제 큐
	public $createQueue = [ ]; // 상점제작시 POS백업큐
	public $autoCreateQueue = [ ]; // 자동 상점제작시 POS백업큐
	public $packetQueue = [ ]; // 아이템 패킷 큐
	public $packet = [ ]; // 전역 패킷 변수
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		$this->saveResource ( "marketPrice.yml", false );
		$this->saveResource ( "marketCount.yml", false );
		$this->saveResource ( $this->messages ["default-language"] . "_item_data.yml", false );
		
		$this->db = (new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML, [ 
				"allow-purchase" => true ] ))->getAll ();
		$this->marketCount = (new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML ))->getAll ();
		$this->marketPrice = (new Config ( $this->getDataFolder () . "marketPrice.yml", Config::YAML ))->getAll ();
		$this->itemName = (new Config ( $this->getDataFolder () . $this->messages ["default-language"] . "_item_data.yml", Config::YAML ))->getAll ();
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->registerCommand ( $this->get ( "commands-buy" ), "ce", "creativeeconomy.commands.buy", $this->get ( "commands-buy-usage" ) );
		$this->registerCommand ( $this->get ( "commands-sell" ), "ce", "creativeeconomy.commands.sell", $this->get ( "commands-sell-usage" ) );
		$this->registerCommand ( $this->get ( "commands-ce" ), "ce", "creativeeconomy.commands.ce", $this->get ( "commands-ce-usage" ) );
		
		if (self::$instance == null) self::$instance = $this;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		$this->packet ["AddItemEntityPacket"] = new AddItemEntityPacket ();
		$this->packet ["AddItemEntityPacket"]->yaw = 0;
		$this->packet ["AddItemEntityPacket"]->pitch = 0;
		$this->packet ["AddItemEntityPacket"]->roll = 0;
		
		$this->packet ["RemoveEntityPacket"] = new RemoveEntityPacket ();
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"CreativeEconomy" ] ), 40 );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
		
		$save = new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML );
		$save->setAll ( $this->marketCount );
		$save->save ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function BlockChange(BlockUpdateEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"] )) $event->setCancelled ();
	}
	public function onTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"] )) {
			if (! $player->hasPermission ( "creativeeconomy.shop.use" )) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			if ($this->db ["allow-purchase"] == false) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			if (isset ( $this->marketPrice [$this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"]] )) {
				$price = $this->marketPrice [$this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"]];
			} else {
				$this->alert ( $player, $this->get ( "not-found-item-data" ) );
				return;
			}
			if ($price == 0) {
				$this->alert ( $player, $this->get ( "this-item-doesnt-sell" ) );
				return;
			}
			$this->purchaseQueue [$player->getName ()] ["id"] = $this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"];
			$this->message ( $player, "' " . $this->itemName [$this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"]] . " '" . $this->get ( "you-can-buy-or-sell" ) );
			$this->message ( $player, $this->get ( "buy-or-sell-help-command" ) . " ( " . $this->get ( "price" ) . " : " . $price . " )" );
			$event->setCancelled ();
		}
		if (isset ( $this->createQueue [$player->getName ()] )) {
			$event->setCancelled ();
			$pos = $block->getSide ( 1 );
			$item = $this->createQueue [$player->getName ()];
			$this->db ["signMarket"] ["{$pos->x}.{$pos->y}.{$pos->z}"] = $item;
			$block->getLevel ()->setBlock ( $pos, Block::get ( Item::GLASS ), true );
			unset ( $this->createQueue [$player->getName ()] );
			$this->message ( $player, $this->get ( "market-completely-created" ) );
		}
		if (isset ( $this->autoCreateQueue [$player->getName ()] ) and $this->autoCreateQueue [$player->getName ()] ["pos1"] === null) {
			$event->setCancelled ();
			$this->autoCreateQueue [$player->getName ()] ["pos1"] = $block->getSide ( 1 );
			// POS1 지정완료
			$this->message ( $player, $this->get ( "pos1-is-selected" ) );
			return;
		}
		if (isset ( $this->autoCreateQueue [$player->getName ()] ) and $this->autoCreateQueue [$player->getName ()] ["pos2"] === null) {
			$event->setCancelled ();
			$this->autoCreateQueue [$player->getName ()] ["pos2"] = $block->getSide ( 1 );
			// POS2 지정완료
			$this->message ( $player, $this->get ( "pos2-is-selected" ) );
			$this->message ( $player, $this->get ( "are-you-want-continue" ) );
			$this->message ( $player, $this->get ( "you-can-possible-to-cancel" ) );
			return;
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"] )) {
			if (! $player->hasPermission ( "creativeeconomy.shop.break" )) {
				$this->alert ( $player, $this->get ( "market-cannot-break" ) );
				$event->setCancelled ();
			}
			if (isset ( $this->marketCount [$this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"]] )) {
				if ($this->marketCount [$this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"]] > 0) {
					$this->marketCount [$this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"]] --;
				}
			}
			unset ( $this->db ["signMarket"] ["{$block->x}.{$block->y}.{$block->z}"] );
			$this->packet ["RemoveEntityPacket"]->eid = $this->packetQueue [$player->getName ()] ["{$block->x}.{$block->y}.{$block->z}"];
			$player->dataPacket ( $this->packet ["RemoveEntityPacket"] ); // 제거패킷 전송
			$this->message ( $player, $this->get ( "market-completely-destroyed" ) );
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->purchaseQueue [$event->getPlayer ()->getName ()] )) unset ( $this->purchaseQueue [$event->getPlayer ()->getName ()] );
		if (isset ( $this->createQueue [$event->getPlayer ()->getName ()] )) unset ( $this->createQueue [$event->getPlayer ()->getName ()] );
		if (isset ( $this->autoCreateQueue [$event->getPlayer ()->getName ()] )) unset ( $this->autoCreateQueue [$event->getPlayer ()->getName ()] );
		if (isset ( $this->packetQueue [$event->getPlayer ()->getName ()] )) unset ( $this->packetQueue [$event->getPlayer ()->getName ()] );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! $player instanceof Player) {
			$this->alert ( $player, $this->get ( "only-in-game" ) );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-buy" ) :
				(isset ( $args [0] )) ? $this->CEBuyCommand ( $player, $args [0] ) : $this->CEBuyCommand ( $player );
				break;
			case $this->get ( "commands-sell" ) :
				(isset ( $args [0] )) ? $this->CESellCommand ( $player, $args [0] ) : $this->CESellCommand ( $player );
				break;
			case $this->get ( "commands-ce" ) :
				if (! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "commands-ce-help1" ) );
					$this->message ( $player, $this->get ( "commands-ce-help2" ) );
					$this->message ( $player, $this->get ( "commands-ce-help3" ) );
					$this->message ( $player, $this->get ( "commands-ce-help4" ) );
					break;
				}
				switch ($args [0]) {
					case $this->get ( "sub-commands-create" ) :
						(isset ( $args [1] )) ? $this->CECreateQueue ( $player, $args [1] ) : $this->CECreateQueue ( $player );
						break;
					case $this->get ( "sub-commands-autocreate" ) :
						$this->CEAutoSet ( $player );
						break;
					case $this->get ( "sub-commands-change" ) :
						(isset ( $args [1] ) and isset ( $args [2] )) ? $this->ChangeMarketPrice ( $player, $args [1], $args [2] ) : $this->ChangeMarketPrice ( $player );
						break;
					case $this->get ( "sub-commands-cancel" ) :
						// ( 모든 큐를 초기화 )
						if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
						if (isset ( $this->createQueue [$player->getName ()] )) unset ( $this->createQueue [$player->getName ()] );
						if (isset ( $this->autoCreateQueue [$player->getName ()] )) unset ( $this->autoCreateQueue [$player->getName ()] );
						
						$this->message ( $player, $this->get ( "all-processing-is-stopped" ) );
						break;
					case $this->get ( "sub-commands-lock" ) :
						$this->AllFreezeMarket ( $player );
						break;
					default :
						$this->message ( $player, $this->get ( "commands-ce-help1" ) );
						$this->message ( $player, $this->get ( "commands-ce-help2" ) );
						$this->message ( $player, $this->get ( "commands-ce-help3" ) );
						$this->message ( $player, $this->get ( "commands-ce-help4" ) );
						break;
				}
				break;
		}
		return true;
	}
	public function CEBuyCommand(Player $player, $count = 1, $item = null) {
		if ($this->db ["allow-purchase"] == false) {
			$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
			return;
		}
		if (! isset ( $this->purchaseQueue [$player->getName ()] ) and $item == null) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		} else {
			if ($item == null) $item = $this->purchaseQueue [$player->getName ()] ["id"];
			if (! isset ( $this->marketPrice [$item] )) {
				$this->alert ( $player, $this->get ( "not-found-item-data" ) );
				return;
			}
			$check = explode ( ".", $item );
			// 가격시세가 있는지 체크, 없다면 데미지0번 시세가 있는지 체크후 있으면 따르고 없으면 리턴
			if (isset ( $this->marketPrice [$item] )) {
				$price = $this->marketPrice [$item] * $count;
			} else {
				if (isset ( $this->marketPrice [$check [0] . ".0"] )) {
					$price = $this->marketPrice [$check [0] . ".0"] * $count;
				} else {
					$this->alert ( $player, $this->get ( "not-found-item-data" ) );
					return;
				}
			}
			if (! is_numeric ( $count ) and ! isset ( $check [1] )) {
				$this->alert ( $player, $this->get ( "buy-or-sell-help-command" ) );
				return;
			}
			if (! isset ( $check [1] )) $check [1] = 0; // 데미지값이 없으면 0으로
			$money = $this->economyAPI->myMoney ( $player );
			if ($money < $price) {
				$this->alert ( $player, $this->get ( "not-enough-money-to-purchase" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
				return;
			}
			$this->economyAPI->reduceMoney ( $player, $price );
			$player->getInventory ()->addItem ( Item::get ( $check [0], $check [1], $count ) );
			(! isset ( $this->itemName [$item] )) ? $itemName = "undefied" : $itemName = $this->itemName [$item];
			$this->message ( $player, $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-buyed" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
			if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
			return;
		}
	}
	public function CESellCommand(Player $player, $count = 1, $item = null) {
		if ($this->db ["allow-purchase"] == false) {
			$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
			return;
		}
		if (! isset ( $this->purchaseQueue [$player->getName ()] ) and $item == null) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		} else {
			if ($item == null) $item = $this->purchaseQueue [$player->getName ()] ["id"];
			if (! isset ( $this->marketPrice [$item] )) {
				$this->alert ( $player, $this->get ( "not-found-item-data" ) );
				return;
			}
			$check = explode ( ".", $item );
			// 가격시세가 있는지 체크, 없다면 데미지0번 시세가 있는지 체크후 있으면 따르고 없으면 리턴
			if (isset ( $this->marketPrice [$item] )) {
				$price = $this->marketPrice [$item] * $count;
			} else {
				if (isset ( $this->marketPrice [$check [0] . ".0"] )) {
					$price = $this->marketPrice [$check [0] . ".0"] * $count;
				} else {
					$this->alert ( $player, $this->get ( "not-found-item-data" ) );
					return;
				}
			}
			if (! is_numeric ( $count ) and ! isset ( $check [1] )) {
				$this->alert ( $player, $this->get ( "buy-or-sell-help-command" ) );
				return;
			}
			if (! isset ( $check [1] )) $check [1] = 0; // 데미지값이 없으면 0으로
			$haveItem = 0;
			foreach ( $player->getInventory ()->getContents () as $inven ) {
				if (! $inven instanceof Item) return;
				if ($inven->getID () == $check [0] and $inven->getDamage () == $check [1]) {
					$haveItem = $inven->getCount (); // 가진아이템 수 확인
				}
			}
			if ($haveItem < $count) {
				$this->alert ( $player, $this->get ( "not-enough-item" ) );
				return;
			}
			$this->economyAPI->addMoney ( $player, $price );
			$money = $this->economyAPI->myMoney ( $player );
			$player->getInventory ()->removeItem ( Item::get ( $check [0], $check [1], $count ) );
			(! isset ( $this->itemName [$item] )) ? $itemName = "undefied" : $itemName = $this->itemName [$item];
			$this->message ( $player, $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-selled" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
			if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
			return;
		}
	}
	public function CECreateQueue(Player $player, $item = null) {
		if ($item == null or ! is_numeric ( $item )) {
			$this->alert ( $player, $this->get ( "commands-ce-help1" ) );
			return;
		}
		$this->message ( $player, $this->get ( "which-you-want-place-choose-pos" ) );
		$this->createQueue [$player->getName ()] = $item;
	}
	public function CreativeEconomy() {
		// 스케쥴로 매번 위치확인하면서 생성작업시작
		if (! isset ( $this->db ["signMarket"] )) return;
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			foreach ( $this->db ["signMarket"] as $marketPos => $item ) {
				$explode = explode ( ".", $marketPos );
				if (! isset ( $explode [2] )) return; // 좌표가 아닐경우 리턴
				$dx = abs ( $explode [0] - $player->x );
				$dy = abs ( $explode [1] - $player->y );
				$dz = abs ( $explode [2] - $player->z ); // XYZ 좌표차이 계산
				                                         
				// 반경 25블럭을 넘거갔을경우 생성해제 패킷 전송후 생성패킷큐를 제거
				if (! ($dx <= 25 and $dy <= 25 and $dz <= 25)) {
					if (! isset ( $this->packetQueue [$player->getName ()] [$marketPos] )) return;
					
					$this->packet ["RemoveEntityPacket"]->eid = $this->packetQueue [$player->getName ()] [$marketPos];
					$player->dataPacket ( $this->packet ["RemoveEntityPacket"] ); // 제거패킷 전송
					unset ( $this->packetQueue [$player->getName ()] [$marketPos] );
					continue;
				} else {
					if (isset ( $this->packetQueue [$player->getName ()] [$marketPos] )) continue;
					
					$itemCheck = explode ( ".", $item );
					if (isset ( $item [1] )) {
						$item = Item::get ( $itemCheck [0], $itemCheck [1] );
					} else {
						$item = Item::get ( $item );
					}
					// 반경 25블럭 내일경우 생성패킷 전송 후 생성패킷큐에 추가
					$this->packetQueue [$player->getName ()] [$marketPos] = Entity::$entityCount ++;
					$this->packet ["AddItemEntityPacket"]->eid = $this->packetQueue [$player->getName ()] [$marketPos];
					$this->packet ["AddItemEntityPacket"]->item = $item;
					$this->packet ["AddItemEntityPacket"]->x = $explode [0] + 0.5;
					$this->packet ["AddItemEntityPacket"]->y = $explode [1];
					$this->packet ["AddItemEntityPacket"]->z = $explode [2] + 0.5;
					$player->dataPacket ( $this->packet ["AddItemEntityPacket"] );
				}
			}
		}
	}
	public function CEAutoSet(Player $player) {
		// 1줄 전자동 상점설치
		if (! isset ( $this->autoCreateQueue [$player->getName ()] )) {
			$this->autoCreateQueue [$player->getName ()] ["pos1"] = null;
			$this->autoCreateQueue [$player->getName ()] ["pos2"] = null;
			$this->message ( $player, $this->get ( "which-you-want-place-choose-pos" ) );
		} else {
			// 포스 1&2 지정되지 않았으면 리턴
			if ($this->autoCreateQueue [$player->getName ()] ["pos1"] == null) {
				$this->alert ( $player, $this->get ( "please-select-pos1" ) );
				return;
			}
			if ($this->autoCreateQueue [$player->getName ()] ["pos2"] == null) {
				$this->alert ( $player, $this->get ( "please-select-pos2" ) );
				return;
			}
			// POS 지정 완료 후 전자동 생성시작
			$pos1 = $this->autoCreateQueue [$player->getName ()] ["pos1"];
			$pos2 = $this->autoCreateQueue [$player->getName ()] ["pos2"];
			
			// 한줄이 아닐경우 리턴
			$dx = $pos1->x - $pos2->x;
			$dy = $pos1->y - $pos2->y;
			$dz = $pos1->z - $pos2->z;
			if (($dx != 0 and $dz != 0) or $dy != 0) {
				$this->alert ( $player, $this->get ( "must-be-a-one-line" ) );
				unset ( $this->autoCreateQueue [$player->getName ()] );
				return;
			}
			
			// 대상 어레이 정리 부분
			// 한줄이 아닌 값을 X Z중에서 찾아 선택
			// 한줄이 아닌 값 중에서 가장작은값을 소트
			if ($dz == 0) {
				// x가 대상일때
				if ($pos1->x > $pos2->x) {
					$max = $pos1->x;
					$min = $pos2->x;
				} else {
					$max = $pos2->x;
					$min = $pos1->x;
				}
			} else {
				// z가 대상일때
				if ($pos1->z > $pos2->z) {
					$max = $pos1->z;
					$min = $pos2->z;
				} else {
					$max = $pos2->z;
					$min = $pos1->z;
				}
			}
			
			// 마켓카운트에서 존재하지않는 마켓 불러오기
			foreach ( $this->marketCount as $item => $marketCount ) {
				if ($marketCount != 0) continue;
				if ($dz == 0) {
					// x가 대상일때
					$pos = new Position ( $min, $pos1->y, $pos1->z, $player->level );
				} else {
					// z가 대상일때
					$pos = new Position ( $pos1->x, $pos1->y, $min, $player->level );
				}
				$this->db ["signMarket"] ["{$pos->x}.{$pos->y}.{$pos->z}"] = $item;
				$player->level->setBlock ( $pos, Block::get ( Item::GLASS ), true );
				$this->marketCount [$item] ++;
				// 각 부분마다 위 주석코드 실행
				if (++ $min > $max) {
					unset ( $this->autoCreateQueue [$player->getName ()] );
					$this->message ( $player, $this->get ( "market-completely-created" ) );
					return; // 모든 블럭에 배치가 끝나면 리턴
				}
			}
		}
	}
	public function ChangeMarketPrice(Player $player, $item = null, $price = null) {
		// 기본가격시세를 입력된 값으로 설정
		if ($item == null or ! is_numeric ( $item )) {
			$this->alert ( $player, $this->get ( "commands-ce-help3" ) );
			return;
		}
		if ($price == null or ! is_numeric ( $price )) {
			$this->alert ( $player, $this->get ( "commands-ce-help3" ) );
			return;
		}
		if (! isset ( $this->marketPrice [$item] )) {
			$this->alert ( $player, $this->get ( "not-found-item-data" ) );
			return;
		}
		$oldPrice = $this->marketPrice [$item];
		$this->marketPrice [$item] = $price;
		$this->message ( $player, $this->get ( "successfully-changed-price" ) );
		$this->message ( $player, "( " . $this->get ( "old-price" ) . " : " . $oldPrice . " / " . $this->get ( "new-price" ) . " : " . $price . " )" );
		
		$save = new Config ( $this->getDataFolder () . "marketPrice.yml", Config::YAML );
		$save->setAll ( $this->marketPrice );
		$save->save ();
	}
	public function AllFreezeMarket(Player $player) {
		if ($this->db ["allow-purchase"] == true) {
			$this->db ["allow-purchase"] = false;
			$this->message ( $player, $this->get ( "market-disabled" ) );
		} else {
			$this->db ["allow-purchase"] = true;
			$this->message ( $player, $this->get ( "market-enabled" ) );
		}
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>