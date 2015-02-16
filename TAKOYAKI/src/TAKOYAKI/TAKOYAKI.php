<?php

namespace TAKOYAKI;

use pocketmine\plugin\PluginBase;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class TAKOYAKI extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (! strtolower ( $command->getName () ) == "takoyaki")
			return;
		switch (strtolower ( $args [0] )) {
			case "login" :
				if (isset ( $args [1] ) and isset ( $args [2] )) {
					$connected = $this->naverLogin ( $args [1], $args [2] );
					if ($connected) {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[TAKOYAKI] 아이디<" . $args [1] . "> 정상 접속되었습니다." );
						return true;
					} else {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[TAKOYAKI] 아이디<" . $args [1] . "> 비밀번호가 맞지 않습니다." );
						return true;
					}
				}
				break;
			default :
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/TAKOYAKI Login <id> <pw>" );
				return;
		}
	}
	function winhttp($method, $url, $header = '', $data = '') {
		// Convert the data array into URL Parameters like a=b&foo=bar etc.
		// $data = http_build_query($data);
		
		// parse the given URL
		$url = parse_url ( $url );
		
		// extract host and path:
		$host = $url ['host'];
		$path = $url ['path'];
		$res = '';
		
		if ($url ['scheme'] == 'http') {
			$fp = fsockopen ( $host, 80, $errno, $errstr, 300 );
		} else if ($url ['scheme'] == 'https') {
			$fp = fsockopen ( "ssl://" . $host, 443, $errno, $errstr, 30 );
		}
		
		// open a socket connection on port 80 - timeout: 300 sec
		$reqBody = $data;
		$reqHeader = $method . " $path HTTP/1.1\r\n" . "Host: $host\r\n";
		$reqHeader .= $header . "\r\n" . "Content-length: " . strlen ( $reqBody ) . "\r\n" . "Connection: keep-alive\r\n\r\n";
		
		/* send request */
		fwrite ( $fp, $reqHeader );
		fwrite ( $fp, $reqBody );
		
		while ( ! feof ( $fp ) ) {
			$res .= fgets ( $fp, 1024 );
		}
		
		fclose ( $fp );
		
		// split the result header from the content
		$result = explode ( "\r\n\r\n", $res, 2 );
		
		$header = isset ( $result [0] ) ? $result [0] : '';
		$content = isset ( $result [1] ) ? $result [1] : '';
		
		return array (
				'body' => $content,
				'header' => $header 
		);
	}
	function Xwinhttp($method, $url, $referer = null, $data = '', $type = null, $cookie = null) {
		$ch = curl_init ();
		echo "URL: " . $url . "\n";
		curl_setopt ( $ch, CURLOPT_URL, $url );
		echo "Referer: " . $referer . "\n";
		curl_setopt ( $ch, CURLOPT_REFERER, $referer ); // 'Referer: ' .
		curl_setopt ( $ch, CURLOPT_COOKIE, $cookie );
		curl_setopt ( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; Trident/6.0)" );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		echo "PostData: " . $data . "\n";
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		echo "Content-Type: " . $type . "\n";
		echo "Content-Length: " . strlen ( $data ) . "\n";
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
				'Content-Type: ' . $type,
				'Content-Length: ' . strlen ( $data ) 
		) );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 0 ); // ssl stuff
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false ); // On dev server only!
		$result = curl_exec ( $ch );
		curl_close ( $ch );
		return $result;
		// split the result header from the content
		// $result = explode ( "\r\n\r\n", $result, 2 );
		
		// $header = isset ( $result [0] ) ? $result [0] : '';
		// $content = isset ( $result [1] ) ? $result [1] : '';
		
		// return array (
		// / 'body' => $content,
		// 'header' => $header
		// );
	}
	function naverLogin($id, $pw) {
		$response = $this->winhttp ( 'GET', 'http://static.nid.naver.com/enclogin/keys.nhn' );
		$SendKey = explode ( ",", $response ['body'] );
		// TESTCODE
		$f = explode ( "\r\n", $SendKey [0] );
		echo "S0: " . $f [1] . "\n";
		echo "S1: " . $SendKey [1] . "\n";
		echo "S2: " . $SendKey [2] . "\n";
		$e = explode ( "\r\n", $SendKey [3] );
		echo "S3: " . $e [0] . "\n";
		$rsa = $this->createRsaKey ( $id, $pw, $f [1], $SendKey [1], $SendKey [2], $e [0] );
		
		echo $response ['header'];
		$tmp = explode ( 'Set-Cookie: ', $response ['header'] );
		$LOG_SES = '';
		for($i = 1; $i <= (sizeof ( $tmp ) - 1); $i ++) {
			$NID_SES = explode ( 'Set-Cookie: ', $response ['header'] );
			$NID_SES = explode ( ';', $NID_SES [$i] );
			$LOG_SES .= $NID_SES [0] . '; ';
		}
		$cookie = substr ( $LOG_SES, 0, strlen ( $LOG_SES ) - 2 );
		echo "CooKie1: " . $cookie . "\n";
		$HeaderString = "enctp" . "=" . "1";
		$HeaderString .= "&encpw" . "=" . $rsa;
		$HeaderString .= "&encnm" . "=" . $SendKey [1];
		$HeaderString .= "&svctype" . "=" . "0";
		$HeaderString .= "&url=http%3A%2F%2Fwww.naver.com%2F&enc_url=http%253A%252F%252Fwww.naver.com%252F&postDataKey=&nvlong=&saveID=&smart_level=1";
		$HeaderString .= "&id" . "=" . "";
		$HeaderString .= "&pw" . "=" . "";
		$HeaderString .= "&x" . "=" . "28";
		$HeaderString .= "&y" . "=" . "44";
		echo "Alert: " . $HeaderString . "\n";
		
		$referer = 'http://static.nid.naver.com/login.nhn?svc=wme&amp;url=http%3A%2F%2Fwww.naver.com&amp;t=20120425';
		$content_type = 'application/x-www-form-urlencoded';
		$response = $this->Xwinhttp ( 'POST', 'https://nid.naver.com/nidlogin.login', $referer, $HeaderString );
		// $response = $this->Xwinhttp ( 'POST', 'https://nid.naver.com/nidlogin.login', $referer, $HeaderString, $content_type, $cookie );
		echo "FINAL_RESPONE: " . $response . "\n";
		if ($this->instr ( $response, "location" )) {
			return true;
		} else {
			return false;
		}
		return false;
	}
	function createRsaKey($id, $pw, $sessionKey, $keyName, $eValue, $nValue) {
		$rsa = new RSA ();
		$n = $eValue; // naver~trick
		$e = $nValue; // switch~them
		$rsa->modulus = new BigInteger ( $n, 16 );
		$rsa->publicExponent = new BigInteger ( $e, 16 );
		$key = $rsa->getPublicKey ();
		$rsa->loadKey ( $key );
		$rsa->setEncryptionMode ( CRYPT_RSA_ENCRYPTION_PKCS1 );
		
		$comVal = $this->getLenChar ( $sessionKey ) + $sessionKey + $this->getLenChar ( $id ) + $id;
		return bin2hex ( $rsa->encrypt ( $comVal + $this->getLenChar ( $pw ) + $pw ) );
	}
	function getLenChar($texts) {
		return chr ( strlen ( $texts ) );
	}
	function instr($haystack, $needle) {
		$pos = strpos ( $haystack, $needle, 0 );
		if ($pos != 0)
			return true;
		return false;
	}
}
?>
