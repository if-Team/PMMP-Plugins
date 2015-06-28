<?php

namespace ifteam\emailAuth;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerQuitEvent;

// TODO - (연동기능)마스터모드일경우
// TODO - (연동기능)슬레이브모드일경우

// TODO - (연동기능)유저 비밀번호 및 계정정보 (IP사용반환해서 재갱신)
// TODO - (연동기능)이코노미API돈
// TODO - (연동기능)유저인벤토리 데이터

// TODO - (연동기능)타서버에 유저가 이미 접속해있으면 접속차단
// TODO - (연동기능)모든 연동작업은 비동기 스레딩으로 처리
// TODO - (연동기능)마스터에서 슬레이브로 데이터가 올때까지 대기 유저의 행동을 차단
class API_CustomPacketListner implements Listener {
	private $plugin;
	public function __construct(emailAuth $plugin) {
		$this->plugin = $plugin;
		if ($this->plugin->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) != null) {
			$this->plugin->checkCustomPacket = true;
			if ($this->plugin->getConfig ()->get ( "usecustompacket", null ) === null) {
				$this->plugin->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->plugin->get ( "you-can-activate-custompacket" ) );
			}
			$this->plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
		}
	}
	public function serverCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		$sender = $event->getSender ();
		if ($this->plugin->getConfig ()->get ( "usecustompacket", false ) != true)
			return;
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == null) { // 서버모드 선택
			switch (strtolower ( $command )) {
				case "master" : // master
					$this->plugin->getConfig ()->set ( "servermode", $command );
					$this->plugin->message ( $sender, $this->plugin->get ( "master-mode-selected" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-passcode" ) );
					break;
				case "slave" : // slave
					$this->plugin->getConfig ()->set ( "servermode", $command );
					$this->plugin->message ( $sender, $this->plugin->get ( "slave-mode-selected" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-passcode" ) );
					break;
				default :
					$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-mode" ) );
					break;
			}
			$event->setCancelled ();
			return;
		}
		if ($this->plugin->getConfig ()->get ( "passcode", null ) == null) { // 통신보안 암호 입력
			if (mb_strlen ( $command, "UTF-8" ) < 8) {
				$this->plugin->message ( $sender, $this->plugin->get ( "too-short-passcode" ) );
				$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-passcode" ) );
				$event->setCancelled ();
				return;
			}
			$this->plugin->getConfig ()->set ( "passcode", $command );
			$this->plugin->message ( $sender, $this->plugin->get ( "passcode-selected" ) );
			if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
				$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-ip" ) );
			} else if ($this->plugin->getConfig ()->get ( "servermode", null ) == "master") {
				$this->plugin->message ( $sender, $this->plugin->get ( "all-setup-complete" ) );
			}
			$event->setCancelled ();
			return;
		}
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") { // 슬레이브 모드일 경우
			if ($this->plugin->getConfig ()->get ( "masterip", null ) == null) { // 마스터서버 아이피 입력
				$ip = explode ( ".", $command );
				if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
					$this->plugin->message ( $sender, $this->plugin->get ( "wrong-ip" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-ip" ) );
					$event->setCancelled ();
					return;
				}
				$this->plugin->getConfig ()->set ( "masterip", $command );
				$this->plugin->message ( $sender, $this->plugin->get ( "master-ip-selected" ) );
				$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-port" ) );
				$event->setCancelled ();
				return;
			}
			if ($this->plugin->getConfig ()->get ( "masterport", null ) == null) { // 마스터서버 포트 입력
				if (! is_numeric ( $command ) or $command <= 30 or $command >= 65535) {
					$this->plugin->message ( $sender, $this->plugin->get ( "wrong-port" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-port" ) );
					$event->setCancelled ();
					return;
				}
				$this->plugin->getConfig ()->set ( "masterport", $command );
				$this->plugin->message ( $sender, $this->plugin->get ( "master-port-selected" ) );
				$this->plugin->message ( $sender, $this->plugin->get ( "all-setup-complete" ) );
				$event->setCancelled ();
				return;
			}
		}
	}
	public function onLogin(PlayerPreLoginEvent $event) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			if (! $event->getPlayer () instanceof Player)
				return;

			// defaultInfoRequest
			// slave->master = [패스코드, defaultInfoRequest, 유저명, IP]
			// master->slave = [패스코드, defaultInfoRequest, 유저명, 타서버접속여부[true|false], 가입여부[true||false], 유저정보데이터]
			
			// $this->plugin->getConfig()->get("passcode");
			// $event->getPlayer()->getName()
			// $event->getPlayer()->getAddress()
			
			// itemSyncro
			// slave->master = [패스코드, itemSyncro, 유저명, itemData]
			// slave->master = [패스코드, itemSyncro, 유저명, itemData]
			// economySyncro
			// slave->master = [패스코드, economySyncro, 유저명, economySyncro]
			// slave->master = [패스코드, economySyncro, 유저명, economySyncro]
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave")
			return true;

		// logoutRequest
		// slave->master = [패스코드, loginRequest, 유저명, 암호해시, IP]
		// itemSyncro
		// slave->master = [패스코드, itemSyncro, 유저명, itemData]
		// slave->master = [패스코드, itemSyncro, 유저명, itemData]
		// economySyncro
		// slave->master = [패스코드, economySyncro, 유저명, economySyncro]
		// slave->master = [패스코드, economySyncro, 유저명, economySyncro]
	}
	public function onCommand(CommandSender $player, Command $command, $label, array $args) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave")
			return true;
		// loginRequest
		// slave->master = [패스코드, loginRequest, 유저명, 암호해시, IP]
		// master->slave = [패스코드, loginRequest, 유저명, 접속성공여부[true||false]]
		// registerRequest
		// slave->master = [패스코드, registerRequest, 유저명, 암호해시, IP]
		// master->slave = [패스코드, registerRequest, 유저명, 접속성공여부[true||false]]
		// unregisterRequest
		// slave->master = [패스코드, unregisterRequest, 유저명, 암호해시]
		// master->slave = [패스코드, unregisterRequest, 유저명, 탈퇴성공여부[true||false]]
	}
	// TODO - (연동기능)타서버에 유저가 이미 접속해있으면 접속차단
	// TODO - (연동기능)모든 연동작업은 비동기 스레딩으로 처리
	// TODO - (연동기능)마스터에서 슬레이브로 데이터가 올때까지 대기 유저의 행동을 차단
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = json_decode ( $ev->getPacket ()->data );
		if (! is_array ( $data )) {
			echo "[테스트] 어레이가 아닌 정보 전달됨";
			return;
		}
		if ($data [0] != $this->plugin->getConfig ()->get ( "passcode", false )) {
			echo "[테스트] 패스코드가 다른 정보 전달됨";
			return;
		}
		if ($this->getConfig ()->get ( "servermode", null ) == "master") {
			switch ($data [1]) {
				case "defaultInfoRequest" :
					$requestedUserName = $data [2];
					$requestedUserIp = $data [3];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
					break;
				case "loginRequest" :                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    
					break;
				case "registerRequest" :
					break;
				case "itemSyncro" :
					break;
				case "economySyncro" :
					break;
			}
		} else if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			switch ($data [1]) {
				case "defaultInfoRequest" :
					break;
				case "loginRequest" :
					break;
				case "registerRequest" :
					break;
				case "itemSyncro" :
					break;
				case "economySyncro" :
					break;
			}
		}
	}
}
?>