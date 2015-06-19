<?php

namespace ifteam\ZombieInfection;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;

class ZombieInfection extends PluginBase implements Listener {
	public $messages, $skin;
	public $m_version = 1; // 메시지 버전 변수
	public $infectionData = [ 
			"infectionStarted" => false 
	];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->initSkins ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new InfectionScheduleTask ( $this ), 80 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function infectionSchedule() {
		$isNight = $this->isNight ( $this->getServer ()->getDefaultLevel ()->getTime () );
		if ($this->infectionData ["infectionStarted"]) {
			if (count ( $this->getServer ()->getOnlinePlayers () ) < 4)
				$this->infectionStop ();
			else if (! $isNight)
				$this->infectionStop ();
		} else {
			if (count ( $this->getServer ()->getOnlinePlayers () ) < 4)
				return;
			if ($isNight)
				$this->infectionStart ();
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if (! $event instanceof EntityDamageByEntityEvent)
			return;
		// TODO 생존자가 데미지를 받을 경우 좀비로 변경시킴 (+스킨변경)
		// TODO 모든생존자가 감염됬을 경우 게임오버
		// TODO 감염됬을경우 감염브리핑 '~님이 감염되었습니다'
	}
	public function onDeath(PlayerDeathEvent $event) {
		// TODO 좀비가 사망할 경우 생존자로 변경시킴 (+스킨변경) (숙주포함)
		// TODO 모든좀비가 사망했을 경우 게임오버
		// TODO 모든생존자가 감염됬을 경우 게임오버
		// TODO 감염이 풀렸을 경우 생존브리핑 '~님이 감염이 풀렸습니다'
	}
	public function onQuit(PlayerQuitEvent $event) {
		// 숙주가 나가는 것을 캐치후 새 숙주를 선정
		if (isset ( $this->infectionData ["hostzombie"] )) {
			if ($this->infectionData ["hostzombie"] == strtolower ( $event->getPlayer ()->getName () )) {
				$this->infectedHostSelect ();
			}
		}
		// TODO 좀비또는 생존자일경우 기록을 제거
		if (isset ( $this->skin ["users"] [strtolower ( $event->getPlayer ()->getName () )] )) {
			$event->getPlayer ()->setSkin ( $this->skin ["users"] [strtolower ( $event->getPlayer ()->getName () )] );
			unset ( $this->skin ["users"] [strtolower ( $event->getPlayer ()->getName () )] );
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if ($this->infectionData ["infectionStarted"] == false) {
			$this->message ( $player, $this->get ( "not-yet-start-the-game" ) );
			return true;
		}
		switch (strtolower ( $command )) {
			case $this->get ( "search" ) :
				$this->searchWarp ( $player );
				break;
		}
		return true;
	}
	public function infectionStart() {
		// TODO 카운트다운
		$this->infectionData ["infectionStarted"] = true;
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$this->alert ( $player, $this->get ( "infection-is-started" ) );
			$this->alert ( $player, $this->get ( "becareful-of-zombie" ) );
		}
	}
	public function infectionStop() {
		$this->infectionData ["infectionStarted"] = false;
		// TODO 감염이 완전히 이뤄지지도 않고 모든좀비도 살아있다면
		// TODO 좀비 수와 인간 수를 집계해서 판정승처리
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$this->alert ( $player, $this->get ( "infection-is-stopped" ) );
		}
	}
	public function infectedHostSelect() {
		$this->infectedHostClear ();
		
		$rand = mt_rand ( 0, count ( $this->getServer ()->getOnlinePlayers () ) );
		$host = $this->getServer ()->getOnlinePlayers ()[$rand];
		
		$this->infectionData ["hostzombie"] = strtolower ( $host->getName () );
		$this->skin ["users"] [strtolower ( $host->getName () )] = $host->getSkinData ();
		
		$host->despawnFromAll ();
		$host->setSkin ( $this->skin ["hostzombie"] );
		$host->spawnToAll ();
		
		$host->addEffect ( Effect::getEffect ( Effect::JUMP ) );
		$host->addEffect ( Effect::getEffect ( Effect::SPEED ) );
		$host->addEffect ( Effect::getEffect ( Effect::REGENERATION ) );
		
		$this->alert ( $player, $this->get ( "now-your-host-zombie" ) );
		$this->alert ( $player, $this->get ( "you-need-a-infect-human" ) );
		$this->alert ( $player, $this->get ( "you-can-use-search-warp" ) );
	}
	public function infectedHostClear() {
		if (! isset ( $this->infectionData ["hostzombie"] ))
			return;
		$host = $this->getServer ()->getPlayer ( $this->infectionData ["hostzombie"] );
		if (! $host instanceof Player)
			return;
		if (isset ( $this->skin ["users"] [strtolower ( $this->infectionData ["hostzombie"] )] )) {
			$host->despawnFromAll ();
			$event->getPlayer ()->setSkin ( $this->skin ["users"] [$this->infectionData ["hostzombie"]] );
			$host->spawnToAll ();
			unset ( $this->skin ["users"] [strtolower ( $this->infectionData ["hostzombie"] )] );
		}
		$host->removeAllEffects ();
		unset ( $this->infectionData ["hostzombie"] );
	}
	public function humanWin() {
		// TODO 휴먼윈
	}
	public function zombieWin() {
		// TODO 좀비윈
	}
	public function searchWarp(Player $player) {
		// TODO 사용제한 쿨타임
		
		// TODO 좀비일 경우 생존자에게 워프 (25블럭 멀리에서)
		// TODO 사람일 경우 좀비에게 워프 (25 블럭 멀리에서)
		
		// TODO 안내메시지
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function initSkins() {
		$this->saveResource ( "skin_zombie.dat", false );
		$this->saveResource ( "skin_hostzombie.dat", false );
		$this->skin ["zombie"] = file_get_contents ( $this->getDataFolder () . "skin_zombie.dat" );
		$this->skin ["hostzombie"] = file_get_contents ( $this->getDataFolder () . "skin_hostzombie.dat" );
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function isNight($tick) {
		$totalhour = ($tick / 1000) + 6;
		$totalday = floor ( $totalhour / 24 );
		$nowhour = floor ( (floor ( $totalhour ) - $totalday * 24) );
		if ($nowhour >= 18 or $nowhour < 6) {
			return true;
		} else {
			return false;
		}
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>