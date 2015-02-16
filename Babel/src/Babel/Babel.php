<?php

namespace Babel;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;
use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\network\protocol\Info;
use pocketmine\utils\TextFormat;

class Babel extends PluginBase implements Listener {
	public $messages;
	public function onEnable() {
		$this->initMessage ();
		$this->registerCommand ( $this->get ( "translate-command" ), "babel", $this->get ( "translate-command-description" ), "/" . $this->get ( "translate-command" ), "babel" );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if (strtolower ( $command->getName () ) == $this->get ( "translate-command" )) {
			if (! isset ( $args [0] ) or ! isset ( $this->messages ["translation"] [$args [0]] )) {
				$list = TextFormat::DARK_AQUA . $this->get ( "print-all-list" ) . "\n";
				foreach ( $this->messages ["translation"] as $index => $text )
					$list .= $index . " ";
				$sender->sendMessage ( $list );
				return true;
			}
			$this->makeServerCommand ( $sender, $command, $label, $args );
			return true;
		}
	}
	private function makeServerCommand(CommandSender $sender, Command $command, $label, array $args) {
		$server = Server::getInstance ();
		$pharPath =\pocketmine\DATA . $server->getName () . "_translate.phar";
		if (file_exists ( $pharPath )) {
			$sender->sendMessage ( $server->getName () . "_translate.phar" . $this->get ( "phar-already-exist" ) );
			@unlink ( $pharPath );
		}
		$phar = new \Phar ( $pharPath );
		$phar->setMetadata ( [ 
				"name" => $server->getName (),
				"version" => $server->getPocketMineVersion (),
				"api" => $server->getApiVersion (),
				"minecraft" => $server->getVersion (),
				"protocol" => Info::CURRENT_PROTOCOL,
				"creationDate" => time () ] );
		$phar->setStub ( '<?php define("pocketmine\\\\PATH", "phar://". __FILE__ ."/"); require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();' );
		$phar->setSignatureAlgorithm ( \Phar::SHA1 );
		$phar->startBuffering ();
		
		$filePath = substr ( \pocketmine\PATH, 0, 7 ) === "phar://" ? \pocketmine\PATH : realpath ( \pocketmine\PATH ) . "/";
		$filePath = rtrim ( str_replace ( "\\", "/", $filePath ), "/" ) . "/";
		foreach ( new \RecursiveIteratorIterator ( new \RecursiveDirectoryIterator ( $filePath . "src" ) ) as $file ) {
			$path = ltrim ( str_replace ( array (
					"\\",
					$filePath ), array (
					"/",
					"" ), $file ), "/" );
			if ($path {0} === "." or strpos ( $path, "/." ) !== false or substr ( $path, 0, 4 ) !== "src/") continue;
			echo "추가 중... " . ($file->getFilename ()) . "\n";
			foreach ( $this->messages ["translation"] [$args [0]] as $index => $phpfile ) {
				if ($file->getFilename () == $index) {
					$translate = file_get_contents ( $file ); // TODO
					foreach ( $this->messages ["translation"] [$args [0]] [$file->getFilename ()] as $index => $text ) {
						$translate = str_replace ( $index, $text, $translate );
						echo "변경완료 [{$index}] [{$text}]\n";
					}
					if (! file_exists ( \pocketmine\DATA . "extract/" . explode ( $file->getFilename (), $path )[0] )) mkdir (\pocketmine\DATA . "extract/" . explode ( $file->getFilename (), $path )[0], 0777, true );
					echo "폴더생성 " . \pocketmine\DATA . "extract/" . explode ( $file->getFilename (), $path )[0] . "\n";
					file_put_contents (\pocketmine\DATA . "extract/" . $path, $translate );
					break;
				}
			}
			if (file_exists ( \pocketmine\DATA . "extract/" . $path )) {
				$phar->addFile ( \pocketmine\DATA . "extract/" . $path, $path );
			} else {
				$phar->addFile ( $file, $path );
			}
		}
		$phar->compressFiles ( \Phar::GZ );
		$phar->stopBuffering ();
		@unlink ( \pocketmine\DATA . "extract/" );
		
		$sender->sendMessage ( $server->getName () . "_translate.phar" . $this->get ( "phar-translate-complete" ) . $pharPath );
		
		return true;
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function registerCommand($name, $fallback, $description, $usage, $permission) {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
}
?>