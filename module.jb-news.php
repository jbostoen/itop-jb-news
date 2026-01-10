<?php


//
// iTop module definition file
//

/** @noinspection PhpUnhandledExceptionInspection */
SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'jb-news/3.2.260110',
	array(
		// Identification
		//
		'label' => 'Feature: Third-party news client and server',
		'category' => 'tools',

		// Setup
		//
		'dependencies' => array(
			'itop-structure/3.2.0',
			'jb-server-communication/3.2.0',
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
			'src/JeffreyBostoenExtensions/News/Helper.php',
			'src/JeffreyBostoenExtensions/News/Provider.php',
			// Client.
			'src/JeffreyBostoenExtensions/News/Client/Base.php',
			// Local server.
			'src/JeffreyBostoenExtensions/News/LocalServer/ServerExtension.php',
			// Remote servers.
			'src/JeffreyBostoenExtensions/News/RemoteServers/Base.php',
			'src/JeffreyBostoenExtensions/News/RemoteServers/JeffreyBostoenNews.php',
			// API Base.
			'src/JeffreyBostoenExtensions/News/Base/Icon.php',
			'src/JeffreyBostoenExtensions/News/Base/Message.php',
			'src/JeffreyBostoenExtensions/News/Base/Translation.php',
			// 1.0.0
			'src/JeffreyBostoenExtensions/News/v100/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/v100/Message.php',
			'src/JeffreyBostoenExtensions/News/v100/MessagesTrait.php',
			// 1.1.0
			'src/JeffreyBostoenExtensions/News/v110/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/v110/Message.php',
			// 2.0.0
			'src/JeffreyBostoenExtensions/News/v200/HttpResponseGetMessagesForInstance.php',
			'src/JeffreyBostoenExtensions/News/v200/Message.php',
			'src/JeffreyBostoenExtensions/News/v200/MessagesTrait.php',
			
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
			'server' => false,
			'frequency' => 60,
			'ttl' => 3600,
			'oql_target_users' => 'SELECT User',
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
