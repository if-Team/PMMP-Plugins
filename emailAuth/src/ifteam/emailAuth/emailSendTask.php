<?php

namespace ifteam\emailAuth;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class emailSendTask extends AsyncTask {
	public $sendMail, $id, $time, $serverName, $code, $istest, $config, $signform;
	public function __construct($sendMail, $id, $time, $serverName, $code, $config, $signform) {
		$this->sendMail = $sendMail;
		$this->id = $id;
		$this->time = $time;
		$this->serverName = $serverName;
		$this->code = $code;
		$this->config = $config;
		$this->signform = $signform;
	}
	public function onRun() {
		$this->sendRegisterMail ( $this->sendMail, $this->id, $this->time, $this->serverName, $this->code );
	}
	public function sendRegisterMail($sendMail, $id, $time, $serverName, $code) {
		$signForm = file_get_contents ( $this->signform );
		$signForm = str_replace ( "##ID##", $id, $signForm );
		$signForm = str_replace ( "##TIME##", $time, $signForm );
		$signForm = str_replace ( "##SERVER##", $serverName, $signForm );
		$signForm = str_replace ( "##CODE##", $code, $signForm );
		return ($this->PHPMailer ( $sendMail, $signForm )) ? true : false;
	}
	public function PHPMailer($sendMail, $html) {
		$mail = new PHPMailer ();
		$mail->isSMTP ();
		$mail->SMTPDebug = 0;
		if ($istest) $mail->SMTPDebug = 2;
		
		$mail->SMTPSecure = 'tls';
		$mail->CharSet = $this->config ["encoding"];
		$mail->Encoding = "base64";
		$mail->Debugoutput = 'html';
		$mail->Host = $this->config ["adminEmailHost"];
		$mail->Port = $this->config ["adminEmailPort"];
		$mail->SMTPAuth = true;
		
		$mail->Username = explode ( "@", $this->config ["adminEmail"] )[0];
		$mail->Password = $this->config ["adminEmailPassword"];
		
		$mail->setFrom ( $this->config ["adminEmail"], $this->config ["serverName"] );
		$mail->addReplyTo ( $this->config ["adminEmail"], $this->config ["serverName"] );
		$mail->addAddress ( $sendMail );
		$mail->Subject = $this->config ["subjectName"];
		
		$mail->msgHTML ( $html );
		
		if ($istest) echo $mail->ErrorInfo . "\n";
		return ($mail->send ()) ? true : false;
	}
}

?>