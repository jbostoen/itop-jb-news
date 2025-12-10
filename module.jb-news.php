<?php


//
// iTop module definition file
//

/** @noinspection PhpUnhandledExceptionInspection */
SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'jb-news/3.2.250909',
	array(
		// Identification
		//
		'label' => 'Feature: Third-party news client and server',
		'category' => 'tools',

		// Setup
		//
		'dependencies' => array(
			'itop-structure/3.2.0',
		),
		'mandatory' => true,
		'visible' => true,
		'auto_select' => true,

		'installer' => 'NewsInstaller',

		// Components
		//
		'datamodel' => array(
			'model.jb-news.php',
			'vendor/autoload.php',
			// iTop doesn't handle autoloading very well sometimes (e.g. interfaces).
			'src/JeffreyBostoenExtensions/News/BackgroundProcess.php',
			'src/JeffreyBostoenExtensions/News/Client.php',
			'src/JeffreyBostoenExtensions/News/FrontEndReadyScripts.php',
			'src/JeffreyBostoenExtensions/News/Helper.php',
			'src/JeffreyBostoenExtensions/News/Provider.php',
			'src/JeffreyBostoenExtensions/News/JsonPage.php',
			'src/JeffreyBostoenExtensions/News/Page.php',
			'src/JeffreyBostoenExtensions/News/ServerWorker.php',
			'src/JeffreyBostoenExtensions/News/SourceJeffreyBostoen.php',
			// API Base.
			'src/JeffreyBostoenExtensions/News/Base/HttpRequest.php',
			'src/JeffreyBostoenExtensions/News/Base/HttpResponse.php',
			'src/JeffreyBostoenExtensions/News/Base/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/Base/Icon.php',
			'src/JeffreyBostoenExtensions/News/Base/Translation.php',
			// 1.0.0
			'src/JeffreyBostoenExtensions/News/v100/HttpRequest.php',
			'src/JeffreyBostoenExtensions/News/v100/HttpResponse.php',
			'src/JeffreyBostoenExtensions/News/v100/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/v100/Message.php',
			// 1.1.0
			'src/JeffreyBostoenExtensions/News/v110/HttpRequest.php',
			'src/JeffreyBostoenExtensions/News/v110/HttpResponse.php',
			'src/JeffreyBostoenExtensions/News/v110/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/v110/Message.php',
			// 2.0.0
			'src/JeffreyBostoenExtensions/News/v200/HttpRequest.php',
			'src/JeffreyBostoenExtensions/News/v200/HttpResponse.php',
			'src/JeffreyBostoenExtensions/News/v200/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/v200/Message.php',
			
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
			'trace_log' => false,
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


/**
 * Class NewsInstaller.
 * 
 */
class NewsInstaller extends ModuleInstallerAPI {
	
	/**
	 * @inheritDoc
	 */
	public static function BeforeDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion) {
		
		// Only applies to upgrades.
		if($sPreviousVersion != '' && version_compare($sPreviousVersion, '3.2.250701', '<')) {
		
			try {

				$aMap = [
					'thirdparty_newsroommessage' => 'news_3rdparty_message',
					'thirdparty_newsroommessage_translation' => 'news_3rdparty_message_translation',
					'thirdparty_newsroommessage_readstatus' => 'news_3rdparty_message_status',
				];

				foreach($aMap as $sOrigName => $sNewName) {

					// Rename table in DB.
					static::RenameTableInDB($sOrigName, $sNewName);

				}

			}
			catch(Exception $e) {
				// Fail gracefully.
			}
			
		}

	}

}
