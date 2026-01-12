<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260112
 */

namespace JeffreyBostoenExtensions\News\RemoteServers;

use JeffreyBostoenExtensions\News\{
	eDataApiVersion,
	Helper
};

use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	eOperation,
	eOperationMode,
	Helper as SCHelper,
};
use JeffreyBostoenExtensions\ServerCommunication\RemoteServers\Base as BaseRemoteServer;
use JeffreyBostoenExtensions\ServerCommunication\Client\Base as Client;

// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use ormDocument;

// iTop classes.
use ThirdPartyNewsMessage;
use User;

// Generic.
use Exception;
use stdClass;

/**
 * Class Base. A remote server.  
 * 
 * Note: Also the short name of this class must be unique!
 */
abstract class Base extends BaseRemoteServer {


	/**
	 * @inheritDoc
	 */
	public function SupportsApiVersion(eApiVersion $eApiVersion): bool {

		return ($eApiVersion === eApiVersion::v2_1_0);
		
	}


	/**
	 * @inheritDoc
	 */
	public function SupportsOperation(eOperation $eOperation): bool {

		switch($eOperation) {
			case eOperation::NewsGetMessagesForInstance:
			case eOperation::NewsTelemetry:
				return true;
			default:
				return false;
		}

	}

	
	/**
	 * @inheritDoc
	 */
	public function SupportsOperationMode(eOperationMode $eOperationMode): bool {

		switch($eOperationMode) {
			case eOperationMode::Cron:
			case eOperationMode::Mitm:
				return true;
			default:
					return false;
		}
		
	}


	/**
	 * @inheritDoc
	 */
	public function OnSendDataToRemoteServer(): void {

		$oRequest = $this->GetClient()->GetCurrentHttpRequest();
		$oRequest->news_api_version = eDataApiVersion::v1_0_0->value;
		$oRequest->news_extension_version = Helper::MODULE_VERSION;

		$eOperation = $this->GetClient()->GetCurrentOperation();

		if($eOperation == eOperation::NewsGetMessagesForInstance) {
			
			$this->SetHttpRequestInstanceInfo();

		}
		elseif($eOperation == eOperation::NewsTelemetry || $eOperation == eOperation::NewsReportReadStatistics) {

			$this->SetHttpRequestReportStatistics();

		}

	}
	

	/**
	 * @inheritDoc
	 */
	public function IsOperationReadyToExecute(): bool {
		
		/** @var Client $oClient */
		$oClient = $this->GetClient();
		$eOperation = $oClient->GetCurrentOperation();
		$sOperation = $eOperation->value;
		$sLastExecution = '1970-01-01';

		if($this->GetKeyValue($sOperation.'_last_execution') !== null) {
			$sLastExecution = $this->GetKeyValue($sOperation.'_last_execution')->Get('value');
		}

		// The job should run every X minutes.
		$iFrequencyMins = (int)MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'frequency', 60);

		// Add some extra leniency, as the cron job is preferred.
		// It should be within X, where X = (frequency + 15 mins)
		$iMinTime = strtotime('-'.$iFrequencyMins.' minutes -15 minutes');

		SCHelper::Trace('Source: %1$s - Last execution of %2$s: %3$s - Frequency (minutes): %4$s - Invoke if last requested before: %5$s',
			$this->GetThirdPartyName(),
			$sOperation,
			$sLastExecution,
			$iFrequencyMins,
			date('Y-m-d H:i:s', $iMinTime)
		);

		// Only keep if the last retrieval date is too long ago.
		if(strtotime($sLastExecution) < $iMinTime) {
			return true;
		}

		return false;

	}


	/**
	 * @inheritDoc
	 */
	public function OnReceiveDataFromExternalServer(): void {

		parent::OnReceiveDataFromExternalServer();

		$eOperation = $this->GetClient()->GetCurrentOperation();

		if($eOperation == eOperation::NewsGetMessagesForInstance) {
			
			$this->ProcessReceivedMessages();

		}

	}


	/**
	 * Processes the received messages.
	 *
	 * @return void
	 */
	public function ProcessReceivedMessages() : void {

		/** @var stdClass $oResponse */
		$oResponse = $this->GetClient()->GetCurrentHttpResponse();
	
		$sThirdPartyName = $this->GetThirdPartyName();

		// Assume these messages are in the correct format.
		// If the format has changed in a backwards incompatible way, the API should simply not return any messages.
		// (except perhaps for one to recommend to upgrade the extension)
			
		// - Check if a valid data structure is in place (API 1.1.0 specification).
		
			if(!property_exists($oResponse, 'messages')) {

				SCHelper::Trace('No messages found.');
				return;

			}

		/** @var Message[] $aMessages */
		$aMessages = $oResponse->messages;
		
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
					SCHelper::Trace('Skipping ThirdPartyNewsMessage object for message ID "%1$s" (manually created on this instance).', $oJsonMessage->thirdparty_message_id);
					unset($aMessages[$oMessage->Get('thirdparty_message_id')]);
					continue;
				}
				
				// - If the message is not in the retrieved messages (e.g. retracted), it should be deleted from the database as well.
				//   Note: This will also remove any statistics related to this message.
				if(in_array($oMessage->Get('thirdparty_message_id'), $aRetrievedMessageIds) == false) {
					$oMessage->DBDelete();
					continue;
				}

				$aMessages[$oMessage->Get('thirdparty_message_id')]->DBObject = $oMessage;
				
			}

		// - No need to continue further if there are no messages.
			
			if(count($aMessages) == 0) {
				return;
			}

		// - Loop through the messages received in the HTTP response to create ThirdPartyNewsMessage objects.

			/** @var stdClass $oJsonMessage */
			foreach($aMessages as $oJsonMessage) {
				
				// - For the ones without a DBObject, create a new one.

					if(!property_exists($oJsonMessage, 'DBObject')) {
						/** @var ThirdPartyNewsMessage $oMessage */
						SCHelper::Trace('Create new ThirdPartyNewsMessage object for message ID "%1$s".', $oJsonMessage->thirdparty_message_id);
						$oJsonMessage->DBObject = MetaModel::NewObject('ThirdPartyNewsMessage', []);
					}
					else {
						SCHelper::Trace('Found existing ThirdPartyNewsMessage object for message ID "%1$s".', $oJsonMessage->thirdparty_message_id);
					}
					
				// - Every message (HTTP response) has a ThirdPartyNewsMessage object associated with it now.

					/** @var ThirdPartyNewsMessage $oMessage */
					$oMessage = $oJsonMessage->DBObject;

				// - Copy the properties to the DBObject.
				
					// - Use the internal name of the remote server (as known to this instance).
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
						SCHelper::Trace('Failed to process translation for message ID "%1$s" and language "%2$s": %3$s', 
							$oJsonMessage->DBObject->GetKey(), 
							$oJsonTranslation->language, 
							$e->getMessage()
						);

					}
				
				}

			}
		
	}


	/**
	 * @inheritDoc
	 * 
	 * It will list:
	 * - The IDs of all the potential target users. (oql_target_users).
	 * - For each message:
	 *   - The potential target users.
	 *   - Per user:
	 *     - When the message was first displayed.
	 *     - When the message was last displayed.
	 *     - When the message was *explicitly* marked as read.
	 * 
	 * 
	 *   
	 */
	public function SetHttpRequestReportStatistics() : void {

		/** @var Client $oClient */
		$oClient = $this->GetClient();

		/** @var HttpRequest $oRequest */
		$oRequest = $oClient->GetCurrentHttpRequest();

		// - Determine which users can be targeted.

			$sOql = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'oql_target_users', 'SELECT User');

			// - Determine the IDs, only once per client instance.

				if($oClient->GetCachedValue('news_target_users') === null) {

					$oFilterUsers = DBObjectSearch::FromOQL_AllData($sOql);
					$oSetUsers = new DBObjectSet($oFilterUsers);
					
					$aExtTargetUsers = [];
					
					while($oUser = $oSetUsers->Fetch()) {
						
						// By default, there is no 'last login' data unfortunately, unless explicitly stated.
						$aExtTargetUsers[] = $oUser->GetKey();
						
					}

					$oClient->SetCachedValue('news_target_users', $aExtTargetUsers);

				}

			// - General config.

				$oRequest->config = new stdClass();
				$oRequest->config->target_users_ids = $oClient->GetCachedValue('news_target_users');
				$oRequest->config->target_users_oql = $sOql;

				$oRequest->config->trace_log = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'trace_log', false);
				$oRequest->config->ttl = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'ttl', 3600);
				$oRequest->config->frequency = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'frequency', 60);
				$oRequest->config->server = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'server', false);

				// - This may seem a bit ridiculous (and is, as accurate unit tests should catch this),
				//   but meant to spot any issues where enabled = false or client = false would be ignored for some reason and still ... report stats.
				$oRequest->config->enabled = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'enabled', false);
				$oRequest->config->client = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'client', false);


			
			// - Get the message status for each message seemingly originating from this remote server.

				// Note: It turns out that due to the way iTop handles Rewind(), it requeries the object anyway. 
				// So no performance penalty here for querying.
					
				$oFilterStatuses = DBObjectSearch::FromOQL_AllData('
					SELECT ThirdPartyMessageUserStatus AS us 
					JOIN ThirdPartyNewsMessage AS msg ON us.message_id = msg.id 
					WHERE 
						msg.thirdparty_name = :thirdparty_name
				');
				$oSetStatuses = new DBObjectSet($oFilterStatuses, [], [
					'thirdparty_name' => $this->GetThirdPartyName(),
				]);
				
			// - Get ThirdPartyNewsMessage objects belonging to the current remote server,
			//   and obtain specific info to report back to this source only.
			
				$sThirdPartyName = $this->GetThirdPartyName();
				
				$oFilterMessages = DBObjectSearch::FromOQL_AllData('
					SELECT ThirdPartyNewsMessage 
					WHERE 
						thirdparty_name = :thirdparty_name
				', [
					'thirdparty_name' => $sThirdPartyName
				]);
				$oSetMessages = new DBObjectSet($oFilterMessages);
		
				$aMessageData = [];
				
				while($oMessage = $oSetMessages->Fetch()) {
					
					// Determine users targeted specifically by the newsroom message.
					// ( Based on "oql" attribute, but might *also* be restricted because of the global "oql_target_users" setting.)
					
						try {
								
							$oFilterTargetUsers = DBObjectSearch::FromOQL_AllData($oMessage->Get('oql'));
							if($oFilterTargetUsers === null) {
								throw new Exception('Invalid target users OQL specified for the message?');
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
						
						/** @var User $oUser An iTop user */
						while($oUser = $oSetUsers->Fetch()) {
							
							$aTargetUsers[] = $oUser->GetKey();
							
						}
						
						$aMessageData[(String)$oMessage->Get('thirdparty_message_id')] = [
							'target_users' => $aTargetUsers,
							'users' => [], // Each user who actually marked the message as "read".
							'first_shown_date' => [], // See users above - This is the first shown date for each user.
							'last_shown_date' => [], // See users above - This is the last shown date for each user.
							'read_date' => [], // See users above - This is the read date for each user.
						];
					
					// Report when messages were read (users stay anonymous to the provider, only IDs are shared).
					
					while($oStatus = $oSetStatuses->Fetch()) {
						
						if($oStatus->Get('message_id') == $oMessage->GetKey()) {
					
							$aMessageData[(String)$oMessage->Get('thirdparty_message_id')]['users'][] = $oStatus->Get('user_id');

							foreach(['first_shown_date', 'last_shown_date', 'read_date'] as $sAttCode) {
								$aMessageData[(String)$oMessage->Get('thirdparty_message_id')][$sAttCode][] = $oStatus->Get($sAttCode);
							}
							
						}
					
					}
					
				}
				

			// - Add this info to the payload.
			
				$oRequest->messageData = $aMessageData;
			
				
	}

}
