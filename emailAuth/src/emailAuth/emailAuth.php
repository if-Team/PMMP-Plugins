<?php

namespace emailAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\CallbackTask;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\permission\PermissionAttachment;

// TODO 향후 가능하면 POP3 이용해서
// 메일을 전송받아야만 가능하게 하는 설정도 추가
// TODO 밴 당한 이메일은 이용불가능하게
// TODO 이메일에 화이트리스트 설정가능하게
class emailAuth extends PluginBase implements Listener {
	private static $instance = null;
	public $db, $rand = [ ];
	public $needAuth = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		if (self::$instance == null)
			self::$instance = $this;
		
		$this->saveDefaultConfig ();
		$this->reloadConfig ();
		
		$this->db = new dataBase ( $this->getDataFolder () . "database.yml" );
		
		$this->saveResource ( "signform.html", false );
		$this->saveResource ( "config.yml", false );
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"autoSave" 
		] ), 2400 );
		
		$this->PHPMailer("hmkuak@naver.com");
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		// TODO SET-UP이 되어있지않으면 매번접속하는 OP에게 알림띄우기
		// TODO SET-UP은 재부팅 된 후에야만 제대로 적용
		
		// TODO OTP나 회원가입시 쓰이는 난수
		// TODO 운영자가 보내는 메일을 받으겠습니까? (Y/N) 메시지
		
		// TODO 메일전송은 별도의 스레드에서 전송시키기 (소요시간 3~4초)
		// TODO 메일전송마다 별도의 스레드 만들기 (빠른 메일전송을 위해)
	}
	public function onDisable() {
		$this->autoSave ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function autoSave() {
		$this->db->save ();
	}
	public function onJoin(PlayerJoinEvent $event) {
		if (isset ( $this->db ["IP"] [$event->getPlayer ()->getAddress ()] )) {
			$this->message ( $event->getPlayer (), "자동 로그인 되었습니다 ! (IP로그인)" );
			return;
		} else
			$this->deauthenticatePlayer ( $player );
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		switch (strtolower ( $command->getName () )) {
			case "로그인" :
				break;
			case "로그아웃" :
				break;
			case "가입" :
				break;
			case "탈퇴" :
				break;
			case "otp" :
				break;
			case "auth" :
				switch (strtolower ( $args [0] )) {
					case "setup" :
						switch (strtolower ( $args [1] )) {
							case "mail" :
								break;
							case "pass" :
								break;
							case "host" :
								break;
							case "port" :
								break;
							default :
								$this->message ( $player, "/auth setup mail - 메일주소" );
								$this->message ( $player, "/auth setup pass - 메일 비밀번호" );
								$this->message ( $player, "/auth setup host - 메일호스트" );
								$this->message ( $player, "/auth setup port - 호스트포트" );
								break;
						}
						break;
					case "test" :
						// 메일러로 메일전송
						// 설정 다 안됬으면 설정요구
						// return 체크하고 정상이면
						// 정상이라 알린 후 재부팅요구
						break;
					case "domain" :
						break;
					case "length" :
						break;
					case "disable" :
						// 회원가입 차단
						break;
					// TODO 서버명 안내메시지 인코딩 접두사
					default :
						$this->message ( $player, "/auth setup - 환경설정" );
						$this->message ( $player, "/auth test - 메일전송 테스트" );
						$this->message ( $player, "/auth domain - 가입가능 도메인 제한" );
						$this->message ( $player, "/auth length - 비밀번호 최소길이 설정" );
						$this->message ( $player, "/auth disable - 회원가입 차단" );
						break;
				}
				break;
		}
		return true;
	}
	public function deauthenticatePlayer(Player $player) {
		$attachment = $player->addAttachment ( $this );
		$this->removePermissions ( $attachment );
		
		$this->needAuth [spl_object_hash ( $player )] = $attachment;
		
		if ($this->db->getEmail ( $player->getAddress () )) {
			$this->loginMessage ( $player );
		} else {
			$this->registerMessage ( $player );
		}
	}
	public function registerMessage(Player $player) {
		$this->message ( $player, "이 서버는 이메일오스를 통한 계정보호를 진행중입니다." );
		$this->message ( $player, "회원등록을 진행해주세요 ! /가입 <이메일주소>" );
	}
	public function loginMessage(Player $player) {
		$this->message ( $player, "이 서버는 이메일오스를 통한 계정보호를 진행중입니다." );
		$this->message ( $player, "로그인을 진행해주세요 ! /로그인 <설정한암호>" );
	}
	protected function removePermissions(PermissionAttachment $attachment) {
		$permissions = [ ];
		foreach ( $this->getServer ()->getPluginManager ()->getPermissions () as $permission ) {
			$permissions [$permission->getName ()] = false;
		}
		
		$permissions ["pocketmine.command.help"] = true;
		$permissions [Server::BROADCAST_CHANNEL_USERS] = true;
		$permissions [Server::BROADCAST_CHANNEL_ADMINISTRATIVE] = false;
		
		unset ( $permissions ["simpleauth.chat"] );
		unset ( $permissions ["simpleauth.move"] );
		unset ( $permissions ["simpleauth.lastip"] );
		
		if ($this->getConfig ()->get ( "disableRegister" ) === true) {
			$permissions ["simpleauth.command.register"] = false;
		} else {
			$permissions ["simpleauth.command.register"] = true;
		}
		$permissions ["simpleauth.command.login"] = true;
		
		uksort ( $permissions, [ 
				emailAuth::class,
				"orderPermissionsCallback" 
		] );
		
		$attachment->setPermissions ( $permissions );
	}
	public static function orderPermissionsCallback($perm1, $perm2) {
		if (self::isChild ( $perm1, $perm2 )) {
			return - 1;
		} elseif (self::isChild ( $perm2, $perm1 )) {
			return 1;
		} else {
			return 0;
		}
	}
	public function PHPMailer($sendmail, $id = null, $code = null) {
		$mail = new PHPMailer ();
		$mail->isSMTP ();
		$mail->SMTPDebug = 2;
		
		$mail->SMTPSecure = 'tls';
		$mail->CharSet = $this->getConfig ()->get ( "encoding" );
		$mail->Encoding = "base64";
		$mail->Debugoutput = 'html';
		$mail->Host = $this->getConfig ()->get ( "adminEmailHost" );
		$mail->Port = $this->getConfig ()->get ( "adminEmailPort" );
		$mail->SMTPAuth = true;
		
		$mail->Username = explode ( "@", $this->getConfig ()->get ( "adminEmail" ) )[0];
		$mail->Password = $this->getConfig ()->get ( "adminEmailPassword" );
		
		$mail->setFrom ( $this->getConfig ()->get ( "adminEmail" ), $this->getConfig ()->get ( "serverName" ) );
		$mail->addReplyTo ( $this->getConfig ()->get ( "adminEmail" ), $this->getConfig ()->get ( "serverName" ) );
		$mail->addAddress ( $sendmail );
		$mail->Subject = $this->getConfig ()->get ( "subjectName" );
		
		$signform = file_get_contents ( $this->getDataFolder () . "signform.html" );
		// TODO 폼 내용 변경
		// ##ID## - 회원아이디
		// ##TIME## - 현재시각 ->시각을 비밀번호로 교체
		// ##SERVER## - 서버이름
		// ##CODE## - 가입인증 코드
		$mail->msgHTML ( $signform );
		
		// echo $mail->ErrorInfo ."\n";
		return ($mail->send ()) ? true : false;
	}
	private function hash($salt, $password) {
		return bin2hex ( hash ( "sha512", $password . $salt, true ) ^ hash ( "whirlpool", $salt . $password, true ) );
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::RED . $mark . $text );
	}
}
?>