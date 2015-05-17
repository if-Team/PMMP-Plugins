<?php

namespace PMSocket;

class PMAttachment extends \ThreadedLoggerAttachment implements \LoggerAttachment {
	public function __construct(PMResender $resender){
		$this->resender = $resender;
	}

    public function log($level, $message){
    	$this->resender->stream($level, $message);
    }
}