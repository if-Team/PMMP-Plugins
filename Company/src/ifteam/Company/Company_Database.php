<?php

namespace ifteam\Company;

use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\Plugin;

class Company_Database {
	/**
	 *
	 * @var Plugin variable
	 */
	private $plugin;
	/**
	 *
	 * @var YML be saved the position
	 */
	private $path;
	/**
	 *
	 * @var Config variable
	 *      CompanyData { [companyname] [description] [stafflist] [daywages] [officepos] [founder] [money] [applywaitlist] }
	 *      , UserData { [username] [companylist] }
	 *      , PluginData { [isenabled] [startupcost] }
	 */
	private $yml;
	/**
	 *
	 * @param string $path        	
	 * @param string $plugin        	
	 */
	public function __construct($path, Company $plugin) {
		$this->plugin = &$plugin;
		$this->path = &$path;
		$this->yml = (new Config ( $this->path, Config::YAML, [ 
				"PluginData" => [ ],
				"CompanyData" => [ ],
				"UserData" => [ ] 
		] ))->getAll ();
	}
	/**
	 * It makes save the YML
	 *
	 * @return true|false
	 */
	public function save() {
		$config = new Config ( $this->path, Config::YAML );
		$config->setAll ( $this->yml );
		return $config->save ();
	}
	/**
	 * Create a company
	 *
	 * @param string $companyname        	
	 * @param string $description        	
	 * @param array $stafflist        	
	 * @param string $daywages        	
	 * @param position $officepos        	
	 * @return true|false
	 */
	public function createCompany($companyname, $description = null, $stafflist = null, $daywages, $officepos = null, $founder = null, $money = null) {
		if (isset ( $this->yml ["CompanyData"] [$companyname] ))
			return false;
		$this->yml ["CompanyData"] [$companyname] = [ 
				"description" => $description,
				"stafflist" => $stafflist,
				"daywages" => $daywages,
				"officepos" => $officepos,
				"founder" => $founder,
				"money" => $money,
				"applywaitlist" => [ 
						null 
				] 
		];
		return true;
	}
	/**
	 * The bankrupt companies
	 *
	 * @param string $companyname        	
	 */
	public function bankruptcyCompany($companyname) {
		// TODO 회사파산처리
	}
	/**
	 * Returns Company information
	 *
	 * @param string $companyname        	
	 * @param string $username        	
	 *
	 * @return string $infomation
	 */
	public function aboutCompany($companyname, $username = null) {
		// TODO 회사정보 리턴
		// Apple (*30명)
		// 일일 급여 - 1$
		// 자산 규모 - 100000$
		// 설립자 : Jobs
		// 위치: 132, 132 ,132, world
	}
	/**
	 * Warp to the company office
	 *
	 * @param string $companyname        	
	 * @param player $player        	
	 * @return true|false
	 */
	public function visitCompany($companyname, Player $player) {
		$companyData = $this->getCompanyData ( $companyname );
		$officePos = explode ( ",", $companyData ["officepos"] );
		
		if (! isset ( $officePos [3] ))
			return false; // POS WRONG
		
		if ($player->getLevel ()->getFolderName () != $officePos [3]) {
			if ($this->plugin instanceof Plugin) {
				$level = $this->plugin->getServer ()->getLevelByName ( $officePos [3] );
				if ($level == null)
					return false; // LEVEL NOT EXIST
				$position = new Position ( $officePos [0], $officePos [1], $officePos [2], $level );
			} else {
				return false; // PLUGIN WRONG
			}
		} else {
			$position = new Vector3 ( $officePos [0], $officePos [1], $officePos [2] );
		}
		
		return $player->teleport ( $position );
		// TODO 해당 위치로 워프되었습니다 !
	}
	/**
	 * View the company commands Help to Player
	 *
	 * @param player $player        	
	 * @param string $index        	
	 */
	public function helpCommand(Player $player, $index = 1) {
		if ($index = 1) {
			$this->plugin->message ( $this->plugin->get ( "command-help-create" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-list" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-request" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-about" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-help" ) );
		} else if ($index = 2) {
			$this->plugin->message ( $this->plugin->get ( "command-help-visit" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-employ" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-eliminated" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-employclear" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-help" ) );
		} else if ($index = 3) {
			$this->plugin->message ( $this->plugin->get ( "command-help-setabout" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-setdaywages" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-paybonus" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-fired" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-help" ) );
		} else if ($index = 4) {
			$this->plugin->message ( $this->plugin->get ( "command-help-bankruptcy" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-resign" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-setoffice" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-startupcost" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-help" ) );
		} else if ($index = 5) {
			$this->plugin->message ( $this->plugin->get ( "command-help-enable" ) );
			$this->plugin->message ( $this->plugin->get ( "command-help-help" ) );
		}
	}
	/**
	 * apply to the companies
	 *
	 * @param string $companyname        	
	 * @param string $username        	
	 * @param string $pledge        	
	 * @return true|false
	 */
	public function addApplyWaitList($companyname, $username, $pledge) {
		if (! isset ( $this->yml ["CompanyData"] [$companyname] ))
			return false; // COMPANY NOT EXIST
		if ($this->isWorking ( $username, $companyname ))
			return false; // ALREADY APLLIED
		$this->yml ["CompanyData"] [$companyname] ["applywaitlist"] [$username] = $pledge;
		// TODO 취직자에게 사항알림
		return true;
	}
	/**
	 * The employment applicants
	 *
	 * @param string $companyname        	
	 * @param string $username        	
	 * @return true|false
	 */
	public function employApplyWaitList($companyname, $username) {
		// TODO 지원대기자 채용
		if (! isset ( $this->yml ["CompanyData"] [$companyname] ["applywaitlist"] [$username] ))
			return false; // USER NOT EXIST
		if ($this->isWorking ( $username, $companyname ))
			return false; // ALREADY APLLIED
		$this->yml ["CompanyData"] ["stafflist"] [$username] = 0;
		unset ( $this->yml ["CompanyData"] [$companyname] [$username] );
		// TODO 고용주에게 사항알림
		// TODO 취직자에게 사항알림
		return true;
	}
	/**
	 * Applicants fire Careers
	 *
	 * @param string $companyname        	
	 * @param string $username        	
	 * @return true|false
	 */
	public function firedApplyWaitList($companyname, $username) {
		// TODO 지원대기자 불채용
	}
	/**
	 * Initialize the list of applicants.
	 *
	 * @param string $companyname        	
	 * @return true|false
	 */
	public function clearApplyWaitList($companyname) {
		// TODO 지원대기자명단 삭제
	}
	/**
	 * Toggle the company commands
	 *
	 * @param bool $bool        	
	 */
	public function setPluginEnabled(Bool $bool) {
		// TODO 활성화여부설정
	}
	/**
	 * Writes About Us posts
	 *
	 * @param string $companyname        	
	 * @param string $description        	
	 */
	public function setCompanyAbout($companyname, $description) {
		// TODO 회사설명설정
	}
	/**
	 * Set the Day Wages
	 *
	 * @param string $companyname        	
	 * @param string $daywages        	
	 */
	public function setCompanyDaywages($companyname, $daywages) {
		// TODO 회사일급설정
	}
	/**
	 * Set the Day Wages
	 *
	 * @param string $companyname        	
	 * @param Position $daywages        	
	 */
	public function setOfficePosition($companyname, Position $position) {
		// TODO 회사위치설정
	}
	/**
	 * Set the company generated costs
	 *
	 * @param string $dollar        	
	 */
	public function setStartUpCost($dollar) {
		$this->yml["PluginData"]["StartUpCost"] = $dollar;
	}
	/**
	 * Bonus to Employees
	 *
	 * @param string $companyname        	
	 * @param string $dollar        	
	 */
	public function payBonus($companyname, $dollar) {
		// TODO 전체보너스지급
	}
	/**
	 * Leave the company
	 *
	 * @param string $companyname        	
	 * @param string $username        	
	 */
	public function resignCompany($companyname, $username) {
		// TODO 회사사직
	}
	/**
	 * is Plugin Enabled
	 *
	 * @return true|false
	 */
	public function isPluginEnabled() {
		if (! isset ( $this->yml ["PluginData"] ) or ! isset ( $this->yml ["PluginData"] ["isPluginEnabled"] ))
			return false;
		return $this->yml ["PluginData"] ["isPluginEnabled"];
	}
	
	/**
	 * Check whether the user company employees
	 *
	 * @param string $username        	
	 * @param string $companyname        	
	 *
	 * @return true|false
	 */
	public function isWorking($username, $companyname) {
		return isset ( $this->yml ["CompanyData"] [$companyname] ["staff"] [$username] );
	}
	
	/**
	 * Check whether the user is the founder of the company
	 *
	 * @param string $username        	
	 * @param string $companyname        	
	 *
	 * @return true|false
	 */
	public function isFounder($username, $companyname) {
		return ($this->yml ["CompanyData"] [$companyname] ["founder"] == $username);
	}
	/**
	 * It returns a list of companies
	 *
	 * @return Array
	 */
	public function getCompanyList() {
		$list = [ ];
		foreach ( $this->yml ["CompanyData"] as $companyname ) {
			$list [$companyname] = 0;
		}
		return $list;
	}
	/**
	 * It returns the incident waiting list
	 *
	 * @param string $companyname        	
	 *
	 * @return Array|null
	 */
	public function getApplyWaitList($companyname) {
		if (! isset ( $this->yml ["CompanyData"] [$companyname] ["applywaitlist"] ))
			return null;
		return $this->yml ["CompanyData"] [$companyname] ["applywaitlist"];
	}
	/**
	 * It returns information of the company
	 *
	 * @param string $companyname        	
	 *
	 * @return Array|null
	 */
	public function getCompanyData($companyname) {
		if (! isset ( $this->yml ["CompanyData"] [$companyname] ))
			return null;
		return $this->yml ["CompanyData"] [$companyname];
	}
	/**
	 * It returns the company information of the user
	 *
	 * @param string $companyname        	
	 *
	 * @return Array|null
	 */
	public function getUserData() {
		if (! isset ( $this->yml ["UserData"] ))
			return null;
		return $this->yml ["UserData"];
	}
	/**
	 * It returns to start-up costs.
	 *
	 * @return string|null
	 */
	public function getStartUpCost() {
		if (! isset ( $this->yml ["PluginData"] ))
			return null;
		return $this->yml ["PluginData"] ["StartUpCost"];
	}
}

?>