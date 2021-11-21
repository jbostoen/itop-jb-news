<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-11-04 15:45:48
 *
 */

	namespace jb_itop_extensions\NewsClient;

	// iTop classes
	use \DBObjectSearch;
	use \DBObjectSet;
	use \MetaModel;
	use \utils;
	
	// Common
	use \Exception;

	// Custom classes
	use \jb_itop_extensions\NewsClient\ProcessThirdPartyNews;

	/**
	 * Class NewsClient. An actual news client which retrieves messages from a third party (non Combodo) news source (person/organization).
	 */
	abstract class NewsClient {
		
		/**
		 * @var \String $sApiVersion API version
		 */
		private static $sApiVersion = '1.0';
		
		/**
		 * @var \String $sThirdPartyName Third party name of person/organization publishing news messages
		 */
		private static $sThirdPartyName = 'jeffreybostoen';
		
		/**
		 * Gets News URL
		 *
		 * @return \String
		 */
		protected static function GetApiVersion() {
			
			return self::$sApiVersion;
			
		}
		
		/**
		 * Gets News URL
		 *
		 * @return \String
		 *
		 * @throws \Exception
		 */
		protected static function GetNewsUrl() {
			
			$sUrl = utils::GetCurrentModuleSetting('url', null);
			
			if($sUrl === null) {
				throw Exception('News URL not defined');
			}
			
			return $sUrl;
			
		}
		
		/**
		 * Gets third party name
		 *
		 * @return \String
		 */
		protected static function GetThirdPartyName() {
			
			return self::$sThirdPartyName;
			
		}
		
		/**
		 * Returns hash of user
		 */
		protected static function GetUserHash() {
		
			$sUserId = UserRights::GetUserId();
			$sUserHash = hash('fnv1a64', $sUserId);
			
		}
		
		/**
		 * Returns hash of instance
		 */
		protected static function GetInstanceHash() {
		
			// Note: not retrieving DB UUID for now as it is not of any use for now.
			$sITopUUID = (string) trim(@file_get_contents(APPROOT . 'data/instance.txt'), "{} \n");

			// Prepare a unique hash to identify users and instances across all iTops in order to be able for them 
			// to tell which news they have already read.
			$sInstanceId = hash('fnv1a64', $sITopUUID);
			
		}
		
		/**
		 * Gets all the relevant messages for this instance
		 *
		 * @param \ProcessThirdPartyNews $oProcess Scheduled background process
		 *
		 * @return void
		 */
		public static function RetrieveFromRemoteServer(ProcessThirdPartyNews $oProcess) {
			
			$sNewsUrl = self::GetNewsUrl();
			$sThirdPartyName = self::GetThirdPartyName();
			$sApiVersion = self::GetApiVersion();
	
			$oNewsRoomProvider = self::GetInstanceHash();
		
			$sApp = defined('ITOP_APPLICATION') ? ITOP_APPLICATION : 'unknown';
			$sVersion = defined('ITOP_VERSION') ? ITOP_VERSION : 'unknown';
			
			// Request messages
			// -
			
				// All messages will be requested.
				// It may be necessary to retract/delete some messages at some point.
				$aPostRequestData = array(
					'operation' => 'get_messages_for_instance',
					'version' => $sApiVersion,
					'instance_hash' => self::GetInstanceHash(),
					'app_name' => $sApp,
					'app_version' => $sVersion
				);

				$cURLConnection = curl_init($sNewsUrl);
				curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $aPostRequestData);
				curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
				
				// Only here to test on local installations.
				// @todo Remove this!
				curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYHOST, false);

				$sApiResponse = curl_exec($cURLConnection);
				
				if(curl_errno($cURLConnection)) {
					
					$sErrorMessage = curl_error($cURLConnection);
					
					$oProcess->Trace('. Error: cURL connection to '.$sNewsUrl.' failed: '.$sErrorMessage);
					
					// Abort
					return;
					
				}

				curl_close($cURLConnection);

				// Assume these messages are in the correct format.
				// If the format has changed in a backwards not compatible way, the API should simply not return any more messages
				// (except for one to recommend to upgrade the extension)
				$aMessages = json_decode($sApiResponse, true);
				
				// Get messages currently in database
				$oFilterMessages = new DBObjectSearch('ThirdPartyNewsroomMessage');
				$oFilterMessages->AddCondition('thirdparty_name', self::GetThirdPartyName(), '=');
				$oSetMessages = new DBObjectSet($oFilterMessages);
				
				$aKnownMessageIds = [];
				while($oMessage = $oSetMessages->Fetch()) {
					$aKnownMessageIds[] = $oMessage->Get('thirdparty_message_id');
				}
				
				$aRetrievedMessageIds = [];
				foreach($aMessages as $aMessage) {
					
					if(in_array($aMessage['thirdparty_message_id'], $aKnownMessageIds) == false) {
						
						// Enrich
						$oMessage = MetaModel::NewObject('ThirdPartyNewsroomMessage', [
							'thirdparty_name' => self::GetThirdPartyName(),
							'thirdparty_message_id' => $aMessage['thirdparty_message_id'],
							'title' => $aMessage['title'],
							'start_date' => $aMessage['start_date'],
							'end_date' => $aMessage['end_date'],
							'priority' => $aMessage['priority'],
							'icon' => $aMessage['icon']
						]);
						$oMessage->AllowWrite(true);
						$iInstanceMsgId = $oMessage->DBInsert();
						
						foreach($aMessage['translations_list'] as $aTranslation) {

							$oTranslation = MetaModel::NewObject('ThirdPartyNewsroomMessageTranslation', [
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
								
								foreach($aMessage as $sAttCode => $sValue) {
									
									switch($sAttCode) {
										
										case 'translations_list':
											
											// Get translations currently in database
											$oFilterTranslations = new DBObjectSearch('ThirdPartyNewsroomMessageTranslation');
											$oFilterTranslations->AddCondition('message_id', $oMessage->GetKey(), '=');
											$oSetTranslations = new DBObjectSet($oFilterTranslations);
											
											foreach($aMessage['translations_list'] as $aTranslation) {
												
												$oSetTranslations->Rewind();
												
												while($oTranslation = $oSetTranslations->Fetch()) {
													
													if($oTranslation->Get('language') == $aTranslation['language']) {
														
														foreach($aTranslation as $sAttCode => $sValue) {
															
															$oTranslation->Set($sAttCode, $sValue);
															
														}
														
														$oTranslation->AllowWrite(true);
														$oTranslation->DBUpdate();
														continue; // Continue translations
												
													}
												
												}
												
												// Translation was added later
												$oTranslation = MetaModel::NewObject('ThirdPartyNewsroomMessageTranslation', [
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
										
										default:
											$oMessage->Set($sAttCode, $sValue);
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
		
		/**
		 * Send the info back to the news server.
		 *
		 * @param \ProcessThirdPartyNews $oProcess Scheduled background process
		 *
		 * @return void
		 *
		 * @details This could be used to post statistics to the server.
		 */
		public static function PostToRemoteServer(ProcessThirdPartyNews $oProcess) {
			
			// @todo Check whether this can be grouped without sending too much data in one call
			return;
			
		}
		
	}
