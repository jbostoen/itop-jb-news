<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
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
		
			$sApiVersion = utils::ReadPostedParam('api_version', '1.0', 'transaction_id');
			
			if($sApiVersion === '1.0') {
				
				// Deprecated, to be removed soon.
				$sInstanceHash = utils::ReadParam('instance_hash', '', false, 'transaction_id');
				$sInstanceHash2 = utils::ReadParam('instance_hash2', '', false, 'transaction_id');
				
				$sVersion = utils::ReadParam('api_version', NewsroomHelper::DEFAULT_API_VERSION, false, 'transaction_id');
				$sAppName = utils::ReadParam('app_name', NewsroomHelper::DEFAULT_APP_NAME, false, 'raw_data');
				$sAppVersion = utils::ReadParam('app_version', NewsroomHelper::DEFAULT_APP_VERSION, false, 'raw_data');
				
				$sEncryptionLib =  utils::ReadParam('encryption_library', 'none', false, 'parameter');
				
				// Avoid warnings
				$aPayload = [];
				$sPayload = 'not applicable';
			
			}
			else {
				
				// Since supporting JSONP: the payload may not longer be in a $_POST request.
				$sPayload = utils::ReadParam('payload', '', false, 'raw_data');
				
				if($sPayload == '') {
					throw new Exception('Missing parameters for operation "get_messages_for_instance". Payload is empty.');
				}
				
				// Payloads can be either encrypted or unencrypted (Sodium not available on the iTop instance which is requesting news messages).
				// Either way, they are base64 encoded.
				$sPayload = base64_decode($sPayload);
				
				// Doesn't seem regular JSON yet; try unsealing
				if(substr($sPayload, 0, 1) !== '{') {
					
					$sPrivateKey = NewsServer::GetKeySodium('private_key_crypto_box');
					$sPublicKey = NewsServer::GetKeySodium('public_key_crypto_box');
					$sPayload = sodium_crypto_box_seal_open($sPayload, sodium_crypto_box_keypair_from_secretkey_and_publickey($sPrivateKey, $sPublicKey));
					
					if(substr($sPayload, 0, 1) !== '{') {
						
						throw new Exception('Unable to decode payload: '. utils::ReadParam('payload', '', 'raw_data')); // Refer to original data.
						
					}
					
				}
				
				$aPayload = json_decode($sPayload, true);
				
				$sInstanceHash = $aPayload['instance_hash'];
				$sInstanceHash2 = $aPayload['instance_hash2'];
				$sVersion = $aPayload['version'];
				$sAppName = $aPayload['app_name'];
				$sAppVersion = $aPayload['app_version'];
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
				
				// Run third party processors
				NewsServer::RunThirdPartyProcessors();
				
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
		
			// Read statistics to be stored somewhere. 
			// For now, just ignore.
			$oPage->add(json_encode([
			]));
			break;
			
		case 'post_messages_to_instance':
		
			$sSourceClass = utils::ReadParam('sourceClass', '', false, 'raw_data');
			
			// - Validate if this is a known third-party name.
				
				if(class_exists($sSourceClass) === false) {
					
					$oPage->add(json_encode([
						'error' => 'News source does not exist.'
					]));
					break;
					
				}
			
			// - Process response
		
				$sApiResponse = utils::ReadParam('data', '', false, 'raw_data');
					
				NewsClient::ProcessRetrievedMessages($sApiResponse, $sSourceClass);
				
			// - Return data to post to news source ('report_read_statistics')
			
				$aPayload = NewsClient::GetPayload($sSourceClass, 'report_read_statistics');
				$sPayload = NewsClient::PreparePayload($sSourceClass, $aPayload);
			
			$oPage->add(json_encode([
				'payload' => $sPayload
			]));
			break;
			
		default:
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
