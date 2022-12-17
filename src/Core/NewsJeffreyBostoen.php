<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220607
 *
 */

	namespace jb_itop_extensions\NewsProvider;
	
	
	/**
	 * Class NewsSourceJeffreyBostoen. A news source.
	 */
	abstract class NewsSourceJeffreyBostoen implements iNewsSource {
		
		/**
		 * @inheritDoc
		 */
		public static function GetThirdPartyName() {
			
			return 'Jeffrey Bostoen';
			
		}
		
		/**
		 * @inheritDoc
		 */
		public static function GetPayload($sOperation) {

			return [];
			
		}
		
		/**
		 * @inheritDoc
		 */
		public static function GetUrl() {

			return 'https://itop-news.jeffreybostoen.be';
		
		}
		
		/**
		 * @inheritDoc
		 */
		public static function GetPublicKeySodium() {
			
			return 'SafJHvlxp3ktweQDbRnkwvm6ih4dru2H3ydvVaA0xSI=';
			
		}
		
	}
	
	
	/**
	 * Class NewsServerProcessorJeffreyBostoen. A news server processor which will keep track of some specific info.
	 */
	abstract class NewsServerProcessorJeffreyBostoen {
		
		/**
		 * @inheritDoc
		 */
		public static function Process() {
			
			// To be fully implemented.
			
			// This could check if the instance exists already and update the info.
			// If it's new, there is probably no way to link it to a customer. Create under an 'unknown' organization.
			
		}
		
		
	}
	