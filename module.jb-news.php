<?php


//
// iTop module definition file
//

/** @noinspection PhpUnhandledExceptionInspection */
SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'jb-news/2.7.211212',
	array(
		// Identification
		//
		'label' => 'Jeffrey Bostoen - News client',
		'category' => 'tools',

		// Setup
		//
		'dependencies' => array(
			'itop-welcome-itil/2.4.0',
		),
		'mandatory' => false,
		'visible' => true,
		'auto_select' => true,

		// Components
		//
		'datamodel' => array(
			'model.jb-news.php',
			'src/Core/NewsClient.php',
			'src/Core/NewsRoomHelper.php',
			'src/Core/NewsRoomProvider.php',
			'src/Core/NewsRoomWebPage.php',
			'src/Core/NewsServer.php',
			'src/Core/ProcessThirdPartyNews.php',
		),
		'webservice' => array(),
		'data.struct' => array(// add your 'structure' definition XML files here,
		),
		'data.sample' => array(// add your sample data XML files here,
		),

		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any

		// Default settings
		//
		'settings' => array(
			// Module specific settings go here, if any
			'enabled' => true,
			'client' => true,
			'frequency' => 60,
			'server' => false,
			'ttl' => 3600,
			'source_url' => 'https://support.jeffreybostoen.be/pages/exec.php?&exec_module=jb-news&exec_page=index.php&exec_env=production',
		),
	)
);


