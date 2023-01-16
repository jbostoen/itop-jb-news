<?php

/**
 * @copyright   Copyright (c) 2019-2023 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.230116
 *
 */

	namespace jb_itop_extensions\NewsProvider;

	// iTop classes
	use \DBObjectSearch;
	use \DBObjectSet;
	use \MetaModel;
	use \utils;
	
	// Common
	use \Exception;

	/**
	 * Interface iNewsServerProcessor. Interface to implement some server-side actions when a client connects.
	 */
	interface iNewsServerProcessor {
		
		/**
		 * Processes some custom actions.
		 * Example: keep track of which instances last connected, process custom info from iNewsSource::GetPayload()
		 *
		 * @param \String $sOperation Operation.
		 * @param \Array $aPlainPayload Plain payload.
		 *
		 * @return void
		 *
		 */
		public static function Process($sOperation, $aPlainPayload = []);
		
	}
	
	/**
	 * Class NewsServer. A news server which is capable to output all messages for an instance.
	 */
	abstract class NewsServer {
		
		/**
		 * @var \String $sApiVersion API version
		 */
		private static $sApiVersion = '1.1.0';
		
		/**
		 * Gets API version of server.
		 *
		 * @return \String
		 */
		public static function GetApiVersion() {
			
			return static::$sApiVersion;
			
		}
		
		/**
		 * Gets all the relevant messages for an instance.
		 *
		 * @return \Array
		 */
		public static function GetMessagesForInstance() {
			
			
			if(utils::ReadParam('api_version', '1.0') === '1.0') {
				
				// Deprecated, to be removed soon.
				$sAppName = utils::ReadParam('app_name', '');
				$sAppVersion = utils::ReadParam('app_name', '');
				$sInstanceId = utils::ReadParam('instance_hash', '');
				$sInstanceId2 = utils::ReadParam('instance_hash2', '');
				$sApiVersion = utils::ReadParam('api_version', '1.0');
			
			}
			else {
				
				$aPayload = json_decode(base64_decode(utils::ReadParam('payload', '[]')), true);
				
				$sAppName = $aPayload['app_name'];
				$sAppVersion = $aPayload['app_version'];
				$sInstanceId = $aPayload['instance_hash'];
				$sInstanceId2 = $aPayload['instance_hash2'];
				$sApiVersion = $aPayload['api_version'];
				
			}
			
		
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
				
				// Note: all attributes should exist on the ThirdPartyNewsRoomMessage class of the client-side.
				switch($sApiVersion) {
					
					case '1.0':
						
						$aObjects[] = [
							'thirdparty_message_id' => $oMessage->Get('thirdparty_message_id'),
							'title' => $oMessage->Get('title'),
							'icon' => $aIcon,
							'start_date' => $oMessage->Get('start_date'),
							'end_date' => $oMessage->Get('end_date'),
							'priority' => $oMessage->Get('priority'),
							'target_profiles' => 'Administrator', // API 1.0 was only used for a very small number of users. "Administrators" were always targeted in the messages that were published.
							'translations_list' => $aTranslations
						];
						break;
						
					case '1.1.0':
					
						// target_profiles is deprecated.
						// oql has been added.
						$aObjects[] = [
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
					
				}
				
			}
			
			return $aObjects;
				
		}
		
		/**
		 * Processes each third party implementation of iNewsServerProcessor.
		 * Currently, these run after executing the normal actions for an 'operation'.
		 *
		 * @param \String $sOperation Operation.
		 * @param \Array $aPlainPayload Plain payload.
		 *
		 * @return void
		 */
		public static function RunThirdPartyProcessors($sOperation, $aPlainPayload = []) {
			
		
			// Build list of processors.
			// -
			
				$aProcessors = [];
				foreach(get_declared_classes() as $sClassName) {
					$aImplementations = class_implements($sClassName);
					if(in_array('jb_itop_extensions\NewsProvider\iNewsServerProcessor', $aImplementations) == true || in_array('iNewsServerProcessor', class_implements($sClassName)) == true) {
						
						$aProcessors[] = $sClassName;
						
					}
				}
				
			// Run each processor.
			// -
				
				foreach($aProcessors as $sProcessor) {
					
					$sProcessor::Process($sOperation, $aPlainPayload);
					
				}
			
		}
	
		/**
		 * Returns Sodium private key.
		 *
		 * @param \String $sKeyType Should be one of these: private_key_file_crypto_sign , private_key_file_crypto_box
		 *
		 * @return \String
		 *
		 * @throws \Exception
		 */
		public static function GetKeySodium($sType) {
			
	
			// Get private key
			$aKeySettings = MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'sodium', []);
			
			
			if(is_array($aKeySettings) == true && array_key_exists($sType, $aKeySettings) == true) {
				
				$sKeyFile = $aKeySettings[$sType];
				
				if(file_exists($sKeyFile) == false) {
					throw new Exception('Missing '.$sType.' key file.');
				}
				
				$sKey = file_get_contents($sKeyFile);
				
				$sBinKey = sodium_base642bin($sKey, SODIUM_BASE64_VARIANT_URLSAFE);
				
				return $sBinKey;
				
			}
			
			return null;
					
		}
		
		/**
		 * Gets the plain payload that was sent to the server.
		 *
		 * @param \String $sPayload Payload
		 *
		 * @return \Array Hash table.
		 */
		public static function GetPlainPayload($sPayload) {
		
			if($sPayload == '') {
				throw new Exception('Payload is empty.');
			}
			
			// Payloads can be either encrypted or unencrypted (Sodium not available on the iTop instance which is requesting news messages).
			// Either way, they are base64 encoded.
			$sPayload = base64_decode($sPayload);
			
			// Doesn't seem regular JSON yet; try unsealing
			if(substr($sPayload, 0, 1) !== '{') {
				
				$sPrivateKey = static::GetKeySodium('private_key_crypto_box');
				$sPublicKey = static::GetKeySodium('public_key_crypto_box');
				$sPayload = sodium_crypto_box_seal_open($sPayload, sodium_crypto_box_keypair_from_secretkey_and_publickey($sPrivateKey, $sPublicKey));
				
				if(substr($sPayload, 0, 1) !== '{') {
					
					throw new Exception('Unable to decode payload: '. utils::ReadParam('payload', '', 'raw_data')); // Refer to original data.
					
				}
				
			}
			
			$aPayload = json_decode($sPayload, true);
			
			return $aPayload;
			
		}
	
	}
