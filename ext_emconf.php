<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "shibboleth_auth".
 *
 * Auto generated 27-06-2016 13:14
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Shibboleth Authentication',
	'description' => 'Shibboleth Single Sign On Authentication (BE & FE). The FE Users will be imported automatically into the configured storage pid.',
	'category' => 'services',
	'version' => '2.6.5',
	'state' => 'stable',
	'uploadfolder' => true,
	'createDirs' => '',
	'clearcacheonload' => true,
	'author' => 'Tamer Erdogan, Richard Rode',
	'author_email' => 'typo3@univie.ac.at',
	'author_company' => '',
	'constraints' => 
	array (
		'depends' => 
		array (
			'typo3' => '4.5.0-6.2.999',
		),
		'conflicts' => 
		array (
		),
		'suggests' => 
		array (
		),
	),
);

