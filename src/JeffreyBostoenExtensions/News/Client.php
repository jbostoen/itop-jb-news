<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.241010
 *
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use ormDocument;
use UserRights;
use utils;

// iTop classes.
use KeyValueStore;
use ThirdPartyNewsMessage;
use ThirdPartyNewsMessageTranslation;

// Generic.
use Exception;
use stdClass;


/**
 * Enum eOperationMode. The operation mode.
 */
enum eOperationMode : string {
	case Cron = 'cron';
	case Mitm = 'mitm';
}

/**
 * Interface iSource. Interface to use when implementing news sources.
 */
interface iSource {
	
	/**
	 * A source can add extra data to the payload that will be sent from the client to the server.
	 * 
	 * @param eOperation $eOperation The operation that is being executed.
	 * @param stdClass $oPayload The payload that will be sent from the client to the server.
	 * 
	 * @details Mind that by default certain parameters are already included in the HTTP request to the news source.
	 * @see Client::GetPayload()
	 *
	 * @return array Key/value pairs to send to the news source.
	 */
	public static function SetPayload(eOperation $eOperation, stdClass $oPayload);
	
	/**
	 * Returns the base64 encoded public key for the Sodium implementation (crypto box).  
	 * A news source must share a public key with the clients.  
	 * This public key will be used to seal data which can then only be read by the news server.
	 *
	 * @return string
	 */
	public static function GetPublicKeySodiumCryptoBox();

	/**
	 * Returns the base64 encoded public key for a Sodium implementation (crypto sign).  
	 * A news source must share a public key with the clients.  
	 * This public key will be used to verify the news message contents of the source, to avoid tampering.
	 *
	 * @return string
	 */
	public static function GetPublicKeySodiumCryptoSign();
	
	/**
	 * Returns the name of third party news source.  
	 * This is used as a unique identifier, so do not use an existing one. It should remain consistent.
	 *
	 * @return string The name of the third party news source.
	 */
	public static function GetThirdPartyName();

	/**
	 * Returns the URL of the news source. 
	 * News will be retrieved from this source.
	 */
	public static function GetUrl();

}

/**
 * Class NewsClient. A news client that retrieves messages from one or more third-party (non Combodo) news sources (person/organization).
 */
abstract class Client {
	
	/** @var array $aCachedPayloads This array is used to cache some payloads that are the same for multiple news sources. */
	public static $aCachedPayloads = [];


	/**
	 * Returns a sanitized version (only keeping alphabetical characters and numbers) of the third-party news source name.
	 *
	 * @param string $sNewsSource
	 * @return string
	 */
	public static function GetSanitizedNewsSourceName(string $sNewsSource) : string {
		
		// Sanitize the news source name to be used as a key in the KeyValueStore.
		// This is to avoid issues with special characters, spaces, etc.
		return 'source_'. preg_replace('/[^a-zA-Z0-9]+/', '', $sNewsSource);
		
	}
	
	/**
	 * Returns an object set of key/value pairs. Each key will be an identifier for a news source, and the value a timestamp.
	 *
	 * @return array Hashtable where the key is th news source name, and the value is the last retrieved date/time.
	 */
	public static function GetLastRetrievedDateTimePerNewsSource() : array {
		
		$aDateTimes = [];

		// - Ensure every news source has a last retrieved date/time.

			foreach(static::GetSources() as $sNewsSource) {

				$sNewsSource = static::GetSanitizedNewsSourceName($sNewsSource);
				$aDateTimes[$sNewsSource] = '1970-01-01 00:00:00'; // Default value
			
			}

		// - Where available, get the real timestamp.

			$oFilter = DBObjectSearch::FromOQL_AllData('SELECT KeyValueStore WHERE namespace = :namespace', [
				'namespace' => Helper::MODULE_CODE
			]);
			$oSet = new DBObjectSet($oFilter);
			
			while($oKeyValue = $oSet->Fetch()) {

				if(preg_match('/_last_retrieval$/', $oKeyValue->Get('key_name'))) {
					$aDateTimes[$oKeyValue->Get('key_name')] = $oKeyValue->Get('value');
				}

			}

		Helper::Trace('Last retrieved timestamps: %1$s', json_encode($aDateTimes, JSON_PRETTY_PRINT));

		return $aDateTimes;
		
	}
	
	
	/**
	 * Saves a key/value for a particular news source.
	 *
	 * @param string $sNewsSource Name of the news source (can be unsanitized).
	 * @param string $sSuffix Suffix to append to the sanitized version of the news source and "_". (e.g. 'last_retrieval', 'token').
	 * @param string $sValue The value to store.
	 *
	 * @return void
	 */
	public static function StoreKeyValue(string $sNewsSource, string $sSuffix, string $sValue) : void {
		
		$sKeyName = static::GetSanitizedNewsSourceName(basename($sNewsSource)).'_'.$sSuffix;
		
		/** @var KeyValueStore $oKeyValueStore */
		$oKeyValueStore = Helper::GetKeyValueStore($sKeyName) ?? MetaModel::NewObject('KeyValueStore', [
			'namespace' => Helper::MODULE_CODE,
			'key_name' => $sKeyName,
		]);
		$oKeyValueStore->Set('value', $sValue);
		$oKeyValueStore->DBWrite();
		
	}
	
	
	
	/**
	 * Gets all the relevant messages for this instance.
	 *
	 * @return void
	 */
	public static function RetrieveFromRemoteServer() : void {
		
		$eOperation = eOperation::GetMessagesForInstance;
		
		// Build list of news sources
		// -
		
			$aSources = static::GetSources();
			Helper::Trace('Request messages from %1$s remote news source(s).', count($aSources));
		
			
		// Request messages from each news source
		// -
		
		
			foreach($aSources as $sSourceClass) {
				
				$sApiResponse = static::DoPost($sSourceClass, $eOperation);

				if($sApiResponse === null) {
					continue;
				}

				static::ProcessRetrievedMessages($sApiResponse, $sSourceClass);
				
			}
			
	}
	
	/**
	 * Process retrieved messages.
	 * 
	 * In case the HTTP response from the news sserver fails to meet the expected format, it will be logged and the process will be aborted gracefully.
	 *
	 * @param stdClass $oResponse The HTTP response from the news server.
	 * @param string $sSourceClass Name of the news source class.
	 *
	 * @return void
	 *
	 */
	public static function ProcessRetrievedMessages(stdClass $oResponse, string $sSourceClass) : void {

		$sThirdPartyName = $sSourceClass::GetThirdPartyName();

		// Assume these messages are in the correct format.
		// If the format has changed in a backwards incompatible way, the API should simply not return any messages.
		// (except perhaps for one to recommend to upgrade the extension)
					
		Helper::Trace('Response: %1$s', PHP_EOL.json_encode($oResponse, JSON_PRETTY_PRINT));
		
		$eCryptographyLib = Helper::GetCryptographyLibrary();
			
		// - Check if a valid data structure is in place (API 1.1.0 specification).
		
			if(!property_exists($oResponse, 'messages')) {

				Helper::Trace('No messages found.');
				return;

			}

		$aMessages = $oResponse->messages;

		// If cryptography was enabled, the HTTP response must contain the same encryption library and a signature.
		if(property_exists($oResponse, 'signature') == true) {

			// Implement supported libraries. For now, only Sodium (so this check is currently a bit redundant).
			if($eCryptographyLib == eCryptographyLibrary::Sodium) {
			
				$sSignature = sodium_base642bin($oResponse->signature, SODIUM_BASE64_VARIANT_URLSAFE);
				$sPublicKey = sodium_base642bin($sSourceClass::GetPublicKeySodiumCryptoSign(), SODIUM_BASE64_VARIANT_URLSAFE);
					
				// Verify using public key.
				if(sodium_crypto_sign_verify_detached($sSignature, json_encode($aMessages), $sPublicKey)) {
					
					// Verified.
					Helper::Trace('Signature is valid.');
					
				} 
				else {
					
					Helper::Trace('Unable to verify the signature using the public key for "%1$s".', $sSourceClass);
					return;
					
				}
				
			}
			else {
			
				// It seems the key "signature" is missing in the response.
				Helper::Trace('Invalid response: "signature" is missing.');
				return;
			
			}
		}
		
		// For easy reference, map the messages by their ID.
		$aRetrievedMessageIds = array_map(
			function($oMessage) {
				return $oMessage->thirdparty_message_id;
			},
			$aMessages
		);
		$aMessages = array_combine(
			$aRetrievedMessageIds,
			$aMessages
		);
		
		// - Preprocessing (common things for both insert, update).

			/** @var stdClass $oJsonMessage */
			foreach($aMessages as $oJsonMessage) {
				
				$aIcon = $oJsonMessage->icon;
				
				/** @var ormDocument|null $oIcon The specific icon for the news message. */
				$oIcon = null;
				if($aIcon->data != '' && $aIcon->mimetype != '' && $aIcon->filename != '') {
					$oIcon = new ormDocument(base64_decode($aIcon->data), $aIcon->mimetype, $aIcon->filename);
				}

				$oJsonMessage->icon = $oIcon;

			}


		// - Get messages currently in database for this third party source.
			
			$oFilterMessages = new DBObjectSearch('ThirdPartyNewsMessage');
			$oFilterMessages->AddCondition('thirdparty_name', $sThirdPartyName, '=');
			$oSetMessages = new DBObjectSet($oFilterMessages);
			$aKnownMessageIds = [];

		
		// - Loop through the messages that are already in the database.
			
			$oSetMessages->Rewind();

			/** @var ThirdPartyNewsMessage $oMessage */
			while($oMessage = $oSetMessages->Fetch()) {

				$aKnownMessageIds[] = $oMessage->Get('thirdparty_message_id');

				// - Do not intervene if the message on the current iTop instance was created manually.
				//   If it was manually created, assume this is the news provider, not the news client.
				//   A news provider may have messages in the database that are not visible to news clients yet (thus missing in the HTTP response).
				if($oMessage->Get('manually_created') == 'yes') {
					continue;
				}
				
				// - If the message is not in the retrieved messages (e.g. retracted), it should be deleted from the database as well.
				if(in_array($oMessage->Get('thirdparty_message_id'), $aRetrievedMessageIds) == false) {
					$oMessage->DBDelete();
				}

				$aMessages[$oMessage->Get('thirdparty_message_id')]->DBObject = $oMessage;
				
			}

		// - Loop through the messages received in the HTTP response to create ThirdPartyNewsMessage objects.

			/** @var stdClass $oJsonMessage */
			foreach($aMessages as $oJsonMessage) {
				
				// - For the ones without a DBObject, create a new one.

					if(!property_exists($oJsonMessage, 'DBObject')) {
						/** @var ThirdPartyNewsMessage $oMessage */
						Helper::Trace('Create new ThirdPartyNewsMessage object for message ID "%1$s".', $oJsonMessage->thirdparty_message_id);
						$oJsonMessage->DBObject = MetaModel::NewObject('ThirdPartyNewsMessage', []);
					}
					else {
						Helper::Trace('Found existing ThirdPartyNewsMessage object for message ID "%1$s".', $oJsonMessage->thirdparty_message_id);
					}
					
				// - Every message (HTTP response) has a ThirdPartyNewsMessage object associated with it now.

					/** @var ThirdPartyNewsMessage $oMessage */
					$oMessage = $oJsonMessage->DBObject;

				// - Copy the properties to the DBObject.
				
					// - Use the internal name of the news source (as known to this instance).
					$oMessage->Set('thirdparty_name', $sThirdPartyName);

					// - Otherwise, trust the source.
					$oMessage->Set('thirdparty_message_id', $oJsonMessage->thirdparty_message_id);
					$oMessage->Set('title', $oJsonMessage->title);
					$oMessage->Set('start_date', $oJsonMessage->start_date);
					$oMessage->Set('end_date', $oJsonMessage->end_date ?? '');
					$oMessage->Set('priority', $oJsonMessage->priority);
					$oMessage->Set('manually_created', 'no'); 
					
					// - Icon.
						
						if($oJsonMessage->icon !== null) {
							$oMessage->Set('icon', $oJsonMessage->icon);
						}

				// - Save.

					$oMessage->AllowWrite(true);
					$oMessage->DBWrite();
					
			}

		// - Now process the translations.

		// - Fetch the existing translations.
		
			$aMessageIds = array_map(
				function(stdClass $oJsonMessage) {
					return $oJsonMessage->DBObject->GetKey();
				},
				$aMessages
			);
			$oFilterTranslations = new DBObjectSearch('ThirdPartyNewsMessageTranslation');
			$oFilterTranslations->AddCondition('message_id', $aMessageIds, 'IN');
			$oSetTranslations = new DBObjectSet($oFilterTranslations);

		// - Index the existing translations.

			/**
			 * @var array $aExistingTranslations An array in which ThirdPartyNewsMessageTranslation will be stored.  
			 * First level = third-party message ID,  
			 * second level = language code,  
			 * third level = ThirdPartyNewsMessageTranslation object
			 */
			$aExistingTranslations = [];

			while($oTranslation = $oSetTranslations->Fetch()) {

				$aExistingTranslations[$oTranslation->Get('thirdparty_message_id')][$oTranslation->Get('language')] = $oTranslation;

			}
				
		// - Process all the translations of every message.

			foreach($aMessages as $oJsonMessage) {

				// - Process each translation.
				/** @var stdClass $oJsonTranslation */
				foreach($oJsonMessage->translations_list as $oJsonTranslation) {

					try {
							
						// Check if this translation already exists.
						if(
							// No translations at all yet for this message.
							!array_key_exists($oJsonMessage->thirdparty_message_id, $aExistingTranslations) || 
							// No translation for this specific language yet.
							!array_key_exists($oTranslation->language, $aExistingTranslations[$oJsonMessage->thirdparty_message_id])
						) {

							/** @var ThirdPartyNewsMessageTranslation $oTranslation */
							$oTranslation = MetaModel::NewObject('ThirdPartyNewsMessageTranslation', [
								'message_id' => $oJsonMessage->DBObject->GetKey(), // Remap
								'language' => $oJsonTranslation->language,
							]);

						}
						else {

							/** @var ThirdPartyNewsMessageTranslation $oTranslation */
							$oTranslation = $aExistingTranslations[$oJsonMessage->thirdparty_message_id][$oJsonTranslation->language];

						}

						$oTranslation->Set('title', $oJsonTranslation->title);
						$oTranslation->Set('text', $oJsonTranslation->text);
						$oTranslation->Set('url', $oJsonTranslation->url);
						$oTranslation->AllowWrite(true);
						$oTranslation->DBWrite();
					
					}
					catch(Exception $e) {

						// Fail silently.
						// Could be a 'non supported language' issue?
						Helper::Trace('Failed to process translation for message ID "%1$s" and language "%2$s": %3$s', 
							$oJsonMessage->DBObject->GetKey(), 
							$oJsonTranslation->language, 
							$e->getMessage()
						);

					}
				
				}

			}
		
		// - Save this as a successful execution (even if some messages or translations were not processed, e.g. because of an error).
		
			static::StoreKeyValue($sSourceClass, 'last_retrieval', date('Y-m-d H:i:s'));

	
				
	}
	
	/**
	 * Posts info back to the news server, unless this is disabled (iTop configuration).
	 * 
	 * This could be used to report statistics about (un)read messages.
	 *
	 * @return void
	 *
	 */
	public static function PostToRemoteServer() : void {

		if(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'disable_reporting', false) == true) {
			Helper::Trace('Reporting has been disabled.');
			return;
		}
		
		$eOperation = eOperation::ReportReadStatistics;
		
		Helper::Trace('Send (anonymous) data to remote news sources.');
		
		// Other hooks may have been executed already.
		// Do not leak sensitive data, OQL queries may contain names etc.
			
		// - Post statistics on messages to the news server.
		
			$aSources = static::GetSources();
			
			foreach($aSources as $sSourceClass) {
				
				// Not interested in the response of this call:
				static::DoPost($sSourceClass, $eOperation);
	
			}
			
		
	}
	
	
	/**
	 * Returns class names of active news sources.
	 *
	 * @return string[] The names of the classes of the news sources that implement iSource.
	 */
	public static function GetSources() : array {

		$aSources = [];
		$aDisabledSources = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'disabled_sources', []);
			
		foreach(get_declared_classes() as $sClassName) {
			
			$aImplementations = class_implements($sClassName);
			if(in_array('JeffreyBostoenExtensions\News\iSource', $aImplementations) == true || in_array('iSource', class_implements($sClassName)) == true) {
				
				// Skip source if temporarily disabled (perhaps advised at some point for some reason, without needing to disable or uninstall the extension completely)
				$sThirdPartyName = $sClassName::GetThirdPartyName();
				if(in_array($sThirdPartyName, $aDisabledSources) == true) {
					Helper::Trace('Source "%1$s" is disabled in the iTop configuration.');
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
	 * @param string $sSourceClass Name of the news source class.
	 * @param eOperation $eOperation The operation that is being executed.
	 * @param eOperationMode $eOperationMode The operation mode (e.g. cron, mitm).
	 *
	 * @return stdClass The payload.
	 *
	 * @details Mind that this is executed over and over for each news source.
	 */
	public static function GetPayload(string $sSourceClass, eOperation $eOperation, eOperationMode $eOperationMode) : stdClass {
		
		$sNewsUrl = $sSourceClass::GetUrl();
		
		$sApp = defined('ITOP_APPLICATION') ? ITOP_APPLICATION : 'unknown';
		$sVersion = defined('ITOP_VERSION') ? ITOP_VERSION : 'unknown';
		
		$sInstanceHash = Helper::GetInstanceHash();
		$sInstanceHash2 = Helper::GetInstanceHash2();
		$sDBUid = Helper::GetDatabaseUID();
		
		$eCryptographyLib = Helper::GetCryptographyLibrary();
		
		/** @var stdClass $oPayload */
		$oPayload = new stdClass();
		$oPayload->operation = $eOperation->value;
		$oPayload->instance_hash = $sInstanceHash;
		$oPayload->instance_hash2 = $sInstanceHash2;
		$oPayload->db_uid = $sDBUid;
		$oPayload->env =  MetaModel::GetEnvironment(); // Note: utils::GetCurrentEnvironment() only returns the correct value on the second call in the same sesssion.
		$oPayload->app_name = $sApp;
		$oPayload->app_version = $sVersion;
		$oPayload->crypto_lib = $eCryptographyLib->value;
		$oPayload->api_version = eApiVersion::v2_0_0->value;
		$oPayload->token = static::GetClientToken($sSourceClass)->Get('value');
		$oPayload->mode = $eOperationMode->value;
		
		if(strpos($sNewsUrl, '?') !== false) {
			
			// To understand the part below:
			// To make things look more pretty, the URL for a news source could point to a generic domain: 'itop-news.domain.org'.
			// This could be an index.php file that simply calls an iTop instance.
			// The index.php script (some sort of proxy) could act as a client to an iTop installation with the "server" setting in this extension set to enabled.
			// It could make a call to: https://localhost:8182/iTop-clients/web/pages/exec.php?&exec_module=jb-news&exec_page=index.php&exec_env=production-news&operation=get_messages_for_instance&version=1.0 
			// and it would also need the originally appended parameters that were sent to 'itop-news.domain.org'.
			$sParameters = explode('?', $sNewsUrl)[1];
			parse_str($sParameters, $aParameters);

			foreach($aParameters as $sKey => $sValue) {
				$oPayload->{$sKey} = $sValue;
			}
			
		}
		
			
		if($eOperation == eOperation::ReportReadStatistics) {
			
			// - Get generic info (not specifically for this one news source).
			
				// - Perhaps this info is already cached for another news source.
				if(isset(static::$aCachedPayloads[$eOperation->value]) == true) {
					
					/** @var int[] $aExtTargetUsers Array to store user IDs of users for whom the news extension is enabled. */
					$aExtTargetUsers = static::$aCachedPayloads[$eOperation->value]['target_users'];
					
					/** @var DBObjectSet[] $oSetStatuses Object set of ThirdPartyMessageUserStatus. */
					$oSetStatuses = static::$aCachedPayloads[$eOperation->value]['read_states'];
					
				}
				else {
					
					// - Build list of target users (news extension).
						
						$sOQL = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'oql_target_users', 'SELECT User');
						$oFilterUsers = DBObjectSearch::FromOQL_AllData($sOQL);
						$oSetUsers = new DBObjectSet($oFilterUsers);
						
						$aExtTargetUsers = [];
						
						while($oUser = $oSetUsers->Fetch()) {
							
							// By default, there is no 'last login' data unfortunately, unless explicitly stated.
							$aExtTargetUsers[] = $oUser->GetKey();
							
						}
						
						static::$aCachedPayloads[$eOperation->value]['target_users'] = $aExtTargetUsers;
						
					// - Get set of ThirdPartyMessageUserStatus (will be used to loop over each time).
						
						$oFilterStatuses = DBObjectSearch::FromOQL_AllData('SELECT ThirdPartyMessageUserStatus');
						$oSetStatuses = new DBObjectSet($oFilterStatuses);
						static::$aCachedPayloads[$eOperation->value]['read_states'] = $oSetStatuses;
						
				}
				
			// - Get ThirdPartyNewsMessage objects of this source and obtain specific info to report back to this source only.
			
				$sThirdPartyName = $sSourceClass::GetThirdPartyName();
				
				$oFilterMessages = DBObjectSearch::FromOQL_AllData('SELECT ThirdPartyNewsMessage WHERE thirdparty_name = :thirdparty_name', [
					'thirdparty_name' => $sThirdPartyName
				]);
				$oSetMessages = new DBObjectSet($oFilterMessages);
		
				$aMessages = [];
				
				while($oMessage = $oSetMessages->Fetch()) {
					
					// Determine users targeted specifically by the newsroom message.
					// ( Based on "oql" attribute, but might *also* be restricted because of the global "oql_target_users" setting.)
					
						try {
								
							$oFilterTargetUsers = DBObjectSearch::FromOQL_AllData($oMessage->Get('oql'));
							if($oFilterTargetUsers === null) {
								throw new Exception();
							}
							
							$oSetUsers = new DBObjectSet($oFilterTargetUsers);

						}
						catch(Exception $e) {

							// Scenarios where a failure could occur: 
							// - Upon failure - likely when upgrading from an old version where "OQL" is not supported (API version 1.0 - deprecated).
							// - Could also happen when an OQL query turns out to be invalid.
							$oAttDef = MetaModel::GetAttributeDef('ThirdPartyNewsMessage', 'oql');
							$oFilterTargetUsers = DBObjectSearch::FromOQL_AllData($oAttDef->GetDefaultValue());
							$oSetUsers = new DBObjectSet($oFilterTargetUsers);

						}
						
						
						$aTargetUsers = [];
						
						/** @var \User $oUser An iTop user */
						while($oUser = $oSetUsers->Fetch()) {
							
							$aTargetUsers[] = $oUser->GetKey();
							
						}
						
						$aMessages[(String)$oMessage->Get('thirdparty_message_id')] = [
							'target_users' => $aTargetUsers,
							'users' => [], // Each user who actually marked the message as "read".
							'read_date' => [], // See users above - This is the read date for each user.
							'first_shown_date' => [], // See users above - This is the first shown date for each user.
							'last_shown_date' => [], // See users above - This is the last shown date for each user.
						];
					
					// Report when messages were read (users stay anonymous to the provider, only IDs are shared).
					
					$oSetStatuses->Rewind();
					while($oStatus = $oSetStatuses->Fetch()) {
						
						if($oStatus->Get('message_id') == $oMessage->GetKey()) {
					
							$aMessages[(String)$oMessage->Get('thirdparty_message_id')]['users'][] = $oStatus->Get('user_id');

							foreach(['first_shown_date', 'last_shown_date', 'read_date'] as $sAttCode) {
								$aMessages[(String)$oMessage->Get('thirdparty_message_id')][$sAttCode][] = $oStatus->Get($sAttCode);
							}
							
						}
					
					}
					
				}
				

			// - Add this info to the payload.
			
				$oPayload->read_status =  new stdClass();
				$oPayload->read_status->target_oql_users = $aExtTargetUsers;
				$oPayload->read_status->messages = $aMessages;
			
		}
		
		
		// -These are default parameters, which can be overridden or extended by the provider.
			$sSourceClass::SetPayload($eOperation, $oPayload);

		// - Return the final payload.

		return $oPayload;
				
	}
	
	/**
	 * Prepare the payload: Perform JSON encoding and then base64 encoding.  
	 * If Sodium is available, the client attempts to encrypt it before sending it to the server.
	 *
	 * @param string $sSourceClass Name of the news source class.
	 * @param stdClass $oPayload Payload to be prepared.
	 *
	 * @return string Binary data
	 */
	public static function PreparePayload(string $sSourceClass, stdClass $oPayload) : string {
		
		$sPayload = json_encode($oPayload);
		
		if(Helper::GetCryptographyLibrary() == eCryptographyLibrary::Sodium) {
			
			// There is no check here to validate if this key is valid.
			// It is the responsibility of a news provider to ensure this is okay.
			$sPublicKey = sodium_base642bin($sSourceClass::GetPublicKeySodiumCryptoBox(), SODIUM_BASE64_VARIANT_URLSAFE);
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
	 * @param string $sSourceClass Name of the news source class.
	 * @param eOperation $eOperation The operation that is being executed.
	 *
	 * @return stdClass|null Null when there is no response (cURL error occurred); otherwise a string.
	 *
	 * @throws Exception
	 */
	public static function DoPost(string $sSourceClass, eOperation $eOperation) : stdClass|null {

		// Unencrypted payload (easier for debugging).
		$oPayload = static::GetPayload($sSourceClass, $eOperation, eOperationMode::Cron);
		$sUrl = $sSourceClass::GetUrl();
				
		Helper::Trace('Url: %1$s', $sUrl);
		Helper::Trace('Data: %1$s', json_encode($oPayload, JSON_PRETTY_PRINT));
		
		// Prepare the payload.
		$sPayload = static::PreparePayload($sSourceClass, $oPayload);
		
		$aPostData = [
			'operation' => $eOperation->value,
			'api_version' => eApiVersion::v2_0_0->value,
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

		$iHttpCode = curl_getinfo($cURLConnection, CURLINFO_HTTP_CODE);
		if($iHttpCode != 200) {

			Helper::Trace('Error: cURL did not return HTTP status code 200, but %1$s', $iHttpCode);
			Helper::Trace($sApiResponse);
			return null;

		}

		curl_close($cURLConnection);

		// - Common behavior for all requests:
		//   For any valid response, try to decode and save the token.

			/** @var stdClass|null $oResponse */
			$oResponse = json_decode($sApiResponse);

			if($oResponse !== null) {
				static::UpdateClientToken($sSourceClass, $oResponse);
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
	 * @param string $sSourceClass
	 * @return KeyValueStore
	 */
	public static function GetClientToken(string $sSourceClass) : KeyValueStore {
		
		// The client token is used to identify the client (i.e. this iTop instance) to the news server.
		// The client token is initially created by the client, and then sent to the news server.
		// Any third-party news server processors can use this token to identify the client, and send another one back that should be saved by the client.
		
		$sKeyName = static::GetSanitizedNewsSourceName(basename($sSourceClass)).'_client_token';

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
	 * Update the client token for the specified news source.
	 * 
	 * @param string $sSourceClass The name of the news source class.
	 * @param stdClass $oResponse The response from the news source, which should contain a "refresh_token" key.
	 *
	 * @return void
	 */
	public static function UpdateClientToken(string $sSourceClass, stdClass $oResponse) : void {
		
		// If a "refresh_token" was received (and it should be), store it.

		if(property_exists($oResponse, 'refresh_token') && is_string($oResponse->refresh_token) && strlen($oResponse->refresh_token) == (Helper::CLIENT_TOKEN_BYTES * 2)) {
					
			// Store the refresh token for this news source.
			// Do so before any other processing can fail; as it may be used to uniquely identify this instance.
			static::StoreKeyValue($sSourceClass, 'client_token', $oResponse->refresh_token);
			
		}
		else {
			
			// No refresh token received, while it was expected.
			// This can simply mean the server (server extension) does not support this feature yet.
			Helper::Trace('No valid "refresh_token" received from the news source "%1$s".', $sSourceClass);
			
		}

	}

}
