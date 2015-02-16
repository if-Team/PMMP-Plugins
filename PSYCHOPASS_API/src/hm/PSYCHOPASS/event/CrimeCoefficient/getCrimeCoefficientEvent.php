<?php

namespace hm\PSYCHOPASS\Event\CrimeCoefficient;

use hm\PSYCHOPASS\Event\PSYCHOPASS_API_Event;

class getCrimeCoefficientEvent extends PSYCHOPASS_API_Event {
	private $plugin, $player, $crimecoefficient, $issuer;
	public function __construct(PSYCHOPASS_API_Event $api, $player, $crimecoefficient, $issuer) {
		$this->plugin = $api;
		$this->player = $player;
		$this->crimecoefficient = $crimecoefficient;
	}
	public function getPlayer() {
		return $this->player;
	}
	public function getCrimeCoefficient() {
		return $this->crimecoefficient;
	}
}
?>