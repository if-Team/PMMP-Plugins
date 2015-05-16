<?php

namespace PMSocket;

use pocketmine\event\Listener;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;
use PMSocket\PMAttachment;

class PMResender implements Listener {
	private $updateList = [ ];
	private $password;
	public function __construct($password) {
		$this->password = $password;
	}
	public function stream($level, $message) {
		echo $message . " - PONG!\n"; // TEST
		foreach ( $this->updateList as $ipport => $data ) {
			echo "PING! :" . $ipport . "\n"; //
			$progress = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) ) - $this->updateList [$ipport] ["lastcontact"];
			if ($progress > 9) {
				unset ( $this->updateList [$ipport] );
				continue;
			}
			$ipport = explode ( ":", $ipport );
			CPAPI::sendPacket ( new DataPacket ( $ipport [0], $ipport [1], json_encode ( [ $this->password,"console",$level,$message ] ) ) );
		}
	}
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = json_decode ( $ev->getPacket ()->data );
		if (! is_array ( $data )) {
			echo "[테스트] 어레이가 아닌 정보 전달됨\n";
			$ev->getPacket ()->printDump ();
			return;
		}
		if ($data [0] != $this->password) {
			echo "[테스트] 패스코드가 다른 정보 전달됨\n";
			var_dump ( $data [0] );
			return;
		}
		switch ($data [1]) {
			case "update" :
				$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["lastcontact"] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
				CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( [ $this->password,"inside","success" ] ) ) );
				break;
			case "disconnect" :
				if (isset ( $this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["lastcontact"] )) {
					unset ( $this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["lastcontact"] );
				}
				CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( [ $this->password,"inside","disconnected" ] ) ) );
		}
	}
	public function makeTimestamp($date) {
		$yy = substr ( $date, 0, 4 );
		$mm = substr ( $date, 5, 2 );
		$dd = substr ( $date, 8, 2 );
		$hh = substr ( $date, 11, 2 );
		$ii = substr ( $date, 14, 2 );
		$ss = substr ( $date, 17, 2 );
		return mktime ( $hh, $ii, $ss, $mm, $dd, $yy );
	}
}

?>