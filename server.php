<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.241108
 */
 
@include_once('../approot.inc.php');
@include_once('../../approot.inc.php');
@include_once('../../../approot.inc.php');

require_once(APPROOT.'/application/application.inc.php');
require_once APPROOT.'/application/startup.inc.php';

// This one isn't autoloaded yet.
require_once(APPROOT.'/application/loginwebpage.class.inc.php');

use JeffreyBostoenExtensions\News\{
    eApiVersion,
    eCryptographyKeyType,
    eCryptographyLibrary,
    eOperation,
	Helper,
	JsonPage,
	Server
};


/**
 * Helper method to create a fake payload for API version 1.0.0. To be deprecated.
 * 
 * @return stdClass
 */
function CreateFakePayload() : stdClass {

	Helper::Trace('Create fake payload for backward compatibility with API version %1$s', eApiVersion::v1_0_0->value);

	$oPayload = new stdClass();
	$oPayload->instance_hash = utils::ReadParam('instance_hash', '', false, 'raw_data');
	$oPayload->instance_hash2 = utils::ReadParam('instance_hash2', '', false, 'raw_data');
	$oPayload->db_uid = utils::ReadParam('db_uid', '', false, 'raw_data');
	$oPayload->env = utils::ReadParam('env', 'production', false, 'raw_data');
	$oPayload->app_name = utils::ReadParam('app_name', '', false, 'raw_data');
	$oPayload->app_version = utils::ReadParam('app_version', '', false, 'raw_data');
	$oPayload->encryption_library = utils::ReadParam('encryption_library', '', false, 'raw_data');
	$oPayload->api_version = utils::ReadParam('api_version', '', false, 'raw_data');

	return $oPayload;
	
}

try {
	
	Helper::Trace('Server received request from client.');
	

	
	$bExtensionEnabled = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'enabled', false);
	$bServerEnabled = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'server', false);

	// - This extension might simply not be enabled.
		
		if(!$bExtensionEnabled) {

			throw new Exception('News extension not enabled.');

		}

	// - The "server" functionality might simply not be enabled.

		if(!$bServerEnabled) {
			
			throw new Exception('Server not enabled.');
			
		}
	
	// - Check if the operation is valid.
		
		$sOperation = utils::ReadParam('operation', '', false, 'parameter');

		if(empty($sOperation)) {
			
			throw new Exception('Missing mandatory parameter "operation".');
		}

		$eOperation = eOperation::tryFrom($sOperation);

		if($eOperation === null) {
			throw new Exception(sprintf('Invalid operation: "%1$s".', $sOperation));
		}

	// - Check if the API version is valid.
	
		
		/** @var string $sApiVersion The API version as requested by the client. */
		$sClientApiVersion = utils::ReadParam('api_version', eApiVersion::v1_0_0->value, false, 'raw_data');

		/** @var eApiVersion $eClientApiVersion The API version as requested by the client. */
		$eClientApiVersion = eApiVersion::tryFrom($sClientApiVersion);

		if($eClientApiVersion === null) {
			throw new Exception(sprintf('Invalid API version: "%1$s".', $sClientApiVersion));
		}

	
	// Don't use Combodo's JsonPage. The server response will be JSONP.
	$oPage = new JsonPage();

	
	switch($eOperation) {
		
		case eOperation::GetMessagesForInstance:
			
			// @todo Remove 1.0 when no client uses this anymore.
			if($eClientApiVersion === eApiVersion::v1_0_0) {
				
				// Deprecated, to be removed soon.
				$sInstanceHash = utils::ReadParam('instance_hash', '', false, 'raw_data');
				$sInstanceHash2 = utils::ReadParam('instance_hash2', '', false, 'raw_data');
				
				/** @var string $sEncryptionLib The encryption library, as specified by the user. */
				$sClientCryptoLib =  utils::ReadParam('encryption_library', 'none', false, 'parameter');
				
				// Create fake payload so it can be processed similar to API version 1.1.0.
				$oPayload = CreateFakePayload();
				
			
			}
			else {
				
				$sPayload = utils::ReadParam('payload', '', false, 'raw_data');
				$oPayload = Server::GetPlainPayload($sPayload);
				
				$sInstanceHash = $oPayload->instance_hash;
				$sInstanceHash2 = $oPayload->instance_hash2;

				/** @var string $sClientCryptoLib The encryption library, as specified by the client. */
				if($eClientApiVersion == eApiVersion::v1_1_0) {
					$sClientCryptoLib = $oPayload->encryption_library;
				 }
				 else {
					$sClientCryptoLib = $oPayload->crypto_lib;
				 }
				 
			}

			// - Log the unencrypted payload.

				Helper::Trace('Payload: %1$s', json_encode($oPayload, JSON_PRETTY_PRINT));
			
			// - Validate required parameters.

			//	 Why do we only check the instance hashes?
				
				if($sInstanceHash == '' || $sInstanceHash2 == '') {
					
					throw new Exception('Error: Empty instance hash.');
					
				}

			// - Validate if "token" is present.

				if(!property_exists($oPayload, 'token') || !is_string($oPayload->token) || strlen($oPayload->token) != (Helper::CLIENT_TOKEN_BYTES * 2)) {
					
					throw new Exception('Error: Invalid or missing "token" in payload.');
					
				}

				
			// - Retrieve messages.

				$aMessages = Server::GetMessagesForInstance();

			// - Validate whether the given encryption/signing is possible.

				$bPhpSodiumEnabled = function_exists('sodium_crypto_sign_detached');

				/** @var eCryptographyLibrary $eClientCryptoLib The encryption library, as specified by the client. */
				$eClientCryptoLib = eCryptographyLibrary::tryFrom($sClientCryptoLib);

				if($eClientCryptoLib === null) {

					throw new Exception('The client requested an unsupported cryptography library.');

				} 
				elseif($eClientCryptoLib == eCryptographyLibrary::Sodium && !$bPhpSodiumEnabled) {
					
					// Stricter implementation: Do not even fall back to non-encrypted version.
					throw new Exception('The client requested Sodium cryptography, but this server does not have Sodium enabled.');

				}
				

			// - Prepare the data.

				/** @var stdClass $oResponse */
				$oResponse = new stdClass();

				switch($eClientApiVersion) {

					case eApiVersion::v2_0_0:

						// - The structure will always be the same.
						$oResponse->crypto_lib = $eClientCryptoLib->value;
						$oResponse->messages = $aMessages;
						// The 'refresh_token' should be set by one iServerExtension.

						break;

					case eApiVersion::v1_1_0:
					case eApiVersion::v1_0_0:

						// - Note: In the end, the response may still be turned into an array for non-encrypted responses. See further.
						$oResponse->encryption_library = $sClientCryptoLib; // This will keep the capital of 'Sodium'.
						$oResponse->messages = $aMessages;

						break;
						
				}
				
			
				// - Execute third-party processors.

					Server::ExecuteThirdPartyServerExtensions($eClientApiVersion, $eOperation, $oPayload, $oResponse);

				// - Sign, if necessary.

					if($eClientCryptoLib == eCryptographyLibrary::Sodium) {
							
						// If Sodium is available, use it to sign the messages.
						// The messages are not secret; the signing is just to verify authenticity.
						$sPrivateKey = Server::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoSign);
						$sSignature = sodium_crypto_sign_detached(json_encode($aMessages), $sPrivateKey);
						
						$oResponse->signature = sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE);

					}

				// - Prepare output.

					if($eClientCryptoLib == eCryptographyLibrary::None && (
							$eClientApiVersion == eApiVersion::v1_1_0 || 
							$eClientApiVersion == eApiVersion::v1_1_0
					)) {

						$sOutput = json_encode($oResponse->messages);

					}
					else {
						
						$sOutput = json_encode($oResponse);
					
					}			

				// - If a callback method is specified, wrap the output in a JSONP callback.

					$sCallBackMethod = utils::ReadParam('callback', '', false, 'parameter');
					if($sCallBackMethod != '') {
						$sOutput = $sCallBackMethod.'('.$sOutput.');';
					
					}

					Helper::Trace('Response to client:');
					Helper::Trace($sOutput);

				// - Print the output.
					$oPage->output($sOutput);

	
		
			break;
			
		case eOperation::ReportReadStatistics:
		
			// @todo Remove 1.0 when no client uses this anymore.
			if($eClientApiVersion === eApiVersion::v1_0_0) {
				
				// Create fake payload so it can be processed similar to API version 1.1.0.
				$oPayload = CreateFakePayload();
			
			}
			else {
				
				// Since supporting JSONP: the payload may not longer be in a $_POST request.
				$sPayload = utils::ReadParam('payload', '', false, 'raw_data');
				$oPayload = Server::GetPlainPayload($sPayload);
				
			}

			$oResponse = new stdClass();

			// This extension does not take care of storing the collected statistics.
			// It must be handled by a third-party processor.			
			Server::ExecuteThirdPartyServerExtensions($eClientApiVersion, $eOperation, $oPayload, $oResponse);
			
			$sOutput = json_encode($oResponse);

			Helper::Trace('Response to client:');
			Helper::Trace($sOutput);

			// - Print the output.
			$oPage->output($sOutput);
			

			break;
			

			
		default:
			
			// Invalid operation. This should not occur.
			throw new Exception('Unexpected cases when handling operations. Please report this as a bug.');
			break;

	}

}
catch(Exception $oException) {
	
	// Note: Transform to cope with XSS attacks
	echo htmlentities($oException->GetMessage(), ENT_QUOTES, 'utf-8');

	Helper::Trace($oException->GetMessage());
	IssueLog::Error($oException->getMessage() . "\nDebug trace:\n" . $oException->getTraceAsString());

	// Set HTTP response code to 500 Internal Server Error.
	// Newer versions of the news extension will reject the response if HTTP status != 200.
	http_response_code(500); // Internal server error

	// No output?
	
}
