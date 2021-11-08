<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-11-04 15:45:48
 *
 * Definition of NewsRoomProvider.
 */

	namespace jb_itop_extensions\NewsClient;

	// jb-framework;
	use \jb_itop_extensions\components\ScheduledProcess;

	// iTop classes
	use \DBObjectSearch;
	use \DBObjectSet;
	use \MetaModel;

	/**
	 * Class NewsClient
	 */
	abstract class NewsClient {
		
		/**
		 * @var \String $sApiVersion API version
		 */
		private static $sApiVersion = '1.0';
		
		/**
		 * @var \String $sNewsUrl News URL
		 */
		private static $sNewsUrl = 'https://news.jeffreybostoen.be';
		
		/**
		 * @var \String $sThirdPartyName Third party name
		 */
		private static $sThirdPartyName = 'jeffreybostoen';
		
		/**
		 * Gets News URL
		 *
		 * @return string
		 */
		protected static function GetApiVersion() {
			
			return self::$sApiVersion;
			
		}
		
		/**
		 * Gets News URL
		 *
		 * @return string
		 */
		protected static function GetNewsUrl() {
			
			return self::$sNewsUrl;
			
		}
		
		/**
		 * Gets third party name
		 *
		 * @return string
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
		 * @var \jb_itop_extensions\components\ScheduledProcess $oProcess Scheduled background process
		 *
		 * @return void
		 */
		public static function GetMessages(ScheduledProcess $oProcess) {
			
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
					'instance_hash' => $sHash,
					'app_name' => $sApp,
					'app_version' => $sVersion
				);

				$cURLConnection = curl_init($sNewsUrl);
				curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $aPostRequestData);
				curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

				$sApiResponse = curl_exec($cURLConnection);
				
				if(curl_errno($cURLConnection)) {
					
					$sErrorMessage = curl_error($cURLConnection);
					
					$oProcess->Trace('. Error: cURL connection to '.$sNewsUrl.' failed');
					
					// Abort
					return;
					
				}

				curl_close($cURLConnection);

				// Assume these messages are in the correct format.
				// If the format has changed in a backwards not compatible way, the API should simply not return any more messages
				// (except for one to recommend to upgrade the extension)
				$aMessages - json_decode($sApiResponse);
				
				// Get messages currently in database
				$oFilterMessages = new DBObjectSearch('ThirdPartyNewsroomMessage');
				$oFilterMessages->AddCondition('thirdparty_name', self::GetThirdPartyName(), '=');
				$oSetMessages = new DBObjectSet($oFilterMessages);
				
				$aKnownMessageIds = [];
				while($oMessage = $oSetMessages->Fetch()) {
					$aKnownMessageIds[] = $oMessage->GetKey();
				}
				
				$aRetrievedMessageIds = [];
				foreach($aMessages as $aMessage) {
					
					if(in_array($aMessage['id'], $aKnownMessageIds) == false) {
						
						// Enrich
						$oMessage = MetaModel::NewObject('ThirdPartyNewsroomMessage', [
							'thirdparty_name' => self::GetThirdPartyName,
							'thirdparty_message_id' => $aMessage['id'],
							'start_date' => $aMessage['start_date'],
							'end_date' => $aMessage['end_date'],
							'priority' => $aMessage['priority'],
							'image' => $aMessage['image']
						]);
						$oMessage->AllowWrite(true);
						$oMessage->DBInsert();
						
					}
					
					$aRetrievedMessageIds[] = $aMessage['key'];
					
				}
				
				// Check whether message has been pulled
				$oSetMessages->Rewind();
				while($oMessage = $oSetMessages->Fetch()) {
					
					if(in_array($oMessage->GetKey(), $aRetrievedMessageIds) == false) {
						$oMessage->DBDelete();
					}
					
				}
				
				
		}
		
		/**
		 * Send the info back to the news server
		 *
		 * @var \jb_itop_extensions\components\ScheduledProcess $oProcess Scheduled background process
		 *
		 * @return void
		 */
		public static function PostReadMessageStatus(ScheduledProcess $oProcess) {
			
			// @todo Check whether this can be grouped without sending too much data in one call
			
		}
		
		/**
		 * Posts diagnostic info to server
		 *
		 * @var \jb_itop_extensions\components\ScheduledProcess $oProcess Scheduled background process
		 *
		 * @return void
		 */		
		public static function PostDiagnosticInfo(ScheduledProcess $oProcess) {
			
			// @todo This is completely unfinished, just contains the basics
			
			require_once(APPROOT.'setup/runtimeenv.class.inc.php');
			$sCurrEnv = utils::GetCurrentEnvironment();
			$oRuntimeEnv = new RunTimeEnvironment($sCurrEnv);
			$aSearchDirs = array(APPROOT.$sDataModelSourceDir);
			
			if(file_exists(APPROOT.'extensions')) {
				$aSearchDirs[] = APPROOT.'extensions';
			}
			
			$sExtraDir = APPROOT.'data/'.$sCurrEnv.'-modules/';
			if (file_exists($sExtraDir)) {
				$aSearchDirs[] = $sExtraDir;
			}
			
			$aAvailableModules = $oRuntimeEnv->AnalyzeInstallation(MetaModel::GetConfig(), $aSearchDirs);
			foreach($aAvailableModules as $sModuleId => $aModuleData) {
				if ($sModuleId == '_Root_') continue;
				if ($aModuleData['version_db'] == '') continue;
				$oPage->add('InstalledModule/'.$sModuleId.': '.$aModuleData['version_db']."\n");
			}
			
			
		}
		
	}
