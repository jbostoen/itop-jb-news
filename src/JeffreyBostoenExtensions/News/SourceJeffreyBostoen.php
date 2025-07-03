<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.241108
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals
use MetaModel;

/**
 * Class SourceJeffreyBostoen. A news source.
 */
abstract class SourceJeffreyBostoen implements iSource {
	
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

		return [
			'app_root_url' => MetaModel::GetConfig()->Get('app_root_url')
		];
		
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
	public static function GetPublicKeySodiumCryptoBox() {
		
		return '_MFByYo4dIpQ-Z8j9jy8cwxU4EH5vVXj5HqhVo02PD4=';
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetPublicKeySodiumCryptoSign() {
		
		return 'SafJHvlxp3ktweQDbRnkwvm6ih4dru2H3ydvVaA0xSI=';
		
	}
}


