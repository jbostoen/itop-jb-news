<?php


//
// iTop module definition file
//

/** @noinspection PhpUnhandledExceptionInspection */
SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'jb-news/2.7.221226',
	array(
		// Identification
		//
		'label' => 'Feature: Third Party News Provider - Jeffrey Bostoen',
		'category' => 'tools',

		// Setup
		//
		'dependencies' => array(
			'itop-welcome-itil/2.4.0 || itop-structure/3.0.0',
		),
		'mandatory' => true,
		'visible' => true,
		'auto_select' => true,

		// Components
		//
		'datamodel' => array(
			'model.jb-news.php',
			'src/Core/NewsClient.php',
			'src/Core/NewsClientFrontend.php',
			'src/Core/NewsRoomCSS.php',
			'src/Core/NewsRoomHelper.php',
			'src/Core/NewsRoomProvider.php',
			'src/Core/NewsRoomWebPage.php',
			'src/Core/NewsServer.php',
			'src/Core/ProcessThirdPartyNews.php',
			'src/Core/NewsJeffreyBostoen.php',
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
			'oql_target_users' => 'SELECT User',
			'sodium' => [
				'private_key_crypto_sign' => '/somepath/sodium_sign_priv.key',
				'private_key_crypto_box' => '/somepath/sodium_box_priv.key',
				'public_key_crypto_box' => '/somepath/sodium_box_pub.key',
			]
		),
	)
);


