<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
 *
 */

// This is the endpoint for iTop users, to retrieve messages from their own instance.

@include_once('../approot.inc.php');
@include_once('../../approot.inc.php');
@include_once('../../../approot.inc.php');


require_once(APPROOT.'/application/application.inc.php');

// use \DownloadPage;

// iTop 3 makes WebPage auto-loadable
if(defined('ITOP_VERSION') == true && version_compare(ITOP_VERSION, '3.0', '<')) {
	
	require_once(APPROOT.'/application/webpage.class.inc.php');
	require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');

	
}

// Still classic
require_once(APPROOT.'/application/loginwebpage.class.inc.php');

use \jb_itop_extensions\NewsProvider\NewsRoomWebPage;
use \jb_itop_extensions\NewsProvider\NewsServer;
use \jb_itop_extensions\NewsProvider\NewsRoomHelper;

try {
	
	require_once APPROOT . '/application/startup.inc.php';
	
	// Check user rights and prompt if needed
	$sOperation = utils::ReadParam('operation', '', false, 'parameter');
	
	// Authentication is required
	$sLoginMessage = LoginWebPage::DoLogin();
	
	if(class_exists('DownloadPage') == true) {
		// Modern 3.0
		$oPage = new DownloadPage('');
		$oPage->SetContentType('application/json');
	}
	else {
		// Legacy 2.7
		$oPage = new ajax_page('');
		$oPage->no_cache();
	}
	
	// Retrieve global parameters
	$sVersion = utils::ReadParam('api_version', NewsRoomHelper::DEFAULT_API_VERSION, false, 'raw_data');
	$sAppName = utils::ReadParam('app_name', NewsRoomHelper::DEFAULT_APP_NAME, false, 'raw_data');
	$sAppVersion = utils::ReadParam('app_version', NewsRoomHelper::DEFAULT_APP_VERSION, false, 'raw_data');

	// Check global parameters
	if(empty($sOperation) || empty($sVersion)) {
		throw new Exception('Missing mandatory parameters "operation" and "version".');
	}

	// Check operation parameters
	switch($sOperation) {
		
		case 'fetch':
		case 'mark_all_as_read':
		
			$sCallback = utils::ReadParam('callback', '', false, 'raw_data');

			// Check parameters
			if(empty($sCallback))
			{
				throw new Exception('Missing parameters for requested operation.');
			}
			break;

		case 'redirect':
		
			$iMessageId = (int) utils::ReadParam('message_id', 0, false, 'integer');
			
			// Check parameters
			if(empty($iMessageId))
			{
				throw new Exception('Missing parameters for requested operation.');
			}
			break;

		case 'view_all':

			break;
	}

	// Execute operation
	switch($sOperation) {
		
		case 'fetch':
		
			// Retrieve messages
			$aMessages = NewsRoomHelper::GetUnreadMessagesForUser();
			$sMessagesJSON = json_encode($aMessages);

			// Prepare response
			$sOutput = $sCallback . '(' . $sMessagesJSON . ')';

			$oPage->SetContentType('application/json');
			$oPage->add($sOutput);
			break;

		case 'mark_all_as_read':
		
			// Mark messages as read
			$iMessageCount = NewsRoomHelper::MarkAllMessagesAsReadForUser();
			$sMessageCountJSON = json_encode(array(
				'counter' => $iMessageCount,
				'message' => $iMessageCount . ' message(s) marked as read',
			));

			// Prepare response
			$sOutput = $sCallback . '(' . $sMessageCountJSON . ')';

			$oPage->SetContentType('application/json');
			$oPage->add($sOutput);
			break;

		case 'view_all':
		
			$oPage = new NewsRoomWebPage('All messages');
			NewsRoomHelper::MakeAllMessagesPage($oPage);
			NewsRoomHelper::MarkAllMessagesAsReadForUser(); // Open for discussion: when all messages are rendered on an overview page: should they be marked as read?
			break;

		case 'redirect':
		
			// Mark message as read upon clicking to see the details
			$bMarked = NewsRoomHelper::MarkMessageAsReadForUser($iMessageId);

			// Redirect to final URL
			$oMessage = MetaModel::GetObject('ThirdPartyNewsRoomMessage', $iMessageId);
			
			if($oMessage !== null && NewsRoomHelper::MessageIsApplicable($oMessage) == true) {
				
				$oTranslation = NewsRoomHelper::GetTranslationForUser($oMessage);
				header('Location: '.$oTranslation->Get('url'));
			
			}
			
			break;
			
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
