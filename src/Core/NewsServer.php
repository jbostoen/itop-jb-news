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
		 * Returns hash of instance
		 */
		protected static function GetInstanceHash() {
		
			return utils::ReadParam('instance_hash', '');
			
		}
		
		/**
		 * Gets all the relevant messages for an instance
		 *
		 * @return void
		 */
		public static function GetMessagesForInstance() {
			
			$sAppName = utils::ReadParam('app_name', '');
			$sAppVersion = utils::ReadParam('app_name', '');
			$sInstanceId = self::GetInstanceHash();
			
			// Output all messages with their translations
			// Theoretically additional filtering could be applied to reduce JSON size
			// Or logging could be added
			
				
				
		}
		
		
	}
