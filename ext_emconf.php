<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "shibboleth_auth".
 *
 * Auto generated 05-06-2014 08:06
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Shibboleth Authentication',
	'description' => 'Shibboleth Single Sign On Authentication (BE & FE). The FE Users will be imported automatically into the configured storage pid.',
	'category' => 'services',
	'shy' => 1,
	'version' => '2.6.2',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 1,
	'lockType' => '',
	'author' => 'Tamer Erdogan, Richard Rode',
	'author_email' => 'typo3@univie.ac.at',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-6.2.999',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
	'_md5_values_when_last_written' => 'a:16:{s:9:"ChangeLog";s:4:"25e7";s:21:"ext_conf_template.txt";s:4:"36e8";s:12:"ext_icon.gif";s:4:"eb04";s:17:"ext_localconf.php";s:4:"51be";s:14:"ext_tables.php";s:4:"fba4";s:18:"locallang_conf.xml";s:4:"3ca6";s:16:"locallang_db.xml";s:4:"bf65";s:10:"README.txt";s:4:"f9d2";s:19:"shibboleth_logo.png";s:4:"aac6";s:14:"doc/manual.sxw";s:4:"7131";s:42:"hooks/class.tx_shibbolethauth_userauth.php";s:4:"b47b";s:35:"pi1/class.tx_shibbolethauth_pi1.php";s:4:"54e1";s:16:"pi1/flexform.xml";s:4:"ccdd";s:17:"pi1/locallang.xml";s:4:"8e2b";s:17:"pi1/template.html";s:4:"9542";s:35:"sv1/class.tx_shibbolethauth_sv1.php";s:4:"864f";}',
);

?>