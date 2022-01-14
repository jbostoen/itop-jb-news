<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220114
 *
 */



@include_once('../approot.inc.php');
@include_once('../../approot.inc.php');
@include_once('../../../approot.inc.php');

require_once(APPROOT.'/application/application.inc.php');

// iTop 3 makes WebPage auto-loadable
if(defined('ITOP_VERSION') == true && version_compare(ITOP_VERSION, '3.0', '<')) {
	
	require_once(APPROOT.'/application/webpage.class.inc.php');
	require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');

	class AjaxPage extends ajax_page {
	}
	
}

// Still classic
require_once(APPROOT.'/application/loginwebpage.class.inc.php');

require_once(APPROOT.'env-'.utils::GetCurrentEnvironment().'/jb-news/src/Core/NewsRoomWebPage.php');
require_once(APPROOT.'env-'.utils::GetCurrentEnvironment().'/jb-news/src/Core/NewsServer.php');

use \jb_itop_extensions\NewsClient\NewsRoomWebPage;
use \jb_itop_extensions\NewsClient\NewsServer;
use \jb_itop_extensions\NewsClient\NewsRoomHelper;

try {
	
	require_once APPROOT . '/application/startup.inc.php';
	
	// Check user rights and prompt if needed
	$sOperation = utils::ReadParam('operation', '');
	
	$oPage = new AjaxPage('');
	$oPage->no_cache();

	// Retrieve global parameters
	$sVersion = utils::ReadParam('api_version', NewsroomHelper::DEFAULT_API_VERSION);
	$sAppName = utils::ReadParam('app_name', NewsroomHelper::DEFAULT_APP_NAME, false, 'raw_data');
	$sAppVersion = utils::ReadParam('app_version', NewsroomHelper::DEFAULT_APP_VERSION, false, 'raw_data');

	// Check global parameters
	if(empty($sOperation) || empty($sVersion)) {
		throw new Exception('Missing mandatory parameters "operation" and "version".');
	}

	// Check operation parameters
	switch($sOperation) {
		
		case 'get_messages_for_instance':
		
			$sInstanceHash = utils::ReadParam('instance_hash', '');
			$sInstanceHash2 = utils::ReadParam('instance_hash2', '');
		
			// Check parameters
			if($sInstanceHash == '' || $sInstanceHash2 == '') {
				throw new Exception('Missing parameters for requested operation.');
			}
		
			if(utils::GetCurrentModuleSetting('enabled', false) == false) {

				$oPage->add('News extension not enabled.');
				break;

			}
			elseif(utils::GetCurrentModuleSetting('server', false) == false) {
				
				$oPage->add('Server not active.');
				break;
				
			}
			else {
		
				// Retrieve messages
				$aMessages = NewsServer::GetMessagesForInstance();
				$sMessagesJSON = json_encode($aMessages);

				// Prepare response
				$sOutput = $sMessagesJSON;

				// Regular JSON here, not JSONP
				$oPage->SetContentType('application/json');
				$oPage->add($sOutput);
				break;
				
			}
			else {
				
				$oPage->add('Server not active.');
				break;
			}
			
		default:
			$oPage->p('Invalid query.');
			break;
	}

	$oPage->output();
}
catch(Exception $oException) {
	
	// Note: Transform to cope with XSS attacks
	echo htmlentities($oException->GetMessage(), ENT_QUOTES, 'utf-8');
	IssueLog::Error($oException->getMessage() . "\nDebug trace:\n" . $oException->getTraceAsString());
	
}
