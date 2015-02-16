<?php

namespace CountryLock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat;

class CountryLock extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		$ip = $player->getAddress ();
		
		if (! $this->checkCountry ( $ip )) {
			$event->setCancelled ();
			$this->getLogger ()->info ( TextFormat::DARK_AQUA . "[해외아이피 차단] " . $ip . " " . $player->getName () . "차단되었습니다." );
		}
	}
	public function checkCountry($ip) {
		$fp = fsockopen ( "whois.nida.or.kr", 43 );
		if ($fp) {
			fputs ( $fp, $ip . "\n" );
			$result = null;
			while ( ! feof ( $fp ) ) {
				$result .= fgets ( $fp, 80 );
			}
			var_dump ( $result );
			if (preg_match ( "/whois\.apnic\.net/", $result )) {
				return false;
			} else {
				return true;
			}
		}
	}
}
?>