<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220607
 *
 */
 
	namespace jb_itop_extensions\NewsProvider;
	
	use \iBackofficeReadyScriptExtension;
	use \MetaModel;
	
	// As of iTop 3.0.0
	if(class_exists('iBackofficeReadyScriptExtension') == true) {
		
		/**
		 * @inheritDoc
		 * @since 3.0.0
		 */
		class ReadyScripts implements iBackofficeReadyScriptExtension {
			
			/**
			 * @inheritDoc
			 */
			public function GetReadyScript() {
				
				$sCode = '';
				$sOperation = 'get_messages_for_instance';
				$sEncryptionLib = static::GetEncryptionLibrary();
				
				// Build list of news sources.
				// -
				
					$aSources = static::GetSources();
					
					$oSetLastRetrieved = NewsClient::GetLastRetrieved();
				
				// Request messages from each news source.
				// -
				
					foreach($aSources as $sSourceClass) {
						
						// Check if necessary to add
							
							$sLastRetrieved = '1970-01-01 00:00:00';
							$sKeyName = 'news_'. preg_replace('/[^a-zA-Z0-9]+/', '', $sSourceClass::GetThirdPartyName());
							
							$oSetLastRetrieved->Rewind();
							while($oLastRetrieved = $oSetLastRetrieved->Fetch()) {
								
								if($oLastRetrieved->Get('key') == $sKeyName) {
								
									$sLastRetrieved = $oLastRetrieved->Get('value');
									break;
									
								}
								
							}
							
							// Last retrieval using scheduled task (cron job) seems to have worked
							if(strtotime($sLastRetrieved) >= strtotime('-'.(Int)(MetaModel::GetModuleSetting(static::MODULE_CODE, 'frequency', 60) * 60).' minutes')) {
								
								continue; // Skip and process next source
								
							}
						
						// Build call to external news source
						// -
						
						$aPayload = static::GetPayload($sSourceClass, $sOperation);
						$sNewsUrl = $sSourceClass::GetUrl();
						
						$aData = [
							'operation' => $sOperation,
							'api_version' => NewsRoomHelper::DEFAULT_API_VERSION,
							'payload' => base64_encode(json_encode($aPayload))
						];
						
						$sData = json_encode($aData);
						
						// - Add call to external news source
						// - Add call back method to current iTop environment, make sure call back function exists
						
						$sCode .=
<<<JS
							$.ajax({
								url: '{$sNewsUrl}',
								dataType: 'jsonp',
								data: {$sData},
								type: 'POST',
								jsonpCallback: '{$sKeyName}',
								contentType: 'application/json; charset=utf-8',
								success: function (result, status, xhr) {
									console.log(result);
								},
								error: function (xhr, status, error) {
									console.log('Result: ' + status + ' ' + error + ' ' + xhr.status + ' ' + xhr.statusText);
								}
							});

JS;
						
					}
				
				
					return $sCode;
				
				}
				
			}
			
		}

	}
	