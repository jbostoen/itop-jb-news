<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220607
 *
 */



@include_once('../approot.inc.php');
@include_once('../../approot.inc.php');
@include_once('../../../approot.inc.php');

use \DownloadPage;

require_once(APPROOT.'/application/application.inc.php');

// iTop 3 makes WebPage auto-loadable
if(defined('ITOP_VERSION') == true && version_compare(ITOP_VERSION, '3.0', '<')) {
	
	require_once(APPROOT.'/application/webpage.class.inc.php');
	require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');

	
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
	
	if(class_exists('DownloadPage') == true) {
		// Modern 3.0
		$oPage = new DownloadPage('');
		$oPage->SetContentType('application/json');
	}
	else {
		// Legacy 2.7
		$oPage = new ajax_page('');
	}
	
	$oPage->no_cache();

	// Retrieve global parameters
	$sVersion = utils::ReadParam('api_version', NewsroomHelper::DEFAULT_API_VERSION);
	$sAppName = utils::ReadParam('app_name', NewsroomHelper::DEFAULT_APP_NAME, false, 'raw_data');
	$sAppVersion = utils::ReadParam('app_version', NewsroomHelper::DEFAULT_APP_VERSION, false, 'raw_data');
	
	$sEncryptionLib =  utils::ReadParam('encryption_library', 'none', false, 'raw_data');

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

			}
			elseif(utils::GetCurrentModuleSetting('server', false) == false) {
				
				$oPage->add('Server not active.');
				
			}
			else {
		
				// Retrieve messages
				$aMessages = NewsServer::GetMessagesForInstance();
				
				// Prepare response
				// Note: the encryption library is appended in the response.
				// While the client may have specified a preferred library, the server might not be support it (anymore).
				// The messages will then still be appended in plain text, but the NewsClient should not process them anymore and add a warning instead.
				$bFunctionExists = function_exists('sodium_crypto_sign_detached');
				
				if($sEncryptionLib == 'Sodium' && $bFunctionExists == true) {
					
					// Get private key
					$sFolder = dirname(__FILE__);
					$sKey = file_get_contents($sFolder.'/keys/sodium_priv.key');
					
					$sSodium_PrivBase64 = sodium_base642bin($sKey, SODIUM_BASE64_VARIANT_URLSAFE);
					
					// Sign using private key
					$sSignature = sodium_crypto_sign_detached(json_encode($aMessages), $sSodium_PrivBase64);
					
					// The data itself is not secret, its authenticity just needs to be able to be verified
					$sOutput = json_encode([
						'encryption_library' => 'Sodium',
						'messages' => $aMessages,
						'signature' => sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE)
					]);					
					
				}
				// 'none' specified by user or requested encryption library not available on server
				else {
					
					// Legacy. Left this untouched for existing users.
					$sOutput = json_encode($aMessages);
					
				}

				// Regular JSON here, not JSONP
				$oPage->SetContentType('application/json');
				$oPage->add($sOutput);
				
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
