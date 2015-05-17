<?php

namespace PMSocket;

use pocketmine\event\Listener;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;

class PMResender implements Listener {
    private $updateList = [];
    private $password;

    public function __construct($password){
        $this->password = $password;
    }

    public function stream($level, $message){
        echo $message . " - PONG!\n";

        foreach($this->updateList as $address => $data){
            echo "PING! :" . $address . "\n";

            $progress = time() - $this->updateList[$address]["LastContact"];
            if($progress > 9){
                unset($this->updateList[$address]);
                continue;
            }

            $address = explode(":", $address);
            CPAPI::sendPacket(new DataPacket($address[0], $address[1], json_encode([$this->password, "console", $level, $message])));
        }
    }

    public function onPacketReceive(CustomPacketReceiveEvent $event){
        $data = json_decode($event->getPacket()->data);

        if(!is_array($data)){
            echo "[테스트] 배열이 아닌 정보 전달됨\n";
            $event->getPacket()->printDump();
            return;
        }

        if($data["password"] != $this->password){
            echo "[테스트] 패스워드가 다른 정보 전달됨\n";
            var_dump($data["password"]);
            return;
        }

        $ip = $event->getPacket()->address;
        $port = $event->getPacket()->port;

        $address = $ip . ":" . $port;

        switch($data["type"]){
            case "update":
                $this->updateList[$address]["LastContact"] = time();

                CPAPI::sendPacket(new DataPacket($ip, $port, json_encode(["password" => $this->password, "type" => "inside", "state" => "succeed"])));
                break;

            case "disconnect":
                if(isset($this->updateList[$address]["LastContact"])){
                    unset($this->updateList[$address]["LastContact"]);
                }

                CPAPI::sendPacket(new DataPacket($ip, $port, json_encode(["password" => $this->password, "type" => "inside", "state" => "disconnected"])));
                break;
        }
    }
}

?>