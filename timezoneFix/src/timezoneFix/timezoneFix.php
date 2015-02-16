<?php

namespace timezoneFix;

use pocketmine\utils\Utils;
use pocketmine\plugin\PluginBase;

class timezoneFix extends PluginBase{
	public function onEnable() {
		if($response = Utils::getURL("http://ip-api.com/json")
				and $ip_geolocation_data = \json_decode($response, \true)
				and $ip_geolocation_data['status'] != 'fail'
				and \date_default_timezone_set($ip_geolocation_data['timezone'])
		){
			\ini_set("date.timezone", $ip_geolocation_data['timezone']);
		}
	}
}
?>