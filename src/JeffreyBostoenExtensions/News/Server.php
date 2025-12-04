<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News;

// iTop classes.
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use utils;

// Generic.
use Exception;
use SodiumException;
use stdClass;
use ThirdPartyNewsMessage;

/**
 * Enum eCryptographyKeyType. Key types.
 * 
 * For good hygiene, it's recommended to use different key pairs for different purposes.
 * 
 * Boxing:  
 * Sealed boxes are designed to anonymously send messages to a recipient given their public key.
 * Only the recipient can decrypt these messages using their private key. While the recipient can verify the integrity of the message, they cannot verify the identity of the sender.
 * 
 * Signing:
 * Signing is used to generate a digital signature for a message using a private key, which can be verified by anyone processing the corresponding public key.
 */
enum eCryptographyKeyType : string {
	
	case PrivateKeyCryptoSign = 'private_key_crypto_sign';
	case PrivateKeyCryptoBox = 'private_key_crypto_box';
	case PublicKeyCryptoBox = 'public_key_crypto_box';
}


/**
 * Interface iServerExtension. Interface to implement server-side actions when a client connects.
 */
interface iServerExtension {
	
	/**
	 * A hook that can execute logic as soon as the incoming HTTP request is received, before any other processing is done.
	 * For example: Keep track of which instances last connected, process custom info that may have been added in the payload.
	 *
	 * @param HttpRequest $oHttpRequest The incoming HTTP request.
	 * @param stdClass $oResponse Response data. This will be sent to the client.
	 *
	 * @return void
	 *
	 */
	public function PreProcess(HttpRequest $oHttpRequest, stdClass $oResponse) : void;
	
	
	/**
	 * A hook that can execute logic after all other processing is done.
	 *
	 * @param HttpRequest $oHttpRequest The incoming HTTP request.
	 * @param stdClass $oResponse Response data. This will be sent to the client.
	 *
	 * @return void
	 *
	 */
	public function PostProcess(HttpRequest $oHttpRequest, stdClass $oResponse) : void;


	/**
	 * A hook to allow additional filtering of any messages that may be returned to the client.
	 *
	 * @param HttpRequest $oHttpRequest The incoming HTTP request.
	 * @param DBObjectSet $oSet The object set to filter.
	 *
	 * @return DBObjectSet
	 *
	 */
	public function ProcessMessages(HttpRequest $oHttpRequest, DBObjectSet $oSet) : void;
	
}

/**
 * Class Server. A news server that processes incoming HTTP requests and lists all applicable messages for the news client (requester).
 */
class Server {

	/**
	 * @var HttpRequest $oHttpRequest The incoming HTTP request.
	 */
	private $oHttpRequest;

	/**
	 * @var object&iServer[] $aExtensions List of third-party server extensions.
	 */
	private $aExtensions = [];
	
	/**
	 * Returns all the relevant messages for an instance.
	 * 
	 * @param eApiVersion $eApiVersion API version (already validated).
	 * @param array $aMessages Reference to an array that will be filled with messages (array structure with keys/values for the JSON structure).
	 * @param stdClass $oIconLib An object whose properties will be 'ref_<md5_of_value>', value = stdClass with data, mimetype, filename).
	 *
	 * @return void
	 */
	public function GetMessagesForInstance(array &$aMessages, stdClass $oIconLib) : void {

		$eApiVersion = $this->oHttpRequest->GetApiVersion();

		Helper::Trace('Getting messages. API version: %1$s', $eApiVersion->value);
		
		// API version 1.0: 
		// Did not support cryptography (and possibly it was also missing the "api_version" parameter), so the parameter should always be present.

		// API version 1.1.0:
		// The parameter "api_version" must be included, and set to 1.1.0.
	
		// Output all messages with their translations
		// Theoretically additional filtering could be applied to reduce JSON size;
		// for instance to only return messages if certain extensions are installed
		// Or logging could be added
		
		// Some publications might still be hidden (surprise announcement, promo, limited offer, ...).
		// Only select the messages that are currently published.
		$sOQL = '
			SELECT ThirdPartyNewsMessage 
			WHERE 
				start_date <= NOW() AND 
				(
					ISNULL(end_date) = 1 OR 
					end_date >= NOW()
				)
		';
		$oSetMessages = new DBObjectSet(DBObjectSearch::FromOQL_AllData($sOQL));

		foreach($this->aExtensions as $oExtension) {
			$oExtension->ProcessMessages($this->oHttpRequest, $oSetMessages);
		}
		
		$aMessages = [];
		
		// - Loop through the messages.
		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSetMessages->Fetch()) {
			
			// - Enrich the JSON structure with the translations.

				$aTranslations = [];
				
				/** @var ormLinkSet $oSetTranslations Translations of a message */
				$oSetTranslations = $oMessage->Get('translations_list');
				
				while($oTranslation = $oSetTranslations->Fetch()) {
					
					$aTranslations[] = [
						'language' => $oTranslation->Get('language'),
						'title' => $oTranslation->Get('title'),
						'text' => $oTranslation->Get('text'),
						'url' => $oTranslation->Get('url')
					];
					
				}
			
			// - Prepare the icon.
			
				$oAttDef = MetaModel::GetAttributeDef('ThirdPartyNewsMessage', 'icon');
				/** @var array|null $aIcon Null or array with keys data, mimetype, filename (and downloads_count) */
				$aIcon = $oAttDef->GetForJSON($oMessage->Get('icon'));
				
				// Note: all attributes should exist on the ThirdPartyNewsMessage class of the client-side.
				switch($eApiVersion) {
					
					case eApiVersion::v1_0_0:

						// API 1.0 is likely only still in use by 1 customer.
						$aMessages[] = [
							'thirdparty_message_id' => $oMessage->Get('thirdparty_message_id'),
							'title' => $oMessage->Get('title'),
							'icon' => $aIcon,
							'start_date' => $oMessage->Get('start_date'),
							'end_date' => $oMessage->Get('end_date'),
							'priority' => $oMessage->Get('priority'),
							'target_profiles' => 'Administrator', // "Administrators" were always targeted in the messages that were published.
							'translations_list' => $aTranslations
						];
						break;
						
					case eApiVersion::v1_1_0:
					
						$aMessages[] = [
							'thirdparty_message_id' => $oMessage->Get('thirdparty_message_id'),
							'title' => $oMessage->Get('title'),
							'icon' => $aIcon,
							'start_date' => $oMessage->Get('start_date'),
							'end_date' => $oMessage->Get('end_date'),
							'priority' => $oMessage->Get('priority'),
							'oql' => $oMessage->Get('oql'),
							'translations_list' => $aTranslations
						];
						break;

					case eApiVersion::v2_0_0:

						$sIconRef = null;

						if($aIcon !== null) {
							
							$sIconRef = 'ref_'.md5(json_encode($aIcon));

							// No need to check if it was set.
							$oIconLib->$sIconRef = new stdClass();
							$oIconLib->$sIconRef->data = $aIcon['data'];
							$oIconLib->$sIconRef->mimetype = $aIcon['mimetype'];
							$oIconLib->$sIconRef->filename = $aIcon['filename'];

						}

						$aMessages[] = [
							'thirdparty_message_id' => $oMessage->Get('thirdparty_message_id'),
							'title' => $oMessage->Get('title'),
							'icon' => $sIconRef,
							'start_date' => $oMessage->Get('start_date'),
							'end_date' => $oMessage->Get('end_date'),
							'priority' => $oMessage->Get('priority'),
							'oql' => $oMessage->Get('oql'),
							'translations_list' => $aTranslations
						];
						break;

					default:
						// Can not be reached because of our prior checks.
						break;
					
				}
			
		}
			
	}


	/**
	 * Processes an incoming HTTP request from a news client.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function ProcessIncomingRequest() : void {
		
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
		
		$this->oHttpRequest = new HttpRequest();
		
		// Don't use Combodo's JsonPage. The server response will be JSONP.
		$oPage = new JsonPage();

		$eClientApiVersion = $this->oHttpRequest->GetApiVersion();
		$eClientCryptoLib = $this->oHttpRequest->GetCryptoLib();
		
		/** @var stdClass $oResponse */
		$oResponse = new stdClass();

		$this->aExtensions = array_map(function(string $sClass) {
			return new $sClass;
		}, Helper::GetImplementations(iServerExtension::class));

		// - Execute pre-processors.
			
			foreach($this->aExtensions as $oExtension) {
				$oExtension->PreProcess($this->oHttpRequest, $oResponse);
			}
			
		switch($this->oHttpRequest->GetOperation()) {
			
			case eOperation::GetMessagesForInstance:
				
				// - Retrieve messages.

					$aMessages = [];
					$oIconLib = new stdClass();
					$this->GetMessagesForInstance($aMessages, $oIconLib);
					
				// - Prepare the data.

					switch($eClientApiVersion) {

						case eApiVersion::v2_0_0:

							// - The structure will always be the same.
							$oResponse->crypto_lib = $eClientCryptoLib->value;
							$oResponse->messages = $aMessages;
							$oResponse->icons = $oIconLib;
							// The 'refresh_token' should be set by one iServerExtension.

							break;

						case eApiVersion::v1_1_0:
						case eApiVersion::v1_0_0:

							// - Note: In the end, the response may still be turned into an array for non-encrypted responses. See further.
							$oResponse->encryption_library = $eClientCryptoLib->value; // This will keep the capital of 'Sodium'.
							$oResponse->messages = $aMessages;

							break;
							
					}

					// - Sign, if necessary.

						if($eClientCryptoLib == eCryptographyLibrary::Sodium) {
								
							// If Sodium is available, use it to sign the messages.
							// The messages are not secret; the signing is just to verify authenticity.
							$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoSign);
							$sSignature = sodium_crypto_sign_detached(json_encode($aMessages), $sPrivateKey);
							
							$oResponse->signature = sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE);

						}

					// - Prepare output.

						if($eClientCryptoLib == eCryptographyLibrary::None && (
								$eClientApiVersion == eApiVersion::v1_1_0 || 
								$eClientApiVersion == eApiVersion::v1_1_0
						)) {

							$oResponse = $oResponse->messages;

						}
			
				break;
				
			case eOperation::ReportReadStatistics:
				break;
				

				
			default:
				
				// Invalid operation. This should not occur.
				throw new Exception('Unexpected cases when handling operations. Please report this as a bug.');
				break;

		}

		// - Last change to alter response.
			
			foreach($this->aExtensions as $oExtension) {
				$oExtension->PostProcess($this->oHttpRequest, $oResponse);
			}

		// - If a callback method is specified, wrap the output in a JSONP callback.

			$sOutput = json_encode($oResponse);

			$sCallBackMethod = utils::ReadParam('callback', '', false, 'parameter');
			if($sCallBackMethod != '') {
				$sOutput = $sCallBackMethod.'('.$sOutput.');';
			
			}

			Helper::Trace('Response to client:');
			Helper::Trace($sOutput);

		// - Print the output.
			$oPage->output($sOutput);

	}


	/**
	 * Returns the HTTP request that is currently being handled.
	 *
	 * @return HttpRequest
	 */
	public function GetHttpRequest() : HttpRequest {

		return $this->oHttpRequest;

	}

}
