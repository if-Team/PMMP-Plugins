<?php

/*
 * FastTransfer plugin for PocketMine-MP
 * Copyright (C) 2015 Shoghi Cervantes <https://github.com/shoghicp/FastTransfer>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace hm\GoAwayAnna;

use pocketmine\network\protocol\DataPacket;

class StrangePacket extends DataPacket {
    /** @var string */
    private $address;

	/** @var int */
    private $port;

    /**
     * @param string $address
     * @param int $port = 19132
     */
    public function __construct($address, $port = 19132){
        $this->address = $address;
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getAddress(){
        return $this->address;
    }

    /**
     * @param string $address
     */
    public function setAddress($address){
        $this->address = $address;
    }

    /**
     * @return int
     */
    public function getPort(){
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort($port){
        $this->port = $port;
    }

    /**
     * @return int
     */
    public function pid(){
        return 0x1b;
    }

	public function encode(){
		$this->reset();
		$this->putAddress();
	}

    public function decode(){

    }

    /**
     * @param int $version = 4
     */
    protected function putAddress($version = 4){
        $this->putByte($version);

        switch($version){
            case 4:
                //IPv4
                foreach(explode(".", $this->getAddress()) as $b){
                    $this->putByte((~((int) $b)) & 0xff);
                }
                $this->putShort($this->getPort());
                break;

            case 6:
                //IPv6
                break;
        }
    }
}