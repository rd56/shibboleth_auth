<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Tamer Erdoğan <tamer.erdogan@univie.ac.at>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Service "Shibboleth-Authentication" for the "tx_shibbolethauth" extension.
 *
 * @author	Tamer Erdoğan <tamer.erdogan@univie.ac.at>
 * @package	TYPO3
 * @subpackage	tx_shibbolethauth
 */
class tx_shibbolethauth_sv1 extends tx_sv_authbase {
	public $prefixId = 'tx_shibbolethauth_sv1';		// Same as class name
	public $scriptRelPath = 'sv1/class.tx_shibbolethauth_sv1.php';	// Path to this script relative to the extension dir.
	public $extKey = 'shibboleth_auth';	// The extension key.
	public $pObj;
	
	protected $extConf;
	protected $remoteUser;
	
	/**
	 * Inits some variables
	 *
	 * @return	void
	 */
	function init() {
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if (empty($this->extConf['remoteUser'])) $this->extConf['remoteUser'] = 'REMOTE_USER';
		$this->remoteUser = $_SERVER[$this->extConf['remoteUser']];
		
		return parent::init();
	}
	
	/**
	 * Initialize authentication service
	 *
	 * @param	string		Subtype of the service which is used to call the service.
	 * @param	array		Submitted login form data
	 * @param	array		Information array. Holds submitted form data etc.
	 * @param	object		Parent object
	 * @return	void
	 */
	function initAuth($mode, $loginData, $authInfo, $pObj) {
		if (defined('TYPO3_cliMode')) {
			return parent::initAuth($mode, $loginData, $authInfo, $pObj);
		}
		
		$this->login = $loginData;
		if(empty($this->login['uname']) && empty($this->remoteUser)) {
			$target = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
			$target = t3lib_div::removeXSS($target);
			if ($this->extConf['forceSSL'] && !t3lib_div::getIndpEnv('TYPO3_SSL')) {
				$target = str_ireplace('http:', 'https:', $target);
				if (!preg_match('#["<>\\\]+#', $target)) {
					t3lib_utility_Http::redirect($target);
				}
			}
			
			if (TYPO3_MODE == 'FE') {
				if(stristr($target, '?') === FALSE) $target .= '?';
				else $target .= '&';
				$pid = t3lib_div::_GP('pid') ? t3lib_div::_GP('pid') : $this->extConf['storagePid'];
				$target .= 'logintype=login&pid='.$pid;
			}
			$redirectUrl = $this->extConf['loginHandler'] . '?target=' . rawurlencode($target);
			$redirectUrl = t3lib_div::sanitizeLocalUrl($redirectUrl);
			
			t3lib_utility_Http::redirect($redirectUrl);
		} else {
			$loginData['status'] = 'login';
			parent::initAuth($mode, $loginData, $authInfo, $pObj);
		}
	}
	
	function getUser() {
		$user = FALSE;
		if ($this->login['status']=='login' && $this->isShibbolethLogin() && empty($this->login['uname'])) {
			if ($this->authInfo['loginType'] == 'FE' && t3lib_div::_GP('auth') != 'shibboleth') return FALSE;
			$user = $this->fetchUserRecord($this->remoteUser);
			
			if(!is_array($user) || empty($user)) {
				$pid = t3lib_div::_GP('pid') ? t3lib_div::_GP('pid') : $this->extConf['storagePid'];
				if ($this->authInfo['loginType'] == 'FE' && !empty($this->remoteUser) && $this->extConf['enableAutoImport'] && $pid == $this->extConf['storagePid']) {
					$this->importFEUser();
				} else {
					$user = FALSE;
					// Failed login attempt (no username found)
					$this->writelog(255, 3, 3, 2,
						"Login-attempt from %s (%s), username '%s' not found!",
						Array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->remoteUser));
					t3lib_div::sysLog(sprintf("Login-attempt from %s (%s), username '%s' not found!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->remoteUser), $this->extKey, 0);
				}
			} else {
				if ($this->authInfo['loginType'] == 'FE' && $this->extConf['enableAutoImport']) {
					$this->updateFEUser();
				}
				if ($this->writeDevLog) t3lib_div::devLog('User found: ' . t3lib_div::arrayToLogString($user, array($this->db_user['userid_column'], $this->db_user['username_column'])), $this->extKey);
			}
			if ($this->authInfo['loginType'] == 'FE') {
				// the fe_user was updated, it should be fetched again.
				$user = $this->fetchUserRecord($this->remoteUser);
			}
		}
		
		if (!defined('TYPO3_cliMode') && $this->authInfo['loginType'] == 'BE' && $this->extConf['onlyShibbolethBE'] && empty($user)) {
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['onlyShibbolethFunc'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['onlyShibbolethFunc'] as $_classRef) {
					$_procObj =& t3lib_div::getUserObj($_classRef);
					$_procObj->onlyShibbolethFunc($this->remoteUser);
				}
			} else {
				throw new \RuntimeException('Login error: User ('.$this->remoteUser.') not found!');
			}
			foreach($_COOKIE as $key=>$val) unset($_COOKIE[$key]);
			exit;
		}
		
		return $user;
	}
	
	/**
	 * Authenticate a user (Check various conditions for the user that might invalidate its authentication, eg. password match, domain, IP, etc.)
	 *
	 * Will return one of following authentication status codes:
	 *  - 0 - authentication failure
	 *  - 100 - just go on. User is not authenticated but there is still no reason to stop
	 *  - 200 - the service was able to authenticate the user
	 *
	 * @param	array		Array containing FE user data of the logged user.
	 * @return	integer		authentication statuscode, one of 0,100 and 200
	 */
	function authUser($user) {
		$OK = 100;
		
		if (defined('TYPO3_cliMode')) {
			$OK = 100;
		} else if (($this->authInfo['loginType'] == 'FE') && !empty($this->login['uname'])) {
			$OK = 100;
		} else if ($this->isShibbolethLogin() && !empty($user)
			&& ($this->remoteUser == $user[$this->authInfo['db_user']['username_column']])) {
			$OK = 200;
			
			if ($user['lockToDomain'] && $user['lockToDomain']!=$this->authInfo['HTTP_HOST']) {
				// Lock domain didn't match, so error:
				if ($this->writeAttemptLog) {
					$this->writelog(255, 3, 3, 1,
						"Login-attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!",
						array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $user[$this->authInfo['db_user']['username_column']], $user['lockToDomain'], $this->authInfo['HTTP_HOST']));
					t3lib_div::sysLog(sprintf( "Login-attempt from %s (%s), username '%s', locked domain '%s' did not match '%s'!",
						$this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $user[$this->authInfo['db_user']['username_column']], $user['lockToDomain'], $this->authInfo['HTTP_HOST']), $this->extKey, 0);
				}
				$OK = 0;
			}
		} else {
			// Failed login attempt (wrong password) - write that to the log!
			if ($this->writeAttemptLog) {
				$this->writelog(255, 3, 3, 1,
					"Login-attempt from %s (%s), username '%s', password not accepted!",
					array($this->info['REMOTE_ADDR'], $this->info['REMOTE_HOST'], $this->remoteUser));
				t3lib_div::sysLog(sprintf("Login-attempt from %s (%s), username '%s', password not accepted!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->remoteUser), $this->extKey, 0 );
			}
			$OK = 0;
		}
		
		return $OK;
	}
	
	protected function importFEUser() {
		$this->writelog(255, 3, 3, 2, "Importing user %s!", array($this->remoteUser));
		
		$user = array('crdate' => time(),
			'tstamp' => time(),
			'pid' => $this->extConf['storagePid'],
			'username' => $this->remoteUser,
			'password' => md5(t3lib_div::shortMD5(uniqid(rand(), TRUE))),
			'email' => $this->getServerVar($this->extConf['mail']),
			'name' => $this->getServerVar($this->extConf['displayName']),
			'usergroup' => $this->getFEUserGroups(),
			);

		// parse additional attrb
		if ($this->extConf['additionalAttr'] != NULL){
			$additionalAttr = explode(',', $this->extConf['additionalAttr']);
			foreach ($additionalAttr as $attr) {
				$attrbCont = explode('=', $attr);
				$user[$attrbCont[0]] = $this->getServerVar($attrbCont[1]);
			}
		}
		// end of parse
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->authInfo['db_user']['table'], $user);
	}
	
	/**
	 * @return	boolean
	 */
	protected function updateFEUser() {
		$this->writelog(255, 3, 3, 2, "Updating user %s!", array($this->remoteUser));
		
		$pid = t3lib_div::_GP('pid') ? t3lib_div::_GP('pid') : $this->extConf['storagePid'];
		$where = "username = '".$GLOBALS['TYPO3_DB']->quoteStr($this->remoteUser, $this->authInfo['db_user']['table'])."' AND pid = '".intval($pid)."'";

		// update existing feusergroup with group from Shibboleth
		$where2 = ' AND deleted = 0';
		$dbres2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('usergroup', $this->authInfo['db_user']['table'],$where.$where2);
		if ($row2 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbres2)) {
			$currentGroups = $row2['usergroup'];
		}
		if ($dbres2) $GLOBALS['TYPO3_DB']->sql_free_result($dbres2);
		$currentGroupsA = explode(',', $currentGroups);
		$retGroupsA = explode(',', $this->getFEUserGroups());
		foreach ($retGroupsA as $rg) {
			if (!in_array($rg, $currentGroupsA)) $currentGroupsA[] = $rg;
		}
		if ($this->extConf['onlyAffiliationGroups']) {
			// remove all groups from shibboleth folder, that are not in the affiliations anymore
			if ($pid !=  $this->extConf['storagePid']) {
				$dbres3 =  $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid','fe_groups', "uid IN ($currentGroups) and pid='".intval($this->extConf['storagePid'])."'");
				$shibgroups = array();
				while ($row3 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbres3)) {
					$shibgroups[] = $row3['uid'];
				}
				foreach ($currentGroupsA as $key=>$cg) {
					if (!in_array($cg, $retGroupsA) and in_array($cg, $shibgroups)) unset($currentGroupsA[$key]);
				}
			} else {
				foreach ($currentGroupsA as $key=>$cg) {
					if (!in_array($cg, $retGroupsA)) unset($currentGroupsA[$key]);
				}
			}
		}
		$newGroups = implode(',', $currentGroupsA);
		// end of update feusergroup

		$user = array('tstamp' => time(),
			'username' => $this->remoteUser,
			'password' => t3lib_div::shortMD5(uniqid(rand(), TRUE)),
			'email' => $this->getServerVar($this->extConf['mail']),
			'name' => $this->getServerVar($this->extConf['displayName']),
			'usergroup' => $newGroups,
			);

		// parse additional attrb
		if ($this->extConf['additionalAttr'] != NULL){
			$additionalAttr = explode(',', $this->extConf['additionalAttr']);
			foreach ($additionalAttr as $attr) {
				$attrbCont = explode('=', $attr);
				$user[$attrbCont[0]] = $this->getServerVar($attrbCont[1]);
			}
		}
		// end of parse
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->authInfo['db_user']['table'], $where, $user);
	}
	
	protected function getFEUserGroups() {
		$feGroups = array();
		$eduPersonAffiliation = $this->getServerVar($this->extConf['eduPersonAffiliation']);
		if (empty($eduPersonAffiliation)) {
			$eduPersonAffiliation = $this->extConf['defaultGroup'];
		} else {
			$affiliation = explode(';', $eduPersonAffiliation);
			if (!empty($this->extConf['eduPersonAffiliationRegExPattern'])) {
				array_walk($affiliation, create_function('&$v', '$v = preg_replace("'.$this->extConf['eduPersonAffiliationRegExPattern'].'", "'.$this->extConf['eduPersonAffiliationRegExReplace'].'", $v);'));
			}
			array_walk($affiliation, create_function('&$v', '$v = preg_replace("/@.*/", "", $v);'));
			// parse only cn value
			array_walk($affiliation, create_function('&$v', 'preg_match("@^(?:cn=)?([^,]+)@i", $v, $matches);$v=$matches[1];'));

			// insert the affiliations in fe_groups if they are not there.
			foreach ($affiliation as $title) {
				$dbres = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, title',
					$this->authInfo['db_groups']['table'],
					"deleted = 0 AND pid = '".intval($this->extConf['storagePid']) . "' AND title = '".$GLOBALS['TYPO3_DB']->quoteStr($title, $this->authInfo['db_groups']['table'])."'");
				if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbres)) {
					$feGroups[] = $row['uid'];
				} else {
					$group = array('title' => $title, 'pid' => $this->extConf['storagePid']);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->authInfo['db_groups']['table'], $group);
					$feGroups[] = $GLOBALS['TYPO3_DB']->sql_insert_id();
				}
				if ($dbres) $GLOBALS['TYPO3_DB']->sql_free_result($dbres);
			}
		}
		
		// Hook for any additional fe_groups
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['getFEUserGroups'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['getFEUserGroups'] as $_classRef) {
				$_procObj =& t3lib_div::getUserObj($_classRef);
				$feGroups = $_procObj->getFEUserGroups($feGroups);
			}
		}
		
		return implode(',', $feGroups);
	}
	
	/**
	 * @return	boolean
	 */
	protected function isShibbolethLogin() {
		return isset($_SERVER['AUTH_TYPE']) && (strtolower($_SERVER['AUTH_TYPE']) == 'shibboleth') && !empty($this->remoteUser);
	}
	
	protected function getServerVar($key, $prefix='REDIRECT_') {
		if (isset($_SERVER[$key])) {
			return $_SERVER[$key];
		} else if (isset($_SERVER[$prefix.$key])) {
			return $_SERVER[$prefix.$key];
		} else {
			foreach($_SERVER as $k=>$v) {
				if ($key == str_replace($prefix, '', $k)) {
					return $v;
				}
			}
		}
		return NULL;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/shibboleth_auth/sv1/class.tx_shibbolethauth_sv1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/shibboleth_auth/sv1/class.tx_shibbolethauth_sv1.php']);
}

?>
