<?php

namespace OpenVPNLock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

class OpenVPNLock extends PluginBase implements Listener {
	public $vpn_file, $vpn_data = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		if (file_exists ( $this->getDataFolder () . "vpnlist.yml" )) {
			$this->vpn_file = $this->initializeYML ( "vpnlist.yml", [ ] );
			$this->vpn_data = $this->vpn_file->getAll ();
		} else {
			$this->vpn_file = $this->initializeYML ( "vpnlist.yml", [ ] );
			$this->UpdateOpenVpnList ();
		}
	}
	public function onDisable() {
		$this->vpn_file->setAll ( $this->vpn_data );
		$this->vpn_file->save ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		switch (strtolower ( strtolower ( $command->getName () ) )) {
			case "vpnupdate" :
				$this->getLogger ()->info ( "[VPN 차단] 리스트 업데이트를 진행합니다." );
				$this->UpdateOpenVpnList ();
				$this->getLogger ()->info ( "[VPN 차단] 리스트 업데이트가 완료되었습니다." );
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		$ip = $player->getAddress ();
		
		if (isset ( $this->vpn_data [$ip] )) {
			$event->setCancelled ();
			$this->getLogger ()->info ( TextFormat::DARK_AQUA . "[OpenVPN 차단] " . $ip . " " . $player->getName () . "차단되었습니다." );
		}
	}
	public static function getURL($page, $timeout = 10) {
		$ch = curl_init ( $page );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, [ 
				"User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0 PocketMine-MP" 
		] );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, \true );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, \false );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt ( $ch, CURLOPT_FORBID_REUSE, 1 );
		curl_setopt ( $ch, CURLOPT_FRESH_CONNECT, 1 );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, \true );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, \true );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, ( int ) $timeout );
		$ret = curl_exec ( $ch );
		curl_close ( $ch );
		
		return $ret;
	}
	public function UpdateOpenVpnList() {
		$this->vpn_data = [ ];
		$data = $this->getURL ( 'http://www.vpngate.net/api/iphone/' );
		while ( isset ( explode ( "\r\n", $data )[1] ) ) {
			$data = explode ( "\r\n", $data, 2 );
			
			if (isset ( $data [1] ))
				$data = explode ( ",", $data [1], 2 );
			if (isset ( $data [1] ))
				$data = explode ( ",", $data [1], 2 );
			
			if (isset ( $data [0] ))
				$ipcheck = explode ( '.', $data [0] );
			
			if (isset ( $ipcheck [3] )) {
				$this->getLogger ()->info ( TextFormat::DARK_AQUA . "[VPN 차단] - " . $data [0] . " 차단됨." );
				$this->vpn_data [$data [0]] = 1;
			}
			
			if (isset ( $data [1] )) {
				$data = $data [1];
			} else {
				break;
			}
		}
		unset ( $data );
	}
	public function initializeYML($path, $array) {
		return new Config ( $this->getDataFolder () . $path, Config::YAML, $array );
	}
}
?>