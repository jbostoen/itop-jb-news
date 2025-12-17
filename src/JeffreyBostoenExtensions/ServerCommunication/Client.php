<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 *
 */

namespace JeffreyBostoenExtensions\ServerCommunication;

use JeffreyBostoenExtensions\ServerCommunication\Base\HttpRequest as HttpRequest_Base;
use JeffreyBostoenExtensions\ServerCommunication\v210\HttpRequest;

// iTop internals.
use Combodo\iTop\Application\Helper\Session;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;

// iTop classes.
use KeyValueStore;

// Generic.
use Exception;
use ModuleInstallation;
use stdClass;

/**
 * Interface iExternalServer. Interface to use when implementing external servers.
 */
interface iExternalServer {
	
	/**
	 * A source can add extra data to the payload that will be sent from the client to the server.
	 * 
	 * @param HttpRequest $oRequest The request (payload) that will be sent from the client to the server.
	 * 
	 * @details Mind that by default certain parameters are already included in the HTTP request to the external server.
	 * @see Client::BuildHttpRequest()
	 *
	 * @return array Key/value pairs to send to the external server.
	 */
	public static function ModifyHttpRequest(HttpRequest $oRequest) : void;
	
	/**
	 * Returns the base64 encoded public key for the Sodium implementation (crypto box).  
	 * A external server must share a public key with the clients.  
	 * This public key will be used to seal data which can then only be read by the external server.
	 *
	 * @return string
	 */
	public static function GetPublicKeySodiumCryptoBox() : string;

	/**
	 * Returns the base64 encoded public key for a Sodium implementation (crypto sign).  
	 * A external server must share a public key with the clients.  
	 * This public key will be used to verify the response of the source, to avoid tampering.
	 *
	 * @return string
	 */
	public static function GetPublicKeySodiumCryptoSign() : string;
	
	/**
	 * Returns the name of third party external server.  
	 * This is used as a unique identifier, so do not use an existing one. It should remain consistent.
	 *
	 * @return string The name of the third party external server.
	 */
	public static function GetThirdPartyName() : string;

	/**
	 * Returns the URL of the external server. 
	 * Data will be retrieved from this source.
	 */
	public static function GetUrl() : string;


	/**
	 * Returns an SVG logo. (Actual SVG, not a URL).
	 *
	 * @return string
	 */
	public static function GetLogoSVG() : string;

	/**
	 * Returns a list of supported operations.
	 * 
	 * This is NOT queried from the server each time.
	 *
	 * @return eOperation[]
	 */
	public static function GetSupportedOperations() : array;

}

/**
 * Class Client. A common client to send and retrieve data from one or more third-party (non Combodo) external servers (person/organization).
 */
abstract class Client {
	
	/** @var array $aCachedPayloads This array is used to cache some payloads that are the same for multiple external servers. */
	public static $aCachedPayloads = [];


	/**
	 * Returns a sanitized version (only keeping alphabetical characters and numbers) of the third-party external server name.
	 *
	 * @param string $sExtServerSource
	 * @return string
	 */
	public static function GetSanitizedExtServerName(string $sExtServerSource) : string {

		$sExtServerSource = basename($sExtServerSource);
		
		// Sanitize the external server name to be used as a key in the KeyValueStore.
		// This is to avoid issues with special characters, spaces, etc.
		return 'source_'. preg_replace('/[^a-zA-Z0-9]+/', '', $sExtServerSource);
		
	}
	
	
	/**
	 * Saves a key/value for a particular external server.
	 *
	 * @param string $sExtServerSource Name of the external server (can be unsanitized).
	 * @param string $sSuffix Suffix to append to the sanitized version of the external server and "_". (e.g. 'last_retrieval', 'token').
	 * @param string $sValue The value to store.
	 *
	 * @return void
	 */
	public static function StoreKeyValue(string $sExtServerSource, string $sSuffix, string $sValue) : void {
		
		$sKeyName = static::GetSanitizedExtServerName($sExtServerSource).'_'.$sSuffix;
		
		/** @var KeyValueStore $oKeyValueStore */
		$oKeyValueStore = Helper::GetKeyValueStore($sKeyName) ?? MetaModel::NewObject('KeyValueStore', [
			'namespace' => Helper::MODULE_CODE,
			'key_name' => $sKeyName,
		]);
		$oKeyValueStore->Set('value', $sValue);
		$oKeyValueStore->DBWrite();
		
	}
	
	
	/**
	 * Process the response from the external server.
	 *
	 * @param string $sExtServerClass
	 * @param eOperation $eOperation
	 * @param stdClass $oResponse
	 * @return void
	 */
	public static function ProcessResponse(string $sExtServerClass, eOperation $eOperation, stdClass $oResponse) : void {

		// Subclasses should implement this.

	}


	/**
	 * Performs a POST request to all external sources.
	 *
	 * @param eOperation $eOperation
	 * 
	 * @return void
	 */
	public static function DoPostAll(eOperation $eOperation) : void {

		$aSources = static::GetSources($eOperation);
		
		foreach($aSources as $sExtServerClass) {
				
			$oResponse = static::DoPost($sExtServerClass, $eOperation);

			// Currently, we expect the external server is fully up to date.
			// For simplicity, the client is NOT backward compatible.
			// If the server doesn't support something yet, the entire response will be ignored.

			if($oResponse === null) {
				Helper::Trace('The response data was null.');
				continue;
			}

			static::ProcessResponse($sExtServerClass, $eOperation, $oResponse);

		}

	}
	
	
	/**
	 * Returns class names of active external servers.
	 * 
	 * @param eOperation|null $eOperation The relevant operation.
	 *
	 * @return string[] The names of the classes of the external servers that implement iExternalServer.
	 */
	public static function GetSources(?eOperation $eOperation) : array {

		$aSources = [];
		$aDisabledSources = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'disabled_sources', []);
			
		foreach(get_declared_classes() as $sClassName) {
			
			$aImplementations = class_implements($sClassName);
			if(in_array(iExternalServer::class, $aImplementations)) {
				
				// - Skip source if temporarily disabled (perhaps advised at some point for some reason, without needing to disable or uninstall the extension completely)
					
					$sThirdPartyName = $sClassName::GetThirdPartyName();
					if(in_array($sThirdPartyName, $aDisabledSources) == true) {
						Helper::Trace('Source "%1$s" is disabled in the iTop configuration.', $sThirdPartyName);
						continue;
					}

				// - Skip source if it doesn't support the operation.

					$aSupportedOperations = $sClassName::GetSupportedOperations();
					if($eOperation !== null && !in_array($eOperation, $aSupportedOperations)) {
						Helper::Trace('Source "%1$s" does not support operation "%2$s".', $sThirdPartyName, $eOperation->value);
						continue;
					}
				
				$aSources[] = $sClassName;
			}
			
		}
		
		return $aSources;
		
	}
	

	/**
	 * Returns the default (essential) payload info.
	 *
	 * @param string $sExtServerClass Name of the external server class.
	 * @param eOperation $eOperation The operation that is being executed.
	 * @param eOperationMode $eOperationMode The operation mode (e.g. cron, mitm).
	 *
	 * @return HttpRequest The payload.
	 *
	 * @details Mind that this is executed once for each external server, as the payload might differ.
	 * 
	 * @todo See if we should have a "light" version of this, containing less instance data.
	 */
	public static function BuildHttpRequest(string $sExtServerClass, eOperation $eOperation, eOperationMode $eOperationMode) : HttpRequest {
		
		$sUrl = $sExtServerClass::GetUrl();
		
		$sApp = defined('ITOP_APPLICATION') ? ITOP_APPLICATION : 'unknown';
		$sVersion = defined('ITOP_VERSION') ? ITOP_VERSION : 'unknown';
		
		$sInstanceHash = Helper::GetInstanceHash();
		$sInstanceHash2 = Helper::GetInstanceHash2();
		$sDBUid = Helper::GetDatabaseUID();
		
		$eCryptographyLib = Helper::GetCryptographyLibrary();
		
		/** @var HttpRequest $oRequest */
		$oRequest = new HttpRequest();
		$oRequest->api_version = eApiVersion::v2_1_0->value;
		$oRequest->operation = $eOperation->value;
		$oRequest->client_token = static::GetClientToken($sExtServerClass)->Get('value');
		$oRequest->mode = $eOperationMode->value;

		// Let's keep this for now, but it may be futile:
		$oRequest->crypto_lib = $eCryptographyLib->value;

		// Identifiers.
		$oRequest->instance_hash = $sInstanceHash;
		$oRequest->instance_hash2 = $sInstanceHash2;
		$oRequest->db_uid = $sDBUid;

		// iTop info.
		$oRequest->app_name = $sApp;
		$oRequest->app_version = $sVersion;
		$oRequest->app_root_url = MetaModel::GetConfig()->Get('app_root_url');
		$oRequest->env = MetaModel::GetEnvironment(); // Note: utils::GetCurrentEnvironment() only returns the correct value on the second call in the same sesssion.


		// - New token.

			$sNewToken = bin2hex(random_bytes(Helper::CLIENT_TOKEN_BYTES));
			$oRequest->new_client_token = $sNewToken;

			if($eOperationMode == eOperationMode::Mitm) {

				$sSanitizedClass = static::GetSanitizedExtServerName($sExtServerClass);
				Session::Set('communication_new_client_token_'.$sSanitizedClass, $sNewToken);

			}
			

		// - Add the version of this module.

			$oFilter = DBObjectSearch::FromOQL_AllData('SELECT ModuleInstallation WHERE name = :name', []);
			$oSet = new DBObjectSet($oFilter, [
				'installed' => false,
			], [
				'name' => Helper::MODULE_CODE
			]);
			/** @var ModuleInstallation $oModuleInstallation */
			$oModuleInstallation = $oSet->Fetch();

			// - There should be at least one.
			if($oModuleInstallation !== null) {
				$oRequest->extension_version = $oModuleInstallation->Get('version');
			}
		
		// @todo Check if the below is still true.
		if(strpos($sUrl, '?') !== false) {
			
			// To understand the part below:
			// To make things look more pretty, the URL for a external server could point to a generic domain: 'itop-communication.domain.org'.
			// This could be an index.php file that simply calls an iTop instance.
			// The index.php script (some sort of proxy) could act as a client to an iTop installation with the "server" setting in this extension set to enabled.
			// It could make a call to: https://localhost:8182/iTop-clients/web/pages/exec.php?&exec_module=jb-news&exec_page=index.php&exec_env=production-news&operation=get_messages_for_instance&version=1.0 
			// and it would also need the originally appended parameters that were sent to 'itop-communication.domain.org'.
			$sParameters = explode('?', $sUrl)[1];
			parse_str($sParameters, $aParameters);

			foreach($aParameters as $sKey => $sValue) {
				$oRequest->{$sKey} = $sValue;
			}
			
		}
		
		// -These are default parameters, which can be overridden or extended by the provider.
			$sExtServerClass::ModifyHttpRequest($oRequest);

		// - Return the final payload.

		return $oRequest;
		
	}

	
	/**
	 * Prepare the payload: 
	 * 
	 * - Encode payload as JSON.
	 * - If Sodium is available, the client attempts to encrypt it before sending it to the server.
	 * - Encode using base64.
	 *
	 * @param string $sExtServerClass Name of the external server class.
	 * @param HttpRequest_Base $oPayload Payload to be prepared. This is a base class, as this method is used in automated tests for older client versions too.
	 *
	 * @return string Binary data
	 */
	public static function PreparePayload(string $sExtServerClass, HttpRequest_Base $oPayload) : string {
		
		$sPayload = json_encode($oPayload);
		
		if(Helper::GetCryptographyLibrary() == eCryptographyLibrary::Sodium) {
			
			// There is no check here to validate if this key is valid.
			// It is the responsibility of an external server to ensure this is okay.
			$sPublicKey = sodium_base642bin($sExtServerClass::GetPublicKeySodiumCryptoBox(), SODIUM_BASE64_VARIANT_URLSAFE);
			$sBinData = sodium_base642bin(base64_encode($sPayload), SODIUM_BASE64_VARIANT_URLSAFE);
			
			// The payload becomes sealed.
			$sPayload = sodium_crypto_box_seal($sBinData, $sPublicKey);
		
		}
		
		return base64_encode($sPayload);
	
	}
	
	/**
	 * Perform an HTTP POST request to an end point.  
	 * It returns the content if there is a valid response (HTTP status code 200).
	 *
	 * @param string $sExtServerClass Name of the external server class.
	 * @param eOperation $eOperation The operation that is being executed.
	 *
	 * @return stdClass|null Null when there is no response (cURL error occurred); otherwise a string.
	 *
	 * @throws Exception
	 */
	public static function DoPost(string $sExtServerClass, eOperation $eOperation) : stdClass|null {

		// Unencrypted payload (easier for debugging).
		$oPayload = static::BuildHttpRequest($sExtServerClass, $eOperation, eOperationMode::Cron);

		$sUrl = $sExtServerClass::GetUrl();
				
		Helper::Trace('Url: %1$s', $sUrl);
		Helper::Trace('Data: %1$s', json_encode($oPayload, JSON_PRETTY_PRINT));
		
		// Prepare the payload.
		$sPayload = static::PreparePayload($sExtServerClass, $oPayload);
		
		$aPostData = [
			'api_version' => eApiVersion::v2_1_0->value,
			'payload' => $sPayload
		];
		
		$cURLConnection = curl_init($sUrl);
		curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $aPostData);
		curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
		
		$bSslVerify = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'curl_ssl_verify', true);
		if(!$bSslVerify) {
			curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYHOST, false);
		}

		$sApiResponse = curl_exec($cURLConnection);
		
		if(curl_errno($cURLConnection)) {
			
			Helper::Trace('Error: cURL connection failed: %1$s, %2$s',
				curl_errno($cURLConnection),
				curl_error($cURLConnection)
			);
			
			// Abort. Otherwise messages might just get deleted while they shouldn't.
			return null;
			
		}

		$iHttpCode = curl_getinfo($cURLConnection, CURLINFO_RESPONSE_CODE);
		if($iHttpCode != 200) {

			// Hint: This also allows the external server to occasionally skip requests, by returning a different HTTP status code.
			Helper::Trace('Error: cURL did not return HTTP status code 200. Actual HTTP status code: %1$s', $iHttpCode);
			Helper::Trace($sApiResponse);
			return null;

		}

		$cURLConnection = null;

		// - Common behavior for all requests:
		//   For any valid response, try to decode and save the token.

			/** @var stdClass|null $oResponse */
			$oResponse = json_decode($sApiResponse);

			if($oResponse !== null) {
				static::UpdateClientToken($sExtServerClass, $oResponse);
			}
			else {

				Helper::Trace('Invalid response, no JSON data returned:');
				Helper::Trace($sApiResponse);

			}

		return $oResponse;

	}


	/**
	 * Gets the client token for the current iTop instance.
	 *
	 * @param string $sExtServerClass
	 * @return KeyValueStore
	 */
	public static function GetClientToken(string $sExtServerClass) : KeyValueStore {
		
		// The client token is used to identify the client (i.e. this iTop instance) to the external server.
		// The client token is initially created by the client, and then sent to the external server.
		// Any third-party external server processors can use this token to identify the client, and send another one back that should be saved by the client.
		
		$sKeyName = static::GetSanitizedExtServerName($sExtServerClass).'_client_token';

		/** @var KeyValueStore $oKeyValueStore */
		$oKeyValueStore = Helper::GetKeyValueStore($sKeyName) ?? MetaModel::NewObject('KeyValueStore', [
			'namespace' => Helper::MODULE_CODE,
			'key_name' => $sKeyName,
			'value' => bin2hex(random_bytes(Helper::CLIENT_TOKEN_BYTES))
		]);

		// Persist in case this was a new client_token.
		$oKeyValueStore->DBWrite();

		return $oKeyValueStore;
		
	}
	
	
	/**
	 * Update the client token for the specified external server.
	 * 
	 * @param string $sExtServerClass The name of the external server class.
	 * @param stdClass $oResponse The response from the external server, which should contain a "refresh_token" key.
	 *
	 * @return void
	 */
	public static function UpdateClientToken(string $sExtServerClass, stdClass $oResponse) : void {
		
		// If a "refresh_token" was received (and it should be), store it.

		if(
			property_exists($oResponse, 'new_client_token') && 
			is_string($oResponse->new_client_token) && 
			strlen($oResponse->new_client_token) == (Helper::CLIENT_TOKEN_BYTES * 2)
		) {

			// Store the refresh token for this external server.
			// Do so before any other processing can fail; as it may be used to uniquely identify this instance.
			static::StoreKeyValue($sExtServerClass, 'client_token', $oResponse->new_client_token);
			
		}
		else {
			
			// No refresh token received, while it was expected.
			// This can simply mean the server (server extension) does not support this feature yet.
			Helper::Trace('New client token not confirmed by the external server "%1$s".', $sExtServerClass);
			
		}

	}




}
