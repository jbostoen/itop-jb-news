<?php


//
// iTop module definition file
//

/** @noinspection PhpUnhandledExceptionInspection */
SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'jb-news-client/1.0.0',
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

		// Components
		//
		'datamodel' => array(
			'model.jb-news-client.php',
			'src/Core/NewsClient.php',
			'src/Core/NewsRoomHelper.php',
			'src/Core/NewsRoomProvider.php',
			'src/Core/NewsRoomWebPage.php',
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
			'time' => '15:06',
			'url' => 'https://127.0.0.1:8182/test-newsroom/demo.php',
		),
	)
);

