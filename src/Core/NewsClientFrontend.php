<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220607
 *
 */
 
	namespace jb_itop_extensions\NewsProvider;
	
	use \AbstractPageUIBlockExtension;
	use \MetaModel;
	
	// As of iTop 3.0.0
	if(class_exists('AbstractPageUIBlockExtension') == true) {
		
		/**
		 * @inheritDoc
		 * @since 3.0.0
		 */
		class ScriptBlock extends AbstractPageUIBlockExtension {
			
			/**
			 * @inheritDoc
			 */
			public function GetHeaderBlock() {
				
				$sOperation = 'get_messages_for_instance';
				$sEncryptionLib = static::GetEncryptionLibrary();
				
				// Build list of news sources.
				// -
				
					$aSources = static::GetSources();
					
				
				// Request messages from each news source.
				// -
				
					foreach($aSources as $sSourceClass) {
						
						
						$aPayload = static::GetPayload($sSourceClass, $sOperation);
						$sNewsUrl = $sSourceClass::GetUrl();
						
						$aData = [
							'operation' => $sOperation,
							'api_version' => NewsRoomHelper::DEFAULT_API_VERSION,
							'payload' => base64_encode(json_encode($aPayload))
						];
						
						$sNewsUrl = preg_replace('/\?.*$/', '', $sNewsUrl).'?&'.http_build_query($aData);
						
						// - Add call to external news source
						
						
						// - Add call back to current iTop environment
						
					}
				
				
				return null;
				
			}
			
		}

	}
	