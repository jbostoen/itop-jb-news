<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.241010
 *
 */

	namespace jb_itop_extensions\NewsProvider;

	// iTop classes
	use \DBObjectSearch;
	use \DBObjectSet;
	use \MetaModel;
	use \ormDocument;
	use \UserRights;
	use \utils;
	
	// Common
	use \Exception;

	// Custom classes
	use \jb_itop_extensions\NewsProvider\ProcessThirdPartyNews;
	
	/**
	 * Interface iNewsSource. Interface to implement news sources.
	 */
	interface iNewsSource {
		
		/**
		 * Returns name of third party news source. This is used as a unique identifier, so do not use an existing one. It should remain consistent.
		 *
		 * @return \String
		 */
		public static function GetThirdPartyName();
		
		/**
		 * Returns additional data to send to news source. For instance, you can specify an API version here.
		 *
		 * @details Mind that by default certain parameters are already included in the HTTP request to the news source.
		 * @see NewsClient::GetPayload())
		 *
		 * @return \Array
		 */
		public static function GetPayload($sOperation);
		
		/**
		 * Returns URL of news source
		 */
		public static function GetUrl();
		
		/**
		 * Returns the base64 encoded public key for a Sodium implementation (crypto box). 
		 * A news source should share this, so the public key can be used to seal data which can then only be read by the news server.
		 *
		 * @return \Boolean
		 */
		public static function GetPublicKeySodiumCryptoBox();
		
		/**
		 * Returns the base64 encoded public key for a Sodium implementation (crypto sign). 
		 * A news source should share this, so the public key can be used to verify the news message contents of the source.
		 *
		 * @return \Boolean
		 */
		public static function GetPublicKeySodiumCryptoSign();
		
	}
	
	/**
	 * Class NewsClient. An actual news client which retrieves messages from a third party (non Combodo) news source (person/organization).
	 */
	abstract class NewsClient {
		
		/** @var \ProcessThirdPartyNews Scheduled background process (used for tracing only). */
		public static $oBackgroundProcess = null;
		
		/** @var \Array $aCachedPayloads used to cache some payloads which are the same for multiple news sources. */
		public static $aCachedPayloads = [];
		
		/**
		 * Returns the timestamp on which the news messages were last retrieved successfully from a particular news source.
		 *
		 * @return \DBObjectSet Object set of key values
		 */
		public static function GetLastRetrieved() {
			
			$oFilter = DBObjectSearch::FromOQL('SELECT KeyValueStore WHERE namespace = :namespace', [
				'namespace' => 'news'
			]);
			$oSet = new DBObjectSet($oFilter);
			$oKeyValue = $oSet->Fetch();
			
			return $oSet;
			
		}
		
		
		/**
		 * Sets the timestamp on which the news messages were last retrieved successfully for a particular news source.
		 *
		 * @param \String $sNewsSource Name of the news source.
		 *
		 * @return void
		 */
		public static function SetLastRetrieved($sNewsSource) {
			
			$sKeyName = 'news_'. preg_replace('/[^a-zA-Z0-9]+/', '', $sNewsSource);
			
			$oFilter = DBObjectSearch::FromOQL('SELECT KeyValueStore WHERE key_name = :key_name AND namespace = :namespace', [
				'namespace' => 'news',
				'key_name' => $sKeyName
			]);
			$oSet = new DBObjectSet($oFilter);
			$oKeyValue = $oSet->Fetch();
			
			if($oKeyValue !== null) {
				
				$oKeyValue->Set('value', date('Y-m-d H:i:s'));
				$oKeyValue->DBUpdate();
				
			}
			else {
				
				$oKeyValue = MetaModel::NewObject('KeyValueStore', [
					'namespace' => 'news',
					'key_name' => $sKeyName,
					'value' => date('Y-m-d H:i:s')
				]);
				$oKeyValue->DBInsert();
				
			}
			
		}
		
		
		
		
		/**
		 * Returns hash of user.
		 */
		public static function GetUserHash() {
		
			$sUserId = UserRights::GetUserId();
			$sUserHash = hash('fnv1a64', $sUserId);
			return $sUserHash;
			
		}
			
		/**
		 * Returns hash of instance.
		 */
		public static function GetInstanceHash() {
		
			// Note: not retrieving DB UUID for now as it is not of any use for now.
			$sITopUUID = (string) trim(@file_get_contents(APPROOT . 'data/instance.txt'), "{} \n");

			// Prepare a unique hash to identify users and instances across all iTops in order to be able for them 
			// to tell which news they have already read.
			$sInstanceId = hash('sha256', $sITopUUID);
			
			return $sInstanceId;
			
		}
		
		/**
		 * Returns hash of instance
		 */
		public static function GetInstanceHash2() {
		
			// Note: not retrieving DB UUID for now as it is not of any use for now.
			$sITopUUID = (string) trim(@file_get_contents(APPROOT . 'data/instance.txt'), "{} \n");

			// Prepare a unique hash to identify users and instances across all iTops in order to be able for them 
			// to tell which news they have already read.
			$sInstanceId = hash('fnv1a64', $sITopUUID);
			
			return $sInstanceId;
		
		
		}
		
		/**
		 * Returns UID of database
		 */
		public static function GetDatabaseUID() {
			
			$oFilter = DBObjectSearch::FromOQL('SELECT DBProperty WHERE name = "database_uuid"');
			$oSet = new DBObjectSet($oFilter);
			$oDBProperty = $oSet->Fetch();
			
			if($oDBProperty !== null) {
				
				return $oDBProperty->Get('value');
				
			}
			
			return '';
			
		}
		
		/**
		 * Gets all the relevant messages for this instance.
		 *
		 * @return void
		 */
		public static function RetrieveFromRemoteServer() {
			
			$sOperation = 'get_messages_for_instance';
			
			static::Trace('. Retrieve messages from remote news sources...');
			
			$sEncryptionLib = static::GetEncryptionLibrary();
			
			// Build list of news sources
			// -
			
				$aSources = static::GetSources();
				
			// Request messages from each news source
			// -
			
			
				foreach($aSources as $sSourceClass) {
					
					$sApiResponse = static::DoPost($sSourceClass, $sOperation);
					
					static::ProcessRetrievedMessages($sApiResponse, $sSourceClass);
					
				}
				
		}
		
		/**
		 * Process retrieved messages.
		 *
		 * @param \String $sApiResponse API response from the news server.
		 * @param \String $sSourceClass Name of the news source class.
		 *
		 * @return void
		 *
		 */
		public static function ProcessRetrievedMessages($sApiResponse, $sSourceClass) {
	
			$sThirdPartyName = $sSourceClass::GetThirdPartyName();
	
			// Assume these messages are in the correct format.
			// If the format has changed in a backwards not compatible way, the API should simply not return any more messages
			// (except for one to recommend to upgrade the extension)
			$aData = json_decode($sApiResponse, true);
			
			// Upon getting invalid data: abort
			if($aData === null) {
				static::Trace('. Invalid data received:');
				static::Trace(str_repeat('*', 25));
				static::Trace($sApiResponse);
				static::Trace(str_repeat('*', 25));
				return;
			}
								
			static::Trace('. Response: '.PHP_EOL.json_encode($aData, JSON_PRETTY_PRINT));
			
			$sEncryptionLib = static::GetEncryptionLibrary();
			
			// Check if modern implementation is in place
			if(array_key_exists('messages', $aData) == true) {
				
				if(array_key_exists('encryption_library', $aData) == true && array_key_exists('signature', $aData) == true) {

					// Check if the server responded in the proper way (e.g. client indicates it supports and expects Sodium: server should return data that can be verified with Sodium)
					// It could also mean that the extension (or NewsSource class) is out of date
					if($sEncryptionLib != $aData['encryption_library']) {
						
						static::Trace('. Requested encryption library "'.$sEncryptionLib.'", but the response does not match the requested library.');
						return;
				
					}
					else {
						
						// Implement supported libraries. For now, only Sodium
						if($sEncryptionLib == 'Sodium') {
						
							$aMessages = $aData['messages'];
							$sSignature = sodium_base642bin($aData['signature'], SODIUM_BASE64_VARIANT_URLSAFE);
							$sPublicKey = sodium_base642bin($sSourceClass::GetPublicKeySodiumCryptoSign(), SODIUM_BASE64_VARIANT_URLSAFE);
								
							// Verify using public key
							if(sodium_crypto_sign_verify_detached($sSignature, json_encode($aMessages), $sPublicKey)) {
								
								// Verified
								static::Trace('. Signature is valid.');
								
							} 
							else {
								
								static::Trace('. Unable to verify the signature using the public key for '.$sSourceClass);
								return;
								
							}   
						
							
						}
						else {
							
							static::Trace('. Unexpected path: suddenly using an unsupported encryption library.');
							
						}
						
					}
					
				}
				else {
				
					// It seems required keys (encryption_library, signature) were missing in the response
					// It could also mean that the extension (or NewsSource class) is out of date
					static::Trace('. Invalid response - encryption_library and signature are missing.');
					static::Trace(str_repeat('*', 25));
					static::Trace($sApiResponse);
					static::Trace(str_repeat('*', 25));
					return;
					
				}
				
			}
			elseif($sEncryptionLib != 'none' && array_key_exists('messages', $aData) == false) {
				
				static::Trace('. Invalid response - messages is missing while a signed response is expected.');
				return;
				
			}
			elseif($sEncryptionLib == 'none') {
				
				// Legacy implementation
				$aMessages = $aData;
				
			}
			
			// Only return data for news messages from this source.
			$sThirdPartyName = $sSourceClass::GetThirdPartyName();
			
			// Get messages currently in database for this third party source
			$oFilterMessages = new DBObjectSearch('ThirdPartyNewsRoomMessage');
			$oFilterMessages->AddCondition('thirdparty_name', $sThirdPartyName, '=');
			$oSetMessages = new DBObjectSet($oFilterMessages);
			
			$aKnownMessageIds = [];
			while($oMessage = $oSetMessages->Fetch()) {
				$aKnownMessageIds[] = $oMessage->Get('thirdparty_message_id');
			}
			
			$aRetrievedMessageIds = [];
			foreach($aMessages as $aMessage) {
				
				$aIcon = $aMessage['icon'];
				
				/** @var \ormDocument|null $oDoc Document (image) */
				$oDoc = null;
				if($aIcon['data'] != '' && $aIcon['mimetype'] != '' && $aIcon['filename'] != '') {
					$oDoc = new ormDocument(base64_decode($aIcon['data']), $aIcon['mimetype'], $aIcon['filename']);
				}
				
				// Ensure backward compatibility for client API 1.1.0 getting response from server API 1.0
				// Note: in real world cases, this shouldn't be a problem; as the server should always take care of this and even prefer to NOT return messages rather than causing issues.
				if(array_key_exists('target_profiles', $aMessage) == true) {
					unset($aMessage['target_profiles']);
					$aMessage['oql'] = 'SELECT User AS u JOIN URP_UserProfile AS up ON up.userid = u.id JOIN Person AS p ON u.contactid = p.id WHERE up.profileid_friendlyname = "Administrator" AND p.status = "active" AND u.status = "enabled"'; // Assume only administrators can see this
				}
				
				if(in_array($aMessage['thirdparty_message_id'], $aKnownMessageIds) == false) {
					
					// Enrich
					$oMessage = MetaModel::NewObject('ThirdPartyNewsRoomMessage', [
						'thirdparty_name' => $sThirdPartyName,
						'thirdparty_message_id' => $aMessage['thirdparty_message_id'],
						'title' => $aMessage['title'],
						'start_date' => $aMessage['start_date'],
						'end_date' => $aMessage['end_date'] ?? '',
						'priority' => $aMessage['priority'],
						'manually_created' => 'no',
						
						// Calls to a server which has not implemented API version 1.1.0 will not return anything.
						'oql' => $aMessage['oql'] ?? 'SELECT User',
					]);
					
					if($oDoc !== null) {
						$oMessage->Set('icon', $oDoc);
					}
					
					$oMessage->AllowWrite(true);
					$iInstanceMsgId = $oMessage->DBInsert();
					
					foreach($aMessage['translations_list'] as $aTranslation) {

						$oTranslation = MetaModel::NewObject('ThirdPartyNewsRoomMessageTranslation', [
							'message_id' => $iInstanceMsgId, // Remap
							'language' => $aTranslation['language'],
							'title' => $aTranslation['title'],
							'text' => $aTranslation['text'],
							'url' => $aTranslation['url']
						]);
						$oTranslation->AllowWrite(true);
						$oTranslation->DBInsert();
					
					}
					
				}
				else {
					
					$oSetMessages->Rewind();

					/** @var \ThirdPartyNewsRoomMessage $oMessage Newsroom message. */
					while($oMessage = $oSetMessages->Fetch()) {
						
						if($oMessage->Get('thirdparty_message_id') == $aMessage['thirdparty_message_id']) {
							
							// Do not intervene if the message on the current instance was manually created.
							if($oMessage->Get('manually_created') == 'yes') {
								continue;
							}
							
							$iInstanceMsgId = $oMessage->GetKey();
							
							foreach($aMessage as $sAttCode => $sValue) {
								
								switch($sAttCode) {
									
									case 'translations_list':
										
										// Get translations currently in database
										$oFilterTranslations = new DBObjectSearch('ThirdPartyNewsRoomMessageTranslation');
										$oFilterTranslations->AddCondition('message_id', $oMessage->GetKey(), '=');
										$oSetTranslations = new DBObjectSet($oFilterTranslations);
										
										foreach($aMessage['translations_list'] as $aTranslation) {
											
											// Looping through this set a couple of times
											$oSetTranslations->Rewind();
											
											/** @var \ThirdPartyNewsRoomMessageTranslation $oTranslation A translation for an iTop news message. */
											while($oTranslation = $oSetTranslations->Fetch()) {
												
												if($oTranslation->Get('language') == $aTranslation['language']) {
													
													// message_id and language won't change.
													foreach(['text', 'title', 'url'] as $sAttCode) {
														
														$oTranslation->Set($sAttCode, $aTranslation[$sAttCode]);
														
													}
													
													$oTranslation->AllowWrite(true);
													$oTranslation->DBUpdate();
													continue 2; // Continue processing translations_list since this one has been updated (it existed)
											
												}
											
											}
											
											// Translation is new
											$oTranslation = MetaModel::NewObject('ThirdPartyNewsRoomMessageTranslation', [
												'message_id' => $iInstanceMsgId, // Remap
												'language' => $aTranslation['language'],
												'title' => $aTranslation['title'],
												'text' => $aTranslation['text'],
												'url' => $aTranslation['url']
											]);
											$oTranslation->AllowWrite(true);
											$oTranslation->DBInsert();
										
											
										}
										
										break;
									
									case 'icon':
									
										// @todo Check if 'icon' can be null
										if($oDoc !== null) {
											$oMessage->Set('icon', $oDoc);
										}
										break;
										
									default:
										$oMessage->Set($sAttCode, $sValue ?? '');
										break;
								
								}
								
							}
							
							$oMessage->AllowWrite(true);
							$oMessage->DBUpdate();
							
						}
						
					}
					
				}
				
				$aRetrievedMessageIds[] = $aMessage['thirdparty_message_id'];
				
			}
			
			// Check whether message has been pulled
			$oSetMessages->Rewind();
			while($oMessage = $oSetMessages->Fetch()) {
				
				// Do not intervene if the message on the current instance was manually created.
				if($oMessage->Get('manually_created') == 'yes') {
					continue;
				}
				
				if(in_array($oMessage->Get('thirdparty_message_id'), $aRetrievedMessageIds) == false) {
					$oMessage->DBDelete();
				}
				
			}
			
			// Mark as properly processed
			static::SetLastRetrieved($sThirdPartyName);
					
		}
		
		/**
		 * Send the info back to the news server, such as statistics about (un)read messages.
		 *
		 * @return void
		 *
		 * @details This could be used to post statistics to the server.
		 */
		public static function PostToRemoteServer() {
			
			$sOperation = 'report_read_statistics';
			
			static::Trace('. Send statistical (anonymous) data to remote news sources...');
			
			// Other hooks may have ran already.
			// Do not leak sensitive data, OQL queries may contain names etc.
				
			// Post statistics on messages
			// -
			
				$aSources = static::GetSources();
				
				foreach($aSources as $sSourceClass) {
					
					// Not interested in the response of this call:
					static::DoPost($sSourceClass, $sOperation);
		
				}
				
			
		}
			
		/**
		 * Get preferred encryption library
		 *
		 * @return \String Sodium, none
		 */
		public static function GetEncryptionLibrary() {
			
			// Start from what's configured
			$sEncryptionLib = MetaModel::GetConfig()->GetEncryptionLibrary();
			
			$aSupportedLibsByEndPoint = ['Sodium', 'none'];
			
			// Reset variable if encryption from config file is not supported by this extension
			if(in_array($sEncryptionLib, $aSupportedLibsByEndPoint) == false) {
				$sEncryptionLib = 'none';
			}

			// This extension currently only supports 'Sodium' or 'none'
			if($sEncryptionLib != 'Sodium') {
			
				$bFunctionExists = function_exists('sodium_crypto_box_keypair');
				
				// Preference 1: Sodium
				if($bFunctionExists == true && in_array('Sodium', $aSupportedLibsByEndPoint) == true) {
					$sEncryptionLib = 'Sodium';
				}
				// Worst case: no encryption
				else {
					
					$sEncryptionLib = 'none';
				}
			
			}
			
			return $sEncryptionLib;
			
		}
		
		/**
		 * Returns class names of active news sources.
		 *
		 * @return \String[]
		 */
		public static function GetSources() {


			$aSources = [];
			$aDisabledSources = MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'debug_disable_sources', []);
				
			foreach(get_declared_classes() as $sClassName) {
				
				$aImplementations = class_implements($sClassName);
				if(in_array('jb_itop_extensions\NewsProvider\iNewsSource', $aImplementations) == true || in_array('iNewsSource', class_implements($sClassName)) == true) {
					
					// Skip source if temporarily disabled (perhaps advised at some point for some reason, without needing to disable or uninstall the extension completely)
					$sThirdPartyName = $sClassName::GetThirdPartyName();
					if(in_array($sThirdPartyName, $aDisabledSources) == true) {
						static::Trace('. Source '.$sThirdPartyName.' is disabled.');
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
		 * @param \String $sSourceClass Name of news source class.
		 * @param \String $sOperation Operation, e.g.: get_messages_for_instance, report_read_statistics.
		 *
		 * @return \Array Key/value
		 *
		 * @details Mind that this is executed over and over for each news source.
		 */
		public static function GetPayload($sSourceClass, $sOperation) {
			
			$sNewsUrl = $sSourceClass::GetUrl();
			
			$sApp = defined('ITOP_APPLICATION') ? ITOP_APPLICATION : 'unknown';
			$sVersion = defined('ITOP_VERSION') ? ITOP_VERSION : 'unknown';
			
			$sInstanceHash = static::GetInstanceHash();
			$sInstanceHash2 = static::GetInstanceHash2();
			$sDBUid = static::GetDatabaseUID();
			
			$sEncryptionLib = static::GetEncryptionLibrary();
			
			$aPayload = [
				'operation' => $sOperation,
				'instance_hash' => $sInstanceHash,
				'instance_hash2' => $sInstanceHash2,
				'db_uid' => $sDBUid,
				'env' =>  utils::GetCurrentEnvironment(),
				'app_name' => $sApp,
				'app_version' => $sVersion,
				'encryption_library' => $sEncryptionLib,
				'api_version' => NewsRoomHelper::DEFAULT_API_VERSION
			];
			
			if(strpos($sNewsUrl, '?') !== false) {
				
				// To understand the part below:
				// Mind that to make things look more pretty, the URL for a news source could point to a generic domain: 'itop-news.domain.org'.
				// This could be an index.php file which simply calls an iTop instance itself.
				// The index.php script (some sort of proxy) itself could act as a client to an iTop installation with the server component in this extension set to enabled.
				// It could make a call to: https://127.0.0.1:8182/iTop-clients/web/pages/exec.php?&exec_module=jb-news&exec_page=index.php&exec_env=production-news&operation=get_messages_for_instance&version=1.0 
				// and it would also need the originally appended parameters sent to 'itop-news.domain.org'.
				$sParameters = explode('?', $sNewsUrl)[1];
				parse_str($sParameters, $aParameters);
				$aPayload = array_merge($aPayload, $aParameters);
				
			}
			
			 
			if($sOperation == 'report_read_statistics') {
				
				// - Get generic info.
				
					if(isset(static::$aCachedPayloads['report_read_statistics']) == true) {
						
						/** @var \Integer[] $aExtTargetUsers Array to store user IDs of users for who the news extension is enabled. */
						$aExtTargetUsers = static::$aCachedPayloads['report_read_statistics']['target_users'];
						
						/** @var \DBObjectSet[] $oSetStatuses Object set of ThirdPartyMessageReadStatus. */
						$oSetStatuses = static::$aCachedPayloads['report_read_statistics']['read_states'];
						
					}
					else {
						
						// - Build list of target users (news extension)
							
							$sOQL = MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'oql_target_users', 'SELECT User');
							$oFilterUsers = DBObjectSearch::FromOQL($sOQL);
							$oSetUsers = new DBObjectSet($oFilterUsers);
							
							$aExtTargetUsers = [];
							
							while($oUser = $oSetUsers->Fetch()) {
								
								// By default, there is no 'last login' data unfortunately, unless explicitly stated.
								$aExtTargetUsers[] = $oUser->GetKey();
								
							}
							
							static::$aCachedPayloads['report_read_statistics']['target_users'] = $aExtTargetUsers;
							
						// - Get set of ThirdPartyMessageReadStatus (will be used to loop over each time)
							
							$oFilterStatuses = DBObjectSearch::FromOQL('SELECT ThirdPartyMessageReadStatus');
							$oFilterStatuses->AllowAllData();
							$oSetStatuses = new DBObjectSet($oFilterStatuses);
							static::$aCachedPayloads['report_read_statistics']['read_states'] = $oSetStatuses;
							
					}
					
				// - Get ThirdPartyNewsRoomMessage of this source and obtain specific info.
				
				
					$sThirdPartyName = $sSourceClass::GetThirdPartyName();
					
					$oFilterMessages = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsRoomMessage WHERE thirdparty_name = :thirdparty_name', [
						'thirdparty_name' => $sThirdPartyName
					]);
					$oFilterMessages->AllowAllData();
					$oSetMessages = new DBObjectSet($oFilterMessages);
					
					
					$aMessages = [];
					
					while($oMessage = $oSetMessages->Fetch()) {
						
						// Determine users targeted by the newsroom message (based on "oql" attribute, but might *also* be restricted because of the global "oql_target_users")
						
							$oFilterTargetUsers = DBObjectSearch::FromOQL($oMessage->Get('oql'));
							
							if($oFilterTargetUsers === null) {
								
								// Upon failure - likely when upgrading from an old version where "oql" is not supported, actually very few instances will be in this case.
								// Could also happen when an OQL query turns out to be invalid.
								$oAttDef = MetaModel::GetAttributeDef('ThirdPartyNewsRoomMessage', 'oql');
								$oFilterTargetUsers = DBObjectSearch::FromOQL($oAttDef->GetDefaultValue());
								
							}
							
							$oFilterTargetUsers->AllowAllData();
							
							$oSetUsers = new DBObjectSet($oFilterTargetUsers);
							
							$aTargetUsers = [];
							
							/** @var \User $oUser An iTop user */
							while($oUser = $oSetUsers->Fetch()) {
								
								$aTargetUsers[] = $oUser->GetKey();
								
							}
							
							$aMessages[(String)$oMessage->Get('thirdparty_message_id')] = [
								'target_users' => $aTargetUsers,
								'users' => [], // Each user who actually "read" the message
								'read_date' => [], // See users above - this is the read date for each user
							];
						
						// Report when messages were read (users stay anonymous, only IDs are shared)
						
						$oSetStatuses->Rewind();
						while($oStatus = $oSetStatuses->Fetch()) {
							
							if($oStatus->Get('message_id') == $oMessage->GetKey()) {
						
								$aMessages[(String)$oMessage->Get('thirdparty_message_id')]['users'][] = $oStatus->Get('user_id');
								$aMessages[(String)$oMessage->Get('thirdparty_message_id')]['read_date'][] = $oStatus->Get('read_date');
								
							}
						
						}
						
					}
					

				// - Add this info to the payload
				
					$aPayload['read_status'] = [
						'target_oql_users' => $aExtTargetUsers,
						'messages' => $aMessages
					];
				
			}
			
			
			// These are default parameters, which can be overridden.
			return array_merge($aPayload, $sSourceClass::GetPayload($sOperation));
					
		}
		
		/**
		 * Prepare payload. The payload gets JSON encoded; then base64 encoded. 
		 * If Sodium is available, it will get encrypted as well.
		 *
		 * @param \String $sSourceClass Source class.
		 * @param \Array $aPayload Payload to be prepared.
		 *
		 * @return \String Binary data
		 */
		public static function PreparePayload($sSourceClass, $aPayload) {
			
			$sPayload = json_encode($aPayload);
			
			if(static::GetEncryptionLibrary() == 'Sodium') {
				
				$sPublicKey = sodium_base642bin($sSourceClass::GetPublicKeySodiumCryptoBox(), SODIUM_BASE64_VARIANT_URLSAFE);
				$sBinData = sodium_base642bin(base64_encode($sPayload), SODIUM_BASE64_VARIANT_URLSAFE);
				
				// The payload becomes sealed.
				$sPayload = sodium_crypto_box_seal($sBinData, $sPublicKey);
			
			}
			
			return base64_encode($sPayload);
		
		}
		
		/**
		 * Do an HTTP POST request to an end point.
		 *
		 * @param \String $sSourceClass News source class.
		 * @param \String $sOperation Operation. Current operations: get_messages_for_instance, report_read_statistics.
		 *
		 * @return \String
		 *
		 * @throws \Exception
		 */
		public static function DoPost($sSourceClass, $sOperation) {
	
			// Unencrypted payload (easier for debugging)
			$aPayload = static::GetPayload($sSourceClass, $sOperation);
			$sNewsUrl = $sSourceClass::GetUrl();
					
			static::Trace('. Url: '.$sNewsUrl);
			static::Trace('. Data: '.json_encode($aPayload));
			
			// Encode and - if available - encrypt.
			$sPayload = static::PreparePayload($sSourceClass, $aPayload);
			
			$aPostData = [
				'operation' => $sOperation,
				'api_version' => NewsRoomHelper::DEFAULT_API_VERSION,
				'payload' => $sPayload
			];
			
			$cURLConnection = curl_init($sNewsUrl);
			curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $aPostData);
			curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
			
			// Only here to test on local installations. Not meant to be enforced!
			// curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYPEER, false);
			// curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYHOST, false);
			$sApiResponse = curl_exec($cURLConnection);
			
			if(curl_errno($cURLConnection)) {
				
				$sErrorMessage = curl_error($cURLConnection);
				static::Trace('. Error: cURL connection to '.$sNewsUrl.' failed: '.$sErrorMessage);
				
				// Abort. Otherwise messages might just get deleted while they shouldn't.
				return;
				
			}

			curl_close($cURLConnection);
			
			return $sApiResponse;

		}
		
		/**
		 * Set BackgroundProcess.
		 *
		 * @param \ProcessThirdPartyNews $oProcess Scheduled background process.
		 *
		 * @return void
		 */
		public static function SetBackgroundProcess(ProcessThirdPartyNews $oProcess) {
		
			static::$oBackgroundProcess = $oProcess;
			
		}
		
		/**
		 * Trace.
		 *
		 * @param \String $sTraceLog Line to add to trace log.
		 *
		 * @return void
		 */
		public static function Trace($sTraceLog) {
		
			if(static::$oBackgroundProcess !== null) {
				static::$oBackgroundProcess->Trace($sTraceLog);
			}
			
		}
		
	}
