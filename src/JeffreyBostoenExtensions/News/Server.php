<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250712
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
	 * Processes actions.
	 * 
	 * It's possible to customize the behavior of the server when a client connects.
	 * 
	 * For example: Keep track of which instances last connected, process custom info that may have been added in the payload.
	 *
	 * @param eApiVersion $eApiVersion API version.
	 * @param eOperation $eOperation Operation.
	 * @param stdClass $oPayload Plain payload. This is received from the client.
	 * @param stdClass $oResponse Response data. This will be sent to the client.
	 *
	 * @return void
	 *
	 */
	public static function Process(eApiVersion $eApiVersion, eOperation $eOperation, stdClass $oPayload, stdClass $oResponse) : void;
	
}

/**
 * Class Server. A news server that is capable to process incoming HTTP requests and lists all applicable messages for the news client (requester).
 */
abstract class Server {
	
	/**
	 * Gets all the relevant messages for an instance.
	 * 
	 * @param eApiVersion $eApiVersion API version (already validated).
	 * @param array $aMessages Reference to an array that will be filled with messages (array structure with keys/values for the JSON structure).
	 * @param stdClass $oIcons An object whose properties will be 'ref_<md5_of_value>', value = stdClass with data, mimetype, filename).
	 *
	 * @return array
	 */
	public static function GetMessagesForInstance(eApiVersion $eApiVersion, array &$aMessages, stdClass $oIconLib) : void {

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
		
		$aMessages = [];

		/** @var array $aIcons Key = md5 of the JSON-encoded value; value = filename, mimetype, data of the icon. */
		$aIcons = [];
		
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
	 * Executes each third-party implementation of iServerExtension.
	 * Currently, these run after executing the default actions for a given 'operation'.
	 * 
	 * The goal is to allow third-party developers to extend the server-side behavior of the news server.
	 *
	 * @param eApiVersion, $eApiVersion API version.
	 * @param eOperation $eOperation Operation.
	 * @param stdClass $oPayload Payload (data) from the client.
	 * @param stdClass $oResponse The response that will be sent to the client.
	 *
	 * @return void
	 */
	public static function ExecuteThirdPartyServerExtensions(eApiVersion $eApiVersion, eOperation $eOperation, stdClass $oPayload, stdClass $oResponse) : void {
		
	
		// - Build list of processors.
		
			$aProcessors = [];
			foreach(get_declared_classes() as $sClassName) {
				$aImplementations = class_implements($sClassName);
				if(in_array('JeffreyBostoenExtensions\News\iServerExtension', $aImplementations) == true || in_array('iServerExtension', class_implements($sClassName)) == true) {
					
					$aProcessors[] = $sClassName;
					
				}
			}
			
		// - Run each processor.
			
			foreach($aProcessors as $sProcessor) {
				
				$sProcessor::Process($eApiVersion, $eOperation, $oPayload, $oResponse);
				
			}
		
	}

	/**
	 * Returns Sodium private key.
	 *
	 * @param eCryptographyKeyType $eKeyType Should be one of these: private_key_file_crypto_sign , private_key_file_crypto_box
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public static function GetKeySodium(eCryptographyKeyType $eKeyType) : string {
		
		$sKeyType = $eKeyType->value;

		// Get private key.
		$aKeySettings = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'sodium', []);
		
		if(is_array($aKeySettings) == false || array_key_exists($sKeyType, $aKeySettings) == false) {
			
			Helper::Trace('Missing %1$s key settings.', $sKeyType);
			throw new Exception('Missing '.$sKeyType.' key settings.');

		}
		
		$sKeyFile = $aKeySettings[$sKeyType];
			
		if(file_exists($sKeyFile) == false) {
			
			Helper::Trace('Missing %1$s key file.', $sKeyType);
			throw new Exception('Missing '.$sKeyType.' key file.');
		
		}
			
		$sKey = file_get_contents($sKeyFile);
		
		try {

			$sBinKey = sodium_base642bin($sKey, SODIUM_BASE64_VARIANT_URLSAFE);

		}
		catch(SodiumException $e) {

			Helper::Trace('Failed to use %1$s key to convert base64 to binary key: %2$s', $sKeyType, $e->getMessage());

		}

		return $sBinKey;
		
	}
	
	/**
	 * Gets (and if necessary decrypts) the payload that was sent to the server.
	 * 
	 * 1) Perform base64 decoding on the payload.
	 * 2) If it's not a JSON structure yet; try decrypting.
	 * 3) Decode the JSON structure.
	 *
	 * @param string $sPayload Payload
	 *
	 * @return stdClass Hash table.
	 */
	public static function GetPlainPayload(string $sPayload) : stdClass {
	
		if(trim($sPayload) == '') {
			Helper::Trace('Payload is empty.');
			throw new Exception('Payload is empty.');
		}

		Helper::Trace('Received payload: %1$s', $sPayload);
		
		// Payloads can be either encrypted or unencrypted (Sodium not available on the iTop instance that is requesting news messages).
		// Either way, they are base64 encoded.
		$sPayload = base64_decode($sPayload);
		
		// Doesn't seem regular JSON yet; try unsealing
		if(substr($sPayload, 0, 1) !== '{') {

			Helper::Trace('No JSON yet, try unsealing the payload.');
			
			$sPrivateKey = static::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoBox);
			// The public key must match the one defined in the "Source" (iSource) for this news provider.
			$sPublicKey = static::GetKeySodium(eCryptographyKeyType::PublicKeyCryptoBox);

			$sPayload = sodium_crypto_box_seal_open($sPayload, sodium_crypto_box_keypair_from_secretkey_and_publickey($sPrivateKey, $sPublicKey));
			
		}
		
		$oPayload = json_decode($sPayload);

		if($oPayload === null) {

			Helper::Trace('Unable to decode the payload. This is probably not JSON.');
			throw new Exception('Unable to decode the payload. This is probably not JSON.');

		}

		Helper::Trace('Plain payload: %1$s', json_encode($oPayload, JSON_PRETTY_PRINT));
		
		return $oPayload;
		
	}

}
