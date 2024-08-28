<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240828
 *
 */
 
	namespace jb_itop_extensions\NewsProvider;
	
	use \iBackofficeReadyScriptExtension;
	use \MetaModel;
	use \utils;
	 
	// As of iTop 3.0.0
	if(interface_exists('iBackofficeReadyScriptExtension') == true) {
	
		/**
		 * @inheritDoc
		 * @since 3.0.0
		 */
		class ReadyScripts implements iBackofficeReadyScriptExtension {
			
			/**
			 * @inheritDoc
			 */
			public function GetReadyScript() : string {
				
				$sCode = 
<<<JS
	// News sources (if any)
	
JS;

				$sOperation = 'get_messages_for_instance';
				$sEncryptionLib = NewsClient::GetEncryptionLibrary();
				
				// Build list of news sources.
				// -
				
					$aSources = NewsClient::GetSources();
					
					$oSetLastRetrieved = NewsClient::GetLastRetrieved();
				
				// Request messages from each news source.
				// -
				
					foreach($aSources as $sSourceClass) {
						
						// Check if necessary to add
							
							$sLastRetrieved = '1970-01-01 00:00:00';
							$sThirdPartyName = $sSourceClass::GetThirdPartyName();
							$sKeyName = 'news_'. preg_replace('/[^a-zA-Z0-9]+/', '', $sThirdPartyName);
							
							$oSetLastRetrieved->Rewind();
							while($oLastRetrieved = $oSetLastRetrieved->Fetch()) {
								
								if($oLastRetrieved->Get('key_name') == $sKeyName) {
								
									$sLastRetrieved = $oLastRetrieved->Get('value');
									break;
									
								}
								
							}
							
							// Last retrieval using scheduled task (cron job) seems to have worked
							if(strtotime($sLastRetrieved) >= strtotime('-'.(Int)(MetaModel::GetModuleSetting(NewsRoomHelper::MODULE_CODE, 'frequency', 60) * 60).' minutes').' -5 minutes') {
								
								continue; // Skip and process next source
								
							}
						
						// Build call to external news source
						// -
						
							$aPayload = NewsClient::GetPayload($sSourceClass, $sOperation);
							$sPayload = NewsClient::PreparePayload($sSourceClass, $aPayload);
							
							$sServerUrl = $sSourceClass::GetUrl();
							
							$sApiVersion = NewsRoomHelper::DEFAULT_API_VERSION;
							
							$aData = [
								'operation' => $sOperation,
								'api_version' => $sApiVersion,
								'payload' => $sPayload,
								'callback' => $sKeyName
							];
							
							$sData = json_encode($aData);
						
						// - Add call to external news source
						// - Add call back method to current iTop environment, make sure call back function exists
						
							// @todo Could become more compact in the future. But assuming there are not many news sources using this extension at this point, not a priority.
						
							$sClientUrl = utils::GetAbsoluteUrlExecPage().'?exec_module='.NewsRoomHelper::MODULE_CODE.'&exec_page=index.php';
							$sSourceClassSlashed = addslashes($sSourceClass);
						
							$sCode .=
<<<JS
								$.ajax({
									url: '{$sServerUrl}',
									dataType: 'jsonp',
									data: {$sData},
									type: 'GET', // JSONP is GET.
									jsonpCallback: '{$sKeyName}',
									contentType: 'application/json; charset=utf-8',
									success: function (result, status, xhr) {
										
										console.log('Retrieved data from {$sThirdPartyName}');
										
										// Post response from news source to iTop
										$.ajax({
												url: '{$sClientUrl}',
												dataType: 'json',
												data: {
													operation: 'post_messages_to_instance',
													api_version: '{$sApiVersion}',
													sourceClass: '{$sSourceClassSlashed}',
													data: JSON.stringify(result)
												},
												type: 'POST', // Without POST, this is highly likely to result in 414 Request-URI Too Long
												success: function(result, status, xhr) {
													
													// Send statistics from iTop to news source (just try, it may fail if the Request-URI becomes too long).
													$.ajax({
														url: '{$sServerUrl}',
														dataType: 'jsonp',
														data: {
															operation: 'report_read_statistics',
															api_version: '{$sApiVersion}',
															payload: result.payload
														},
														type: 'GET',
														crossDomain: true,
														jsonpCallback: '{$sKeyName}',
														contentType: 'application/json; charset=utf-8',
														success: function (result, status, xhr) {
															
															// Doesn't matter
															
														}
														
													});
													
												}
										});
										
									},
									error: function (xhr, status, error) {
										console.log('Result for news source {$sThirdPartyName}: ' + status + ' ' + error + ' ' + xhr.status + ' ' + xhr.statusText);
									}
								});

JS;
						
					}
				
				// Return result.
				// -
				
				return $sCode;
			}
			
		}


	}
	
