<?php

namespace LaunchCode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;

class LaunchCode extends PluginBase implements Listener {
	public $security_keys = [ ];
	public $login = false;
	public $ctime = null;
	public $enable = null;
	public $cancelcount = null;
	public $locked = 'false';
	public $logining = null;
	public $passkey = null;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->readPassCode ();
	}
	public function ServerCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		$sender = $event->getSender ();
		if (! $this->enable)
			return;
		if ($this->locked != 'false') {
			$rtime = 60 - abs ( $this->locked - time () );
			echo "\033[36m" . "[LaunchCode] " . "\033[31m" . "콘솔 잠겨있습니다. (" . $rtime . "초 남음)" . "\n";
			$event->setCancelled ();
			return;
		}
		if ($sender instanceof Player)
			return;
		if ($this->logining == true) {
			$event->setCancelled ();
			if ($this->ACCESS ( $command ))
				$this->login = true;
			$this->logining = false;
			return;
		}
		if (abs ( date ( 'i' ) - $this->ctime ) >= 10 and $this->ctime != null and $this->login == true) {
			echo "\033[36m" . "[LaunchCode] " . "\033[0m" . "자동 로그아웃 되었습니다. (5M TIME OUT)\n";
			$this->login = false;
			$event->setCancelled ();
		}
		$this->ctime = date ( 'i' );
		if ($command == 'login' and $this->login == false) {
			$event->setCancelled ();
			
			$rand1 = rand ( 0, 4 );
			$rand2 = rand ( 0, 4 );
			$number = $rand1 + 1;
			$alphabet = chr ( ord ( 'A' ) + $rand2 );
			
			$rand_face = rand ( 0, 1 );
			if ($rand_face)
				$this->passkey = $this->security_keys [$rand1] [$rand2] [0];
			else
				$this->passkey = $this->security_keys [$rand1] [$rand2] [1];
			
			if ($rand_face)
				$rand_face = "앞";
			else
				$rand_face = "뒷";
			
			echo "\033[36m" . "[LaunchCode] " . "\033[0m" . "보안코드 [" . $number . $alphabet . "]번 " . "\033[33m" . $rand_face . " 두자리" . "\033[0m" . "를 입력하세요. : " . "\033[30m";
			$this->logining = true;
			return;
		}
		if ($this->login == false) {
			$event->setCancelled ();
			echo "\033[36m" . "[LaunchCode] " . "\033[31m" . "권한이 없습니다. (ACCESS DENIED).\n";
			if (++ $this->cancelcount >= 5) {
				$this->locked = time ();
				echo "\033[36m" . "[LaunchCode] " . "\033[31m" . "콘솔 잠금처리되었습니다. (CONSOLE LOCKED)\n";
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"unlock" 
				], [ 
						$event 
				] ), 20 * 60 );
			} else {
				echo "\033[36m" . "[LaunchCode] " . "\033[31m" . $this->cancelcount . "회실패. (5회시 잠금처리됩니다.) (LOCK WARNING).\n";
			}
			return;
		}
		if ($command == 'logout') {
			if ($this->login == true) {
				$this->login = false;
				$event->setCancelled ();
				echo "\033[36m" . "[LaunchCode] " . "\033[0m" . "정상적으로 로그아웃 되었습니다.\n";
				echo "\033[36m" . "[LaunchCode] " . "\033[0m" . "콘솔보호가 작동됩니다. (AUTHENTICATION REQUIRED)\n";
			}
		}
	}
	public function unlock() {
		$this->cancelcount = 0;
		$this->locked = 'false';
	}
	public function readPassCode() {
		$content = null;
		@mkdir ( $this->getDataFolder () );
		$txtFile = $this->getDataFolder () . "security_code.txt";
		$ymlFile = $this->getDataFolder () . "security_hash.yml";
		if (file_exists ( $ymlFile )) {
			$this->enable = true;
			$this->security_hash = new Config ( $ymlFile, Config::YAML, $this->security_keys );
			$this->security_keys = $this->security_hash->getAll ();
			return;
		}
		if (file_exists ( $txtFile )) {
			$this->enable = true;
			$keys = [ ];
			$fp = fopen ( $txtFile, "r" );
			while ( ! feof ( $fp ) )
				$content .= fgets ( $fp );
			$e = explode ( "\r\n", $content );
			for($a = 0; $a <= 4; $a ++)
				$keys [$a] = explode ( " ", $e [$a] );
			fclose ( $fp );
			@unlink ( $txtFile );
			for($h1 = 0; $h1 <= 4; $h1 ++) {
				for($h2 = 0; $h2 <= 4; $h2 ++) {
					$this->security_keys [$h1] [$h2] ['0'] = $this->hash ( substr ( $keys [$h1] [$h2], 0, 2 ) );
					$this->security_keys [$h1] [$h2] ['1'] = $this->hash ( substr ( $keys [$h1] [$h2], 2, 2 ) );
				}
			}
			$this->security_hash = new Config ( $this->getDataFolder () . "security_hash.yml", Config::YAML, $this->security_keys );
			$this->security_hash->save ();
		} else {
			$this->enable = false;
			$this->getServer ()->getLogger ()->critical ( "[LaunchCode] security_code.txt 를 넣어주세요." );
			$this->getServer ()->getLogger ()->critical ( "[LaunchCode] 비활성화 되었습니다. " );
		}
	}
	public function ACCESS($command) {
		if ($this->hash ( $command ) == $this->passkey) {
			echo "\033[36m" . "[LaunchCode] " . "\033[0m" . "인증에 성공하였습니다. (ACCESS GRANTED)\n";
			return true;
		} else {
			echo "\033[36m" . "[LaunchCode] " . "\033[31m" . "인증에 실패하였습니다. (ACCESS DENIED)\n";
			if (++ $this->cancelcount >= 5) {
				$this->locked = time ();
				echo "\033[36m" . "[LaunchCode] " . "\033[31m" . "콘솔 잠금처리되었습니다. (CONSOLE LOCKED)\n";
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"unlock" 
				] ), 20 * 60 );
			} else {
				echo "\033[36m" . "[LaunchCode] " . "\033[31m" . $this->cancelcount . "회실패. (5회시 잠금처리됩니다.) (LOCK WARNING).\n";
			}
			$this->cancelcount ++;
			return false;
		}
	}
	private function hash($word) {
		return bin2hex ( hash ( "sha512", $word, true ) ^ hash ( "whirlpool", $word, true ) );
	}
}

?>