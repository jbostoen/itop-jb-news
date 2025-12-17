<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 *
 */

namespace JeffreyBostoenExtensions\News;

use JeffreyBostoenExtensions\News\Message;
use JeffreyBostoenExtensions\ServerCommunication\{
	Client as BaseClient,
	eOperation,
	eOperationMode,
};
use JeffreyBostoenExtensions\ServerCommunication\v210\HttpRequest;

// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use ormDocument;

// iTop classes.
use ThirdPartyNewsMessage;
use ThirdPartyNewsMessageTranslation;

// Generic.
use Exception;
use stdClass;

/**
 * Class Client. A common client to send and retrieve data from one or more third-party (non Combodo) external servers (person/organization).
 */
abstract class Client extends BaseClient {
	

	/**
	 * Returns an object set of key/value pairs. Each key will be an identifier for a external server, and the value a timestamp.
	 *
	 * @return array Hashtable where the key is the external server name, and the value is the last retrieved date/time.
	 */
	public static function GetLastRetrievedDateTimePerExternalServerSource() : array {
		
		$aDateTimes = [];

		// - Ensure every external server has a last retrieved date/time.

			foreach(static::GetSources() as $sExtServerSource) {

				$sExtServerSource = static::GetSanitizedExtServerName($sExtServerSource);
				$aDateTimes[$sExtServerSource] = '1970-01-01 00:00:00'; // Default value
			
			}

		// - Where available, get the real timestamp.

			$oFilter = DBObjectSearch::FromOQL_AllData('SELECT KeyValueStore WHERE namespace = :namespace AND key_name LIKE "%_last_retrieval"', [
				'namespace' => Helper::MODULE_CODE
			]);
			$oSet = new DBObjectSet($oFilter);
			
			while($oKeyValue = $oSet->Fetch()) {

				$sKey = str_replace('_last_retrieval', '', $oKeyValue->Get('key_name'));
				$aDateTimes[$sKey] = $oKeyValue->Get('value');

			}

		Helper::Trace('Last retrieved timestamps: %1$s', json_encode($aDateTimes, JSON_PRETTY_PRINT));

		return $aDateTimes;
		
	}


	/**
	 * Gets all the relevant messages for this instance.
	 *
	 * @return void
	 */
	public static function RetrieveMessagesFromExternalServer() : void {
		
		$eOperation = eOperation::GetMessagesForInstance;

		static::DoPostAll($eOperation);
			
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

		try {
			
			if($eOperation == eOperation::GetMessagesForInstance) {
				static::ProcessRetrievedMessages($oResponse, $sExtServerClass);
			}

		}
		catch(Exception $e) {

			Helper::Trace('Error occurred while processing response from %1$s:', static::GetSanitizedExtServerName($sExtServerClass));
			Helper::Trace('Exception: %1$s', $e->getMessage());
			Helper::Trace(json_encode($oResponse));

		}

	}
	
	/**
	 * Process retrieved messages.
	 * 
	 * In case the HTTP response from the external server fails to meet the expected format,  
	 * it will be logged and the process will be aborted gracefully.
	 *
	 * @param stdClass $oResponse The HTTP response from the external server.
	 * @param string $sExtServerClass Name of the external server class.
	 *
	 * @return void
	 *
	 */
	public static function ProcessRetrievedMessages(stdClass $oResponse, string $sExtServerClass) : void {

		$sThirdPartyName = $sExtServerClass::GetThirdPartyName();

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

		/** @var Message[] $aMessages */
		$aMessages = $oResponse->messages;

		// Implement supported libraries. For now, only Sodium (so this check is currently a bit redundant).
		if($eCryptographyLib == eCryptographyLibrary::Sodium) {

			// If cryptography was enabled, the HTTP response must contain the same encryption library and a signature.
			if(property_exists($oResponse, 'signature') == true) {

				// - Verify entire response (except signature) using public key.
					
					$sSignature = sodium_base642bin($oResponse->signature, SODIUM_BASE64_VARIANT_URLSAFE);
					$sPublicKey = sodium_base642bin($sExtServerClass::GetPublicKeySodiumCryptoSign(), SODIUM_BASE64_VARIANT_URLSAFE);

					$oClonedResponse = clone $oResponse;
					$oClonedResponse->signature = null;
					
					if(sodium_crypto_sign_verify_detached($sSignature, json_encode($oClonedResponse), $sPublicKey)) {
						
						// Verified.
						Helper::Trace('Signature is valid.');
						
					} 
					else {
						
						Helper::Trace('Unable to verify the signature using the public key for "%1$s".', $sExtServerClass);
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
		
		// - Pre-processing (common things for both insert, update).

			/** @var stdClass $oJsonMessage */
			foreach($aMessages as $oJsonMessage) {
				
				/** @var string|null $sIconRef */
				$sIconRef = $oJsonMessage->icon;
				
				/** @var ormDocument|null $oIcon The specific icon for the news message. */
				
				if($sIconRef !== null) {

					$oIconData = $oResponse->icons->{$sIconRef};					
					$oIcon = new ormDocument(base64_decode($oIconData->data), $oIconData->mimetype, $oIconData->filename);
					$oJsonMessage->icon = $oIcon;

				}

			}


		// - Get messages currently in database for this third party source.
			
			$oFilterMessages = new DBObjectSearch('ThirdPartyNewsMessage');
			$oFilterMessages->AddCondition('thirdparty_name', $sThirdPartyName, '=');
			$oSetMessages = new DBObjectSet($oFilterMessages);

		
		// - Loop through the messages that are already in the database.
			
			/** @var ThirdPartyNewsMessage $oMessage */
			while($oMessage = $oSetMessages->Fetch()) {

				// - Do not intervene if the message on the current iTop instance was created manually.
				//   If it was manually created, assume this is the news provider, not the news client.
				//   A news provider may have messages in the database that are not visible to news clients yet (thus missing in the HTTP response).
				if($oMessage->Get('manually_created') == 'yes') {
					// This message should not be processed further on either!
					// Theoretically speaking, it could be assumed that all messages in this set will be manually created (coming from the same source).
					Helper::Trace('Skipping ThirdPartyNewsMessage object for message ID "%1$s" (manually created on this instance).', $oJsonMessage->thirdparty_message_id);
					unset($aMessages[$oMessage->Get('thirdparty_message_id')]);
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
				
					// - Use the internal name of the external server (as known to this instance).
					$oMessage->Set('thirdparty_name', $sThirdPartyName);

					// - Otherwise, trust the source.
					$oMessage->Set('thirdparty_message_id', $oJsonMessage->thirdparty_message_id);
					$oMessage->Set('title', $oJsonMessage->title);
					$oMessage->Set('start_date', $oJsonMessage->start_date);
					$oMessage->Set('end_date', $oJsonMessage->end_date ?? '');
					$oMessage->Set('priority', $oJsonMessage->priority);
					$oMessage->Set('manually_created', 'no'); 
					
					// - Icon.
						
						$oMessage->Set('icon', $oJsonMessage->icon);

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
			 * First level = internal message ID,  
			 * second level = language code,  
			 * third level = ThirdPartyNewsMessageTranslation object
			 */
			$aExistingTranslations = [];

			while($oTranslation = $oSetTranslations->Fetch()) {

				$sKey = $oTranslation->Get('language').'_'.$oTranslation->Get('message_id');
				$aExistingTranslations[$sKey] = $oTranslation;

			}
				
		// - Process all the translations of every message.

			foreach($aMessages as $oJsonMessage) {

				// - Process each translation.
				/** @var stdClass $oJsonTranslation */
				foreach($oJsonMessage->translations_list as $oJsonTranslation) {

					try {
							
						// Check if this translation already exists.
						$sKey = $oJsonTranslation->language.'_'.$oJsonMessage->DBObject->GetKey();

						if(!array_key_exists($sKey, $aExistingTranslations)) {

							/** @var ThirdPartyNewsMessageTranslation $oTranslation */
							$oTranslation = MetaModel::NewObject('ThirdPartyNewsMessageTranslation', [
								'message_id' => $oJsonMessage->DBObject->GetKey(), // Remap
								'language' => $oJsonTranslation->language,
							]);

						}
						else {

							/** @var ThirdPartyNewsMessageTranslation $oTranslation */
							$oTranslation = $aExistingTranslations[$sKey];

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
		
			static::StoreKeyValue($sExtServerClass, 'last_retrieval', date('Y-m-d H:i:s'));

	
				
	}
	
	/**
	 * Posts info to the external server, unless this is disabled (iTop configuration).
	 * 
	 * This could be used to report statistics about (un)read messages.
	 *
	 * @return void
	 *
	 */
	public static function PostStatisticsToRemoteServer() : void {

		if(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'disable_reporting', false) == true) {
			Helper::Trace('Reporting has been disabled.');
			return;
		}
		
		$eOperation = eOperation::ReportReadStatistics;

		Helper::Trace('Send (anonymous) data to remote external servers.');
		
		// Other hooks may have been executed already.
		// Do not leak sensitive data, OQL queries may contain names etc.
			
		// - Post statistics on messages to the news server.

			static::DoPostAll($eOperation);
		
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
	 */
	public static function BuildHttpRequest(string $sExtServerClass, eOperation $eOperation, eOperationMode $eOperationMode) : HttpRequest {

		$oPayload = parent::BuildHttpRequest($sExtServerClass, $eOperation, $eOperationMode);
			
		if($eOperation == eOperation::ReportReadStatistics) {
			
			// - Get generic info (not specifically for this one external server).
			
				// - Perhaps this info is already cached for another external server.
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
			
				$sThirdPartyName = $sExtServerClass::GetThirdPartyName();
				
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
			
				// @todo Check: extend HttpRequestPayload instead?
				$oPayload->read_status =  new stdClass();
				$oPayload->read_status->target_oql_users = $aExtTargetUsers;
				$oPayload->read_status->messages = $aMessages;
			
		}

		return $oPayload;
				
	}
	
	

}
