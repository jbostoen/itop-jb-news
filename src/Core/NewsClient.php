<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220607
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
		 * Returns additional post parameters. For instance, you can specify an API version here.
		 *
		 * @details Mind that by default certain parameters are already included in the POST request to the news source.
		 * @see NewsClient::RetrieveFromRemoteServer())
		 *
		 * @return \Array
		 */
		public static function GetPostParameters();
		
		/**
		 * Returns URL of news source
		 */
		public static function GetUrl();
		
		/**
		 * Returns the base64 encoded public key for a Sodium implementation. 
		 * A news source should share this, so the public key can be used to verify the news message contents of the source.
		 *
		 * @return \Boolean
		 */
		public static function GetPublicKeySodium();
		
	}
	
	/**
	 * Class NewsClient. An actual news client which retrieves messages from a third party (non Combodo) news source (person/organization).
	 */
	abstract class NewsClient {
		
		/**
		 * Returns hash of user
		 */
		protected static function GetUserHash() {
		
			$sUserId = UserRights::GetUserId();
			$sUserHash = hash('fnv1a64', $sUserId);
			return $sUserHash;
			
		}
			
		/**
		 * Returns hash of instance
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
		 * Gets all the relevant messages for this instance
		 *
		 * @param \ProcessThirdPartyNews $oProcess Scheduled background process
		 *
		 * @return void
		 */
		public static function RetrieveFromRemoteServer(ProcessThirdPartyNews $oProcess) {
			
			$sApp = defined('ITOP_APPLICATION') ? ITOP_APPLICATION : 'unknown';
			$sVersion = defined('ITOP_VERSION') ? ITOP_VERSION : 'unknown';
			
			$sInstanceHash = static::GetInstanceHash();
			$sInstanceHash2 = static::GetInstanceHash2();
			$sDBUid = static::GetDatabaseUID();
			
			$sEncryptionLib = static::GetEncryptionLibrary();
			
			// Build list of news sources
			// -
			
				$aDisabledSources = MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'debug_disable_sources', []);
			
				$aSources = [];
				foreach(get_declared_classes() as $sClassName) {
					
					$aImplementations = class_implements($sClassName);
					if(in_array('jb_itop_extensions\NewsProvider\iNewsSource', $aImplementations) == true || in_array('iNewsSource', class_implements($sClassName)) == true) {
						
						// Skip source if temporarily disabled (perhaps advised at some point for some reason, without needing to disable or uninstall the extension completely)
						$sThirdPartyName = $sClassName::GetThirdPartyName();
						if(in_array($sThirdPartyName, $aDisabledSources) == true) {
							$oProcess->Trace('. Source '.$sThirdPartyName.' is disabled.');
							continue;
						}
						
						$aSources[] = $sClassName;
					}
					
				}
				
			// Request messages
			// -
			
			
				foreach($aSources as $sSourceClass) {
										
					$sNewsUrl = $sSourceClass::GetUrl();
					$sThirdPartyName = $sSourceClass::GetThirdPartyName();
					
					// All messages will be requested.
					// It may be necessary to retract/delete some messages at some point.
					// These are default parameters, which can be overridden.
					$aPostRequestData = [
						'operation' => 'get_messages_for_instance',
						'instance_hash' => $sInstanceHash,
						'instance_hash2' => $sInstanceHash2,
						'db_uid' => $sDBUid,
						'env' =>  utils::GetCurrentEnvironment(),
						'app_name' => $sApp,
						'app_version' => $sVersion,
						'encryption_library' => $sEncryptionLib,
						'api_version' => NewsRoomHelper::DEFAULT_API_VERSION
					];
					
					$aPostRequestData = array_merge($aPostRequestData, $sSourceClass::GetPostParameters());
					
					if(strpos($sNewsUrl, '?') !== false) {
						
						// To understand the part below:
						// Mind that to make things look more pretty, the URL for a news source could point to a generic domain: 'itop-news.domain.org'.
						// This could be an index.php file which simply calls an iTop instance itself, the index.php script (some sort of proxy) itself would act as a client to an iTop installation with the server in this extension enabled.
						// It could make a call to: https://127.0.0.1:8182/iTop-clients/web/pages/exec.php?&exec_module=jb-news&exec_page=index.php&exec_env=production-news&operation=get_messages_for_instance&version=1.0 
						// and it would also need the originally appended parameters sent to 'itop-news.domain.org'.
						$sParameters = explode('?', $sNewsUrl)[1];
						parse_str($sParameters, $aParameters);
						
						$aPostRequestData = array_merge($aPostRequestData, $aParameters);
						
						
					}
					
					$oProcess->Trace('. Url: '.$sNewsUrl);
					$oProcess->Trace('. Data: '.json_encode($aPostRequestData));

					$cURLConnection = curl_init($sNewsUrl);
					curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $aPostRequestData);
					curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
					
					// Only here to test on local installations. Not meant to be enforced!
					// curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYPEER, false);
					// curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYHOST, false);

					$sApiResponse = curl_exec($cURLConnection);
					
					if(curl_errno($cURLConnection)) {
						
						$sErrorMessage = curl_error($cURLConnection);
						
						$oProcess->Trace('. Error: cURL connection to '.$sNewsUrl.' failed: '.$sErrorMessage);
						
						// Abort. Otherwise messages might just get deleted while they shouldn't.
						return;
						
					}

					curl_close($cURLConnection);


					// Assume these messages are in the correct format.
					// If the format has changed in a backwards not compatible way, the API should simply not return any more messages
					// (except for one to recommend to upgrade the extension)
					$aData = json_decode($sApiResponse, true);
					
					// Upon getting invalid data: abort
					if($aData === null) {
						$oProcess->Trace('. Invalid data received:');
						$oProcess->Trace(str_repeat('*', 25));
						$oProcess->Trace($sApiResponse);
						$oProcess->Trace(str_repeat('*', 25));
						return;
					}
										
					$oProcess->Trace('. Response: '.PHP_EOL.json_encode($aData, JSON_PRETTY_PRINT));
					
					
					// Check if modern implementation is in place
					if(array_key_exists('messages', $aData) == true) {
						
						if(array_key_exists('encryption_library', $aData) == true && array_key_exists('signature', $aData) == true) {

							// Check if the server responded in the proper way (e.g. client indicates it supports and expects Sodium: server should return data that can be verified with Sodium)
							// It could also mean that the extension (or NewsSource class) is out of date
							if($sEncryptionLib != $aData['encryption_library']) {
								
								$oProcess->Trace('. Requested encryption library "'.$sEncryptionLib.'", but the response does not match the requested library.');
								return;
						
							}
							else {
								
								// Implement supported libraries. For now, only Sodium
								if($sEncryptionLib == 'Sodium') {
								
									$aMessages = $aData['messages'];
									$sSignature = sodium_base642bin($aData['signature'], SODIUM_BASE64_VARIANT_URLSAFE);
									$sKey = sodium_base642bin($sSourceClass::GetPublicKeySodium(), SODIUM_BASE64_VARIANT_URLSAFE);
										
									// Verify using public key
									if(sodium_crypto_sign_verify_detached($sSignature, json_encode($aMessages), $sKey)) {
										
										// Verified
										$oProcess->Trace('. Signature is valid.');
										
									} 
									else {
										
										$oProcess->Trace('. Unable to verify the signature using the public key for '.$sSourceClass);
										return;
										
									}   
								
									
								}
								else {
									
									$oProcess->Trace('. Unexpected path: suddenly using an unsupported encryption library.');
									
								}
								
							}
							
						}
						else {
						
							// It seems required keys (encryption_library, signature) were missing in the response
							// It could also mean that the extension (or NewsSource class) is out of date
							$oProcess->Trace('. Invalid response - encryption_library and signature are missing.');
							$oProcess->Trace(str_repeat('*', 25));
							$oProcess->Trace($sApiResponse);
							$oProcess->Trace(str_repeat('*', 25));
							return;
							
						}
						
					}
					elseif($sEncryptionLib != 'none' && array_key_exists('messages', $aData) == false) {
						
						$oProcess->Trace('. Invalid response - messages is missing while a signed response is expected.');
						return;
						
					}
					elseif($sEncryptionLib == 'none') {
						
						// Legacy implementation
						$aMessages = $aData;
						
					}
					
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
							$aMessage['oql'] = 'SELECT User AS u JOIN URP_UserProfile AS up ON up.userid = u.id WHERE up.profileid_friendlyname = "Administrator"'; // Assume only administrators can see this
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
							while($oMessage = $oSetMessages->Fetch()) {
								
								if($oMessage->Get('thirdparty_message_id') == $aMessage['thirdparty_message_id']) {
									
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
						
						if(in_array($oMessage->Get('thirdparty_message_id'), $aRetrievedMessageIds) == false) {
							$oMessage->DBDelete();
						}
						
					}
				
				}
				
		}
		
		/**
		 * Send the info back to the news server, such as statistics about (un)read messages.
		 *
		 * @param \ProcessThirdPartyNews $oProcess Scheduled background process
		 *
		 * @return void
		 *
		 * @details This could be used to post statistics to the server.
		 */
		public static function PostToRemoteServer(ProcessThirdPartyNews $oProcess) {
			
			// @todo Check whether this can be grouped without sending too much data in one call
			
			// Other hooks may have ran already.
			// Do not leak sensitive data, OQL queries may contain names etc.
			$sOQL = MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'oql_target_users', 'SELECT User');
			$oFilterUsers = DBObjectSearch::FromOQL($sOQL);
			$oSetUsers = new DBObjectSet($oFilterUsers);
			
			$aCurrentAudienceUserIds = [];
			
			while($oUser = $oSetUsers->Fetch()) {
				
				$aCurrentAudienceUserIds[] = $oUser->GetKey();
				
			}
			
			// Post statistics: total number of possible users who can still see a message
			// Number of users who have seen a specific message (mind that this could be larger than the number of possible users who can still see it!)
			// Differentiation needed?
			
			// @todo Determine "target audience": all possible users (as defined by the admin); or combined with the OQL on the message?
			
			// - Count how many users:
			//	(1) Have read the message and are still in the target group.
			//	(2) Have read the message and are no longer in the target group.
			//	(3) Are in the target group, but have not read the message yet.
			
			$oFilterMessages = DBObjectSearch::FromOQL('SELECT ThirdPartyNewsRoomMessage');
			$oFilterMessages->AllowAllData();
			$oSetMessages = new DBObjectSet($oFilterMessages);
			
			$oFilterLinks = DBObjectSearch::FromOQL('SELECT ThirdPartyMessageToUser');
			$oSetLinks = new DBObjectSet($oFilterLinks);
			
			$aMessagesData = [];
			
			while($oMessage = $oSetMessages->Fetch()) {
			
				$iTotalRead = 0;
				$iTotalUnread = 0;

				$fStillAllowed = 0;
				$fPreviouslyAllowed = 0;
				$fNotAllowed = 0;

				// Cross-reference
				$oSetLinks->Rewind();
				while($oLink = $oSetLinks->Fetch()) {
					
					// Process unique user/message records for this message.
					if($oLink->Get('message_id') == $oMessage->GetKey()) {
						
						$iTotalRead += ($oLink->Get('read_date') == '' ? 0 : 1);
						$iTotalUnread += ($oLink->Get('read_date') == '' ? 1 : 0);
						
					}
					
					// Is this user still allowed to see this message?
					
					
				}
				
				$aMessagesData[$oMessage->Get('thirdparty_name').'_'.$oMessage->Get('thirdparty_message_id')] = [
					'totalRead' => $iTotalRead,
					'totalUnread' => $iTotalUnread
				];
				
			}
			
			return;
			
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

			// This extension only supports  Sodium or none
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
		
		
		
	}
