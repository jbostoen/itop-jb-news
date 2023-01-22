<?php

/**
 * @copyright   Copyright (c) 2019-2023 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.230122
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

	
}

// Still classic
require_once(APPROOT.'/application/loginwebpage.class.inc.php');


use \jb_itop_extensions\NewsProvider\NewsClient;
use \jb_itop_extensions\NewsProvider\NewsRoomHelper;
use \jb_itop_extensions\NewsProvider\NewsRoomWebPage;
use \jb_itop_extensions\NewsProvider\NewsServer;


try {
	
	
	require_once APPROOT . '/application/startup.inc.php';
	
	// Check user rights and prompt if needed
	$sOperation = utils::ReadParam('operation', '', false, 'parameter');
	
	if(class_exists('DownloadPage') == true) {
		// Modern 3.0
		$oPage = new DownloadPage('');
	}
	else {
		// Legacy 2.7
		$oPage = new ajax_page('');
	}
	
	$oPage->no_cache();
	$oPage->SetContentType('application/json');

	// Check global parameters
	if(empty($sOperation)) {
		throw new Exception('Missing mandatory parameter "operation" .');
	}

	
	switch($sOperation) {
		
		case 'get_messages_for_instance':
		
			$sApiVersion = utils::ReadParam('api_version', '1.0', false, 'raw_data');
			
			// @todo Remove 1.0 when no client uses this anymore.
			if($sApiVersion === '1.0') {
				
				// Deprecated, to be removed soon.
				$sInstanceHash = utils::ReadParam('instance_hash', '', false, 'raw_data');
				$sInstanceHash2 = utils::ReadParam('instance_hash2', '', false, 'raw_data');
				
				$sEncryptionLib =  utils::ReadParam('encryption_library', 'none', false, 'parameter');
				
				// Create fake payload for iNewsServerProcessor::Process()
				$aPayload = [
					'instance_hash' =>utils::ReadParam('instance_hash', '', false, 'raw_data'),
					'instance_hash2' => utils::ReadParam('instance_hash2', '', false, 'raw_data'),
					'db_uid' => utils::ReadParam('db_uid', '', false, 'raw_data'),
					'env' => utils::ReadParam('env', '', false, 'raw_data'),
					'app_name' => utils::ReadParam('app_name', '', false, 'raw_data'),
					'app_version' => utils::ReadParam('app_version', '', false, 'raw_data'),
					'encryption_library' => utils::ReadParam('encryption_library', '', false, 'raw_data'),
					'api_version' => utils::ReadParam('api_version', '', false, 'raw_data')
				];
			
			}
			else {
				
				// Since supporting JSONP: the payload may not longer be in a $_POST request.
				$sPayload = utils::ReadParam('payload', '', false, 'raw_data');
				$aPayload = NewsServer::GetPlainPayload($sPayload);
				
				$sInstanceHash = $aPayload['instance_hash'];
				$sInstanceHash2 = $aPayload['instance_hash2'];
				
				$sEncryptionLib = $aPayload['encryption_library'];
				
			}

		
			
			// Check parameters
			if($sInstanceHash == '' || $sInstanceHash2 == '') {
				
				throw new Exception('Missing parameters for operation "get_messages_for_instance". API version: '.$sApiVersion.' - Payload: '.$sPayload.' - POST: '.json_encode($_POST).' - GET: '.json_encode($_GET).' - '.json_encode($aPayload));
				
			}

			if(MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'enabled', false) == false) {

				$oPage->add('News extension not enabled.');

			}
			elseif(MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'server', false) == false) {
				
				$oPage->add('Server not active.');
				
			}
			else {
		
				// Retrieve messages
				$aMessages = NewsServer::GetMessagesForInstance();
				
				// Run third-party processors
				NewsServer::RunThirdPartyProcessors($sOperation, $aPayload);
				
				// Prepare response
				// Note: the encryption library is appended in the response.
				// While the client may have specified a preferred library, the server might not be support it (anymore).
				// The messages will then still be appended in plain text, but the NewsClient should not process them anymore and add a warning instead.
				$bFunctionExists = function_exists('sodium_crypto_sign_detached');
				
				if($sEncryptionLib == 'Sodium' && $bFunctionExists == true) {
					
					// Get private key
					$sPrivateKey = NewsServer::GetKeySodium('private_key_crypto_sign');
					
					// Sign using private key
					$sSignature = sodium_crypto_sign_detached(json_encode($aMessages), $sPrivateKey);
					
					// The data itself is not secret, its authenticity just needs to be able to be verified
					$sOutput = json_encode([
						'encryption_library' => 'Sodium',
						'messages' => $aMessages,
						'vesion' => NewsServer::GetApiVersion(),
						'signature' => sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE)
					]);
					
				}
				// 'none' specified by user or requested encryption library not available on server
				else {
					
					// Legacy. Left this untouched for existing users.
					$sOutput = json_encode($aMessages);
					
				}

				$sCallBackMethod = utils::ReadParam('callback', '', false, 'parameter');
				if($sCallBackMethod != '') {

					$oPage->SetContentType('application/json');
					$sOutput = $sCallBackMethod.'('.$sOutput.');';
				
				}

				// Return data
				$oPage->add($sOutput);
				
			}

	
		
			break;
			
		case 'report_read_statistics':
		
			$sApiVersion = utils::ReadParam('api_version', '1.0', false, 'raw_data');
			
			// @todo Remove 1.0 when no client uses this anymore.
			if($sApiVersion === '1.0') {
				
				// Create fake payload for iNewsServerProcessor::Process()
				$aPayload = [
					'instance_hash' =>utils::ReadParam('instance_hash', '', false, 'raw_data'),
					'instance_hash2' => utils::ReadParam('instance_hash2', '', false, 'raw_data'),
					'db_uid' => utils::ReadParam('db_uid', '', false, 'raw_data'),
					'env' => utils::ReadParam('env', '', false, 'raw_data'),
					'app_name' => utils::ReadParam('app_name', '', false, 'raw_data'),
					'app_version' => utils::ReadParam('app_version', '', false, 'raw_data'),
					'encryption_library' => utils::ReadParam('encryption_library', '', false, 'raw_data'),
					'api_version' => utils::ReadParam('api_version', '', false, 'raw_data')
				];
			
			}
			else {
				
				// Since supporting JSONP: the payload may not longer be in a $_POST request.
				$sPayload = utils::ReadParam('payload', '', false, 'raw_data');
				$aPayload = NewsServer::GetPlainPayload($sPayload);
				
			}
			
			NewsServer::RunThirdPartyProcessors($sOperation, $aPayload);

			// Read statistics to be stored somewhere. 
			// For now, just ignore.
			$oPage->add(json_encode([
			]));
			break;
			

			
		default:
		
		
			$sApiVersion = utils::ReadParam('api_version', '1.0', false, 'raw_data');
			
			// @todo Remove 1.0 when no client uses this anymore.
			if($sApiVersion === '1.0') {
				
				// Avoid warnings
				$aPayload = [];
			
			}
			else {
				
				// Since supporting JSONP: the payload may not longer be in a $_POST request.
				$sPayload = utils::ReadParam('payload', '', false, 'raw_data');
				$aPayload = NewsServer::GetPlainPayload($sPayload);
				
			}
			
			NewsServer::RunThirdPartyProcessors($sOperation, $aPayload);
			
			
		
			$oPage->add(json_encode([
				'error' => 'Invalid operation: '.$sOperation
			]));
			break;
	}

	$oPage->output();
}
catch(Exception $oException) {
	
	// Note: Transform to cope with XSS attacks
	echo htmlentities($oException->GetMessage(), ENT_QUOTES, 'utf-8');
	IssueLog::Error($oException->getMessage() . "\nDebug trace:\n" . $oException->getTraceAsString());
	
}
