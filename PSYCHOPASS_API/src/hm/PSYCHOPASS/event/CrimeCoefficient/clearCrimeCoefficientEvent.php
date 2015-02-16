<?php

namespace hm\PSYCHOPASS\Event\CrimeCoefficient;

use hm\PSYCHOPASS\Event\PSYCHOPASS_API_Event;

class clearCrimeCoefficientEvent extends PSYCHOPASS_API_Event {
	private $plugin, $player, $issuer;
	public function __construct(PSYCHOPASS_API_Event $api, $player, $issuer) {
		$this->plugin = $api;
		$this->player = $player;
	}
	public function getPlayer() {
		return $this->player;
	}
	public function getCrimeCoefficient() {
		return $this->crimecoefficient;
	}
}
?>