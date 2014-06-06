<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Mag. Tamer Erdogan <tamer.erdogan@univie.ac.at>
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
 * Plugin 'Shibboleth Login' for the 'shibboleth_auth' extension.
 *
 * @author	Mag. Tamer Erdogan <tamer.erdogan@univie.ac.at>
 * @package	TYPO3
 * @subpackage	tx_shibbolethauth
 */
class tx_shibbolethauth_pi1 extends tslib_pibase {
	public $prefixId      = 'tx_shibbolethauth_pi1';		// Same as class name
	public $scriptRelPath = 'pi1/class.tx_shibbolethauth_pi1.php';	// Path to this script relative to the extension dir.
	public $extKey        = 'shibboleth_auth';	// The extension key.
	public $pi_checkCHash = FALSE;
	public $pi_USER_INT_obj = TRUE;

	protected $userIsLoggedIn;	// Is user logged in?
	protected $template;	// holds the template for FE rendering
	protected $noRedirect = FALSE;	// flag for disable the redirect
	protected $logintype;	// logintype (given as GPvar), possible: login, logout
	public $conf;
	
	protected $extConf;
	protected $remoteUser;
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->initPIflexForm();
		$this->pi_USER_INT_obj = TRUE;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if (empty($this->extConf['remoteUser'])) $this->extConf['remoteUser'] = 'REMOTE_USER';
		if (isset($_SERVER['AUTH_TYPE']) && strtolower($_SERVER['AUTH_TYPE']) == 'shibboleth') {
			$this->remoteUser = $_SERVER[$this->extConf['remoteUser']];
		}
		
		$templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : 'EXT:'.$this->extKey.'/pi1/template.html';
		$this->template = $this->cObj->fileResource($templateFile);
		
		// GPvars:
		$this->logintype = t3lib_div::_GP('logintype');
		// Is user logged in?
		$this->userIsLoggedIn = $GLOBALS['TSFE']->loginUser;
		// What to display
		if($this->userIsLoggedIn) {
			$this->remoteUser = $GLOBALS['TSFE']->fe_user->user['username'];
			if ($this->logintype == 'login') {
				$content = $this->showLoginSuccess();
			} else {
				$content = $this->showLogout();
			}
		} else {
			if ($this->logintype == 'logout') {
				$content = $this->showLogoutSuccess();
			} else if ($this->logintype == 'login') {
				$content = $this->showLoginError();
			} else {
				$content = $this->showLogin();
			}
		}
		
		// Redirect Status
		$returnUrl =  t3lib_div::_GP('return_url');
		if ($returnUrl) {
			$this->redirectUrl = $returnUrl;
		} else {
			$this->redirectUrl = t3lib_div::_GP('redirect_url');
		}

		// Process Redirect
		if ($this->userIsLoggedIn && $this->redirectUrl) {
			if (!$GLOBALS['TSFE']->fe_user->cookieId) {
				$content .= $this->cObj->stdWrap($this->pi_getLL('cookie_warning', '', 1), $this->conf['cookieWarning_stdWrap.']);
			} else {
				t3lib_utility_Http::redirect($this->redirectUrl);
			}
		}

		return $this->conf['wrapContentInBaseClass'] ? $this->pi_wrapInBaseClass($content) : $content;
	}
	
	/**
	 * Shows login form
	 *
	 * @return	string		content
	 */
	protected function showLogin() {
		$target = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
		$target = t3lib_div::removeXSS($target);
		if ($this->extConf['forceSSL'] && !t3lib_div::getIndpEnv('TYPO3_SSL')) {
			$target = str_ireplace('http:', 'https:', $target);
			if (!preg_match('#["<>\\\]+#', $target)) {
				t3lib_utility_Http::redirect($target);
			}
		}
		if(stristr($target, '?') === FALSE) $target .= '?';
		else $target .= '&';
		$pid = $this->lConf['page'] ?  $this->lConf['page'] : $this->extConf['storagePid'];
		$target .= 'logintype=login&auth=shibboleth&pid='.$pid;
		$redirectUrl = $this->extConf['loginHandler'] . '?target=' . rawurlencode($target);
		$redirectUrl = t3lib_div::sanitizeLocalUrl($redirectUrl);
		t3lib_utility_Http::redirect($redirectUrl);
	}
	
	protected function showLoginSuccess() {
		// if set, we redirect to the target page. If you want to show a success message, uncomment the redirect below.
		if (!empty($this->lConf['redirect'])) {
			$path = $this->pi_getPageLink($this->lConf['redirect']);
			$fullUrl = t3lib_div::locationHeaderUrl ( $path );
			t3lib_utility_Http::redirect($path);
		}
		// we redirect to the original page. if you want to show a success message, uncomment the redirect below.
		$redirectUrl = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
		$pid = $this->lConf['page'] ?  $this->lConf['page'] : $this->extConf['storagePid'];
		$redirectUrl = preg_replace('/[\?|&]logintype=login&auth=shibboleth&pid='.$pid.'/', '', $redirectUrl);
		$redirectUrl = t3lib_div::sanitizeLocalUrl($redirectUrl);
		t3lib_utility_Http::redirect($redirectUrl);
		
		// show message for successful login. If you don't uncomment the redirects above, this will never be shown.
		$subpart = $this->cObj->getSubpart($this->template, '###TEMPLATE_LOGIN_SUCCESS###');
		
		$markerArray['###STATUS_HEADER###'] = $this->pi_getLL('success_header', '', 1);
		$markerArray['###STATUS_MESSAGE###'] = str_replace('###USER###', $this->remoteUser, $this->pi_getLL('success_message', '', 1));
		$markerArray['###USER###'] = $this->remoteUser;
		
		return $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, $subpartArray);
	}

	protected function showLoginError() {
		$subpart = $this->cObj->getSubpart($this->template, '###TEMPLATE_LOGIN_ERROR###');
		
		$markerArray['###ERROR_HEADER###'] = $this->pi_getLL('error_header', '', 1);
		$markerArray['###ERROR_MESSAGE###'] = $this->pi_getLL('error_message', '', 1);
		if (!empty($_SERVER[$this->extConf['remoteUser']])) $markerArray['###ERROR_MESSAGE###'] .= '<br>'.str_replace('###USER###', $this->remoteUser, $this->pi_getLL('wrong_user', '', 1));
		
		return $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, $subpartArray);
	}

	/**
	 * Shows logout form
	 *
	 * @return	string		The content.
	 */
	protected function showLogout() {
		$subpart = $this->cObj->getSubpart($this->template, '###TEMPLATE_LOGOUT###');
		$subpartArray = array();

		$markerArray['###STATUS_HEADER###'] = $this->pi_getLL('status_header', '', 1);
		$markerArray['###STATUS_MESSAGE###'] = $this->pi_getLL('status_message', '', 1);
		$markerArray['###USER###'] = $this->remoteUser;
		$markerArray['###LEGEND###'] = $this->pi_getLL('logout', '', 1);
		
		$this->conf['linkConfig.']['parameter'] = $GLOBALS['TSFE']->id;
		// should GETvars be preserved?
		if ($this->conf['preserveGETvars'])	{
			$additionalParams .= $this->getPreserveGetVars();
		}
		if ($additionalParams)	{
			$this->conf['linkConfig.']['additionalParams'] =  $additionalParams;
		}
		$markerArray['###ACTION_URI###'] = htmlspecialchars($this->cObj->typolink_url($this->conf['linkConfig.']));
		$markerArray['###LOGOUT_LABEL###'] = $this->pi_getLL('logout', '', 1);
		$markerArray['###NAME###'] = htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name']);
		$pid = $this->lConf['page'] ?  $this->lConf['page'] : $this->extConf['storagePid'];
		$markerArray['###STORAGE_PID###'] = $pid;
		$markerArray['###USERNAME###'] = htmlspecialchars($GLOBALS['TSFE']->fe_user->user['username']);
		$markerArray['###USERNAME_LABEL###'] = $this->pi_getLL('username', '', 1);
		
		return $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, $subpartArray);
	}

	protected function showLogoutSuccess() {
		// Redirect if logoutHandler is set.
		if (!empty($this->extConf['logoutHandler'])) {
			$redirectUrl = $this->extConf['logoutHandler'];
			$redirectUrl = t3lib_div::sanitizeLocalUrl($this->extConf['logoutHandler']);
			t3lib_utility_Http::redirect($redirectUrl);
		}
		// Show logout message if no redirect
		$subpart = $this->cObj->getSubpart($this->template, '###TEMPLATE_LOGOUT_SUCCESS###');
		
		$markerArray['###STATUS_HEADER###'] = $this->pi_getLL('logout', '', 1);
		$markerArray['###STATUS_MESSAGE###'] = $this->pi_getLL('logout_message', '', 1);
		
		return $this->cObj->substituteMarkerArrayCached($subpart, $markerArray, $subpartArray);
	}

	/**
	 * Is used by TS-setting preserveGETvars
	 * possible values are "all" or a commaseperated list of GET-vars
	 * they are used as additionalParams for link generation
	 *
	 * @return	string		additionalParams-string
	 */
	 protected function getPreserveGetVars() {
		$params = '';
		$preserveVars =! ($this->conf['preserveGETvars'] || $this->conf['preserveGETvars']=='all' ? array() : implode(',', (array)$this->conf['preserveGETvars']));
		$getVars = t3lib_div::_GET();

		if (is_array($getVars)) {
			foreach ($getVars as $key => $val) {
				if (stristr($key,$this->prefixId) === FALSE) {
					if (is_array($val)) {
						foreach ($val as $key1 => $val1) {
							if ($this->conf['preserveGETvars'] == 'all' || in_array($key . '[' . $key1 .']', $preserveVars)) {
								$params .= '&' . $key . '[' . $key1 . ']=' . $val1;
							}
						}
					} else {
						if (!in_array($key, array('id','no_cache','logintype','redirect_url','cHash'))) {
							$params .= '&' . $key . '=' . $val;
						}
					}
				}
			}
		}
		return $params;
	}


	private function initPIflexForm() {
		$this->pi_initPIflexForm(); // Init and get the flexform data of the plugin
		$this->lConf = array(); // Setup our storage array...
		// Assign the flexform data to a local variable for easier access
		$piFlexForm = $this->cObj->data['pi_flexform'];
		// Traverse the entire array based on the language...
		// and assign each configuration option to $this->lConf array...
		if (!empty($piFlexForm) and is_array($piFlexForm['data'])) {
			foreach ( $piFlexForm['data'] as $sheet => $data ) {
				foreach ( $data as $lang => $value ) {
					foreach ( $value as $key => $val ) {
						$this->lConf[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
					}
				}
			}
		}
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/shibboleth_auth/pi1/class.tx_shibbolethauth_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/shibboleth_auth/pi1/class.tx_shibbolethauth_pi1.php']);
}

?>
