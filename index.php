<?php


use \jb_itop_extensions\NewsClient\Common\Helper\NewsroomHelper;

@include_once '../approot.inc.php';
@include_once '../../approot.inc.php';
@include_once '../../../approot.inc.php';
require_once APPROOT.'/application/application.inc.php';
require_once APPROOT.'/application/webpage.class.inc.php';
require_once(APPROOT.'/application/itopwebpage.class.inc.php');
require_once(APPROOT.'/application/loginwebpage.class.inc.php');
require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');
require_once(APPROOT.'env-'.utils::GetCurrentEnvironment().'/jb-news-client/src/Core/NewsRoomWebPage.php');
require_once(APPROOT.'env-'.utils::GetCurrentEnvironment().'/jb-news-client/src/Core/NewsServer.php');

use \jb_itop_extensions\NewsClient\NewsRoomWebPage;
use \jb_itop_extensions\NewsClient\NewsServer;

try {
	
	require_once APPROOT . '/application/startup.inc.php';
	
	// Check user rights and prompt if needed
	$sLoginMessage = LoginWebPage::DoLogin();

	$oPage = new ajax_page('');
	$oPage->no_cache();

	// Retrieve global parameters
	$sOperation = utils::ReadParam('operation', '');
	$sVersion = utils::ReadParam('api_version', NewsroomHelper::DEFAULT_API_VERSION);
	$sAppName = utils::ReadParam('app_name', NewsroomHelper::DEFAULT_APP_NAME, false, 'raw_data');
	$sAppVersion = utils::ReadParam('app_version', NewsroomHelper::DEFAULT_APP_VERSION, false, 'raw_data');

	// Check global parameters
	if(empty($sOperation) || empty($sVersion)) {
		throw new Exception('Missing mandatory parameters "operation" and "version".');
	}

	// Check operation parameters
	switch($sOperation) {
		
		case 'fetch':
		
			$sCallback = utils::ReadParam('callback', '');

			// Check parameters
			if(empty($sCallback))
			{
				throw new Exception('Missing parameters for requested operation.');
			}
			break;

		case 'redirect':
		
			$iMessageId = (int) utils::ReadParam('message_id', 0);

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
			$aMessages = NewsroomHelper::GetUnreadMessagesForUser();
			$sMessagesJSON = json_encode($aMessages);

			// Prepare response
			$sOutput = $sCallback . '(' . $sMessagesJSON . ')';

			$oPage->SetContentType('application/jsonp');
			echo $sOutput;
			break;

		case 'mark_all_as_read':
		
			// Mark messages as read
			$iMessageCount = NewsroomHelper::MarkAllMessagesAsReadForUser();
			$sMessageCountJSON = json_encode(array(
				'counter' => $iMessageCount,
				'message' => $iMessageCount . ' message(s) marked as viewed',
			));

			// Prepare response
			$sOutput = $sCallback . '(' . $sMessageCountJSON . ')';

			$oPage->SetContentType('application/jsonp');
			echo $sOutput;
			break;

		case 'view_all':
		
			$oPage = new NewsRoomWebPage('All messages');
			NewsroomHelper::MakeAllMessagesPage($oPage);
			break;

		case 'redirect':
		
			// Mark message as read
			$bMarked = NewsroomHelper::MarkMessageAsReadForUser($iMessageId, $oUser);

			// Redirect to final URL
			$oMessage = MetaModel::GetObject('ThirdPartyNewsroomMessage', $iMessageId, true, true);
			header('Location: ' . $oMessage->Get('url'));
			break;
			
		case 'get_messages_for_instance':
		
			// Retrieve messages
			$aMessages = NewsServer::GetMessagesForInstance();
			$sMessagesJSON = json_encode($aMessages);

			// Prepare response
			$sOutput = $sMessagesJSON;

			$oPage->SetContentType('application/jsonp');
			echo $sOutput;
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
