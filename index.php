<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250712
 *
 */

// This is the endpoint for iTop users, to retrieve messages from their own instance.

@include_once('../approot.inc.php');
@include_once('../../approot.inc.php');
@include_once('../../../approot.inc.php');

use JeffreyBostoenExtensions\News\{
	Client,
    eApiVersion,
	eOperation,
	eOperationMode,
    eUserOperation,
    Helper,
	JsonPage,
	Page
};


use Combodo\iTop\Application\WebPage\{
	WebPage
};

// Still classic.
require_once(APPROOT.'/application/application.inc.php');
require_once APPROOT.'/application/startup.inc.php';
require_once(APPROOT.'/application/loginwebpage.class.inc.php');


try {
	
	
	
	// Check user rights and prompt if needed.
	$sOperation = utils::ReadParam('operation', '', false, 'parameter');
	$eUserOperation = eUserOperation::tryFrom($sOperation);
	
	// Authentication is required
	$sLoginMessage = LoginWebPage::DoLogin();
	
	
	$sApiVersion = utils::ReadParam('api_version', eApiVersion::v1_1_0, false, 'raw_data');
	$sAppName = utils::ReadParam('app_name', Helper::DEFAULT_APP_NAME, false, 'raw_data');
	$sAppVersion = utils::ReadParam('app_version', Helper::DEFAULT_APP_VERSION, false, 'raw_data');

	// - Check the parameters that are required with each call.

		if(empty($sOperation) || empty($sApiVersion)) {
			Helper::Trace('Missing mandatory parameters "operation" and "version".');
			throw new Exception('Missing mandatory parameters "operation" and "version".');
		}

		if($eUserOperation === null) {
			Helper::Trace('Invalid operation requested: "%1$s"', $sOperation);
			throw new Exception('Invalid operation requested: '.$sOperation);
		}

	// - For the given operation: Check if the required parameters are set.
		
		switch($eUserOperation) {
			
			case eUserOperation::FetchMessages:
			case eUserOperation::MarkAllAsRead:
			
				$sCallback = utils::ReadParam('callback', '', false, 'raw_data');

				// Check parameters.
				if(empty($sCallback)) {
					throw new Exception('Missing parameters for requested operation.');
				}
				break;

			case eUserOperation::Redirect:
			
				$iMessageId = (int) utils::ReadParam('message_id', 0, false, 'integer');
				
				// Check parameters
				if(empty($iMessageId)) {
					throw new Exception('Missing parameters for requested operation.');
				}
				break;

			case eUserOperation::PostMessagesToInstance:
			case eUserOperation::ViewAll:
				
				// Nothing needed for these.
				break;
		}

	// - Execute the requested operation.
		
		switch($eUserOperation) {
			
			case eUserOperation::FetchMessages:
			
				// Retrieve messages.
				$aMessages = Helper::GetUnreadMessagesForUser();
				$sMessagesJSON = json_encode($aMessages);

				// Prepare response.
				$sOutput = $sCallback.'('.$sMessagesJSON.')';

				$oPage = new JsonPage();
				$oPage->output($sOutput);
				break;

			case eUserOperation::MarkAllAsRead:
			
				// Mark messages as read.
				$iMessageCount = Helper::MarkAllMessagesAsReadForUser();
				$sMessageCountJSON = json_encode(array(
					'counter' => $iMessageCount,
					'message' => $iMessageCount.' message(s) marked as read',
				));

				// Prepare response
				$sOutput = $sCallback.'('.$sMessageCountJSON.')';

				$oPage = new JsonPage();
				$oPage->output($sOutput);
				break;

			case eUserOperation::ViewAll:
			
				$oPage = new Page('All messages');
				Helper::MakeAllMessagesPage($oPage);
				Helper::MarkAllMessagesAsReadForUser(); // Open to discussion: when all messages are rendered on an overview page: should they be marked as read?
				$oPage->output();
				break;

			case eUserOperation::Redirect:
			
				// Mark message as read when the user requested to see the details.
				$bMarked = Helper::MarkMessageAsReadForUser($iMessageId);

				// Redirect to final URL
				/** @var ThirdPartyNewsMessage $oMessage */
				$oMessage = MetaModel::GetObject('ThirdPartyNewsMessage', $iMessageId);
				
				if($oMessage !== null && Helper::MessageIsApplicable($oMessage) == true) {
					
					$oTranslation = Helper::GetTranslationForUser($oMessage);
					header('Location: '.$oTranslation->Get('url'));
				
				}
				
				break;

			case eUserOperation::PostMessagesToInstance:
				
				$oPage = new JsonPage();

				$sSourceClass = utils::ReadParam('sourceClass', '', false, 'raw_data');
				
				// - Validate if this is a known third-party name.
					
					if(class_exists($sSourceClass) === false) {
						
						$oPage->output(json_encode([
							'error' => 'News source does not exist.'
						]));
						break;
						
					}
				
				// - Process response.
			
					$sApiResponse = utils::ReadParam('data', '', false, 'raw_data');
					$oApiResponse = json_decode($sApiResponse);
					if($oApiResponse !== null) {
						Client::ProcessRetrievedMessages($oApiResponse, $sSourceClass);
					}

				// - Return data to post to news source ('report_read_statistics').
				
					$oPayload = Client::GetPayload($sSourceClass, eOperation::ReportReadStatistics, eOperationMode::Mitm);
					$sPayload = Client::PreparePayload($sSourceClass, $oPayload);
				
				$oPage->output(json_encode([
					'payload' => $sPayload
				]));
				break;
				
			default:

				$oPage = new WebPage('');
				$oPage->p('Invalid operation.');
				break;
		}

	}
	catch(Exception $oException) {
		
		// Note: Transform to cope with XSS attacks
		echo htmlentities($oException->GetMessage(), ENT_QUOTES, 'utf-8');

		Helper::Trace($oException->getMessage());
		Helper::Trace($oException->getTraceAsString());

		IssueLog::Error($oException->getMessage() . "\nDebug trace:\n" . $oException->getTraceAsString());
		
	}
