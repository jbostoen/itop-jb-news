<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220323
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

	/**
	 * Class NewsServer. A news server which is capable to output all messages for an instance.
	 */
	abstract class NewsServer {
		
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
		 * Gets all the relevant messages for an instance
		 *
		 * @return \Array
		 */
		public static function GetMessagesForInstance() {
			
			$sAppName = utils::ReadParam('app_name', '');
			$sAppVersion = utils::ReadParam('app_name', '');
			$sInstanceId = utils::ReadParam('instance_hash', '');
			$sInstanceId2 = utils::ReadParam('instance_hash2', '');
			
			// Output all messages with their translations
			// Theoretically additional filtering could be applied to reduce JSON size;
			// for instance to only return messages if certain extensions are installed
			// Or logging could be added
			
			$oFilterMessages = new DBObjectSearch('ThirdPartyNewsRoomMessage');
			
			// Some publications might still be hidden (surprise announcement, promo, limited offer, ...)
			$sNow = date('Y-m-d H:i:s');
			$sOQL = 'SELECT ThirdPartyNewsRoomMessage WHERE start_date <= "'.$sNow.'" AND (ISNULL(end_date) OR end_date >= "'.$sNow.'")';
			$oSetMessages = new DBObjectSet(DBObjectSearch::FromOQL($sOQL));
			
			$aObjects = [];
			
			while($oMessage = $oSetMessages->Fetch()) {
				
				// Unfortunately in iTop 2.7 there's no native function to output all field info of an object
				
				$aTranslations = [];
				
				/** @var \ormLinkSet $oSetTranslations Translations of a message */
				$oSetTranslations = $oMessage->Get('translations_list');
				
				while($oTranslation = $oSetTranslations->Fetch()) {
					
					$aTranslations[] = [
						'language' => $oTranslation->Get('language'),
						'title' => $oTranslation->Get('title'),
						'text' => $oTranslation->Get('text'),
						'url' => $oTranslation->Get('url')
					];
					
				}
				
				
				$oAttDef = MetaModel::GetAttributeDef('ThirdPartyNewsRoomMessage', 'icon');
				$aIcon = $oAttDef->GetForJSON($oMessage->Get('icon'));
				
				$aObjects[] = [
					'thirdparty_message_id' => $oMessage->Get('thirdparty_message_id'),
					'title' => $oMessage->Get('title'),
					'icon' => $aIcon,
					'start_date' => $oMessage->Get('start_date'),
					'end_date' => $oMessage->Get('end_date'),
					'priority' => $oMessage->Get('priority'),
					'target_profiles' => $oMessage->Get('target_profiles'),
					'translations_list' => $aTranslations
				];
				
			}
			
			return $aObjects;
				
		}
		
		
	}
