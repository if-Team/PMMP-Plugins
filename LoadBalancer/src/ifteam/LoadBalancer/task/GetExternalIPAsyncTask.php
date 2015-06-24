<?php

namespace ifteam\LoadBalancer\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class GetExternalIPAsyncTask extends AsyncTask {
	public $ip;
	public $pluginName;
	public function __construct($pluginName) {
		$this->pluginName = $pluginName;
	}
	public function onRun() {
		$this->ip = $this->getIP ();
	}
	public function onCompletion(Server $server) {
		$plugin = $server->getPluginManager ()->getPlugin ( $this->pluginName );
		if ($plugin === null)
			return;
		$plugin->setExternalIp ( $this->ip );
	}
	/**
	 * Gets the External IP using an external service
	 *
	 * @return string
	 */
	public function getIP() {
		$ip = \trim (\strip_tags ( $this->getURL ( "http://checkip.dyndns.org/" ) ) );
		$externalIp = "";
		if (\preg_match ( '#Current IP Address\: ([0-9a-fA-F\:\.]*)#', $ip, $matches ) > 0) {
			$externalIp = $matches [1];
		} else {
			$ip = $this->getURL ( "http://www.checkip.org/" );
			if (\preg_match ( '#">([0-9a-fA-F\:\.]*)</span>#', $ip, $matches ) > 0) {
				$externalIp = $matches [1];
			} else {
				$ip = $this->getURL ( "http://checkmyip.org/" );
				if (\preg_match ( '#Your IP address is ([0-9a-fA-F\:\.]*)#', $ip, $matches ) > 0) {
					$externalIp = $matches [1];
				} else {
					$ip = \trim ( $this->getURL ( "http://ifconfig.me/ip" ) );
					if ($ip != "") {
						$externalIp = $ip;
					} else {
						return "0.0.0.0";
					}
				}
			}
		}
		return $externalIp;
	}
	/**
	 * GETs an URL using cURL
	 *
	 * @param
	 *        	$page
	 * @param int $timeout
	 *        	default 10
	 * @param array $extraHeaders        	
	 *
	 * @return bool|mixed
	 */
	public function getURL($page, $timeout = 10, array $extraHeaders = []) {
		$ch = curl_init ( $page );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, \array_merge ( [ 
				"User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0 PocketMine-MP" 
		], $extraHeaders ) );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, \true );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, \false );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt ( $ch, CURLOPT_FORBID_REUSE, 1 );
		curl_setopt ( $ch, CURLOPT_FRESH_CONNECT, 1 );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, \true );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, \true );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, ( int ) $timeout );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, ( int ) $timeout );
		$ret = curl_exec ( $ch );
		curl_close ( $ch );
		
		return $ret;
	}
}

?>