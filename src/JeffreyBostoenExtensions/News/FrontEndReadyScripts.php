<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250725
 *
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use iBackofficeReadyScriptExtension;
use MetaModel;
use stdClass;
use utils;
	

/**
 * Class FrontEndReadyScripts. This hooks into the iTop back office.
 */
class FrontEndReadyScripts implements iBackofficeReadyScriptExtension {
	
	/**
	 * @inheritDoc
	 */
	public function GetReadyScript() : string {
		
		$sCode = '';

		$eOperation = eOperation::GetMessagesForInstance;
		$sOperation = $eOperation->value;
		
		// - Build list of news sources & check last retrieval date.

			$aSources = Client::GetSources();
			$aLastRetrieved = Client::GetLastRetrievedDateTimePerNewsSource();
		
		// - Request messages from each news source (if needed).
		
			foreach($aSources as $sSourceClass) {
				
				// - Check if it's necessary to add the script to poll the news source.
					
					$sKeyName = Client::GetSanitizedNewsSourceName($sSourceClass);
					$sLastRetrieved = $aLastRetrieved[$sKeyName];
					
					// The cron job runs every X minutes.
					$iFrequency = (int)(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'frequency', 60) * 60);

					// Add some extra leniency, as the cron job is preferred.
					$iMinTime = strtotime('-'.$iFrequency.' minutes -15 minutes');

					// If it seems the cron job successfully retrieved messages recently, skip.
					if(strtotime($sLastRetrieved) >= $iMinTime) {
						continue; // Skip and process next source.
					}
				
				// - Build request to external news source.
				
					$oPayload = Client::GetPayload($sSourceClass, $eOperation, eOperationMode::Mitm);
					$sPayload = Client::PreparePayload($sSourceClass, $oPayload);
					
					$sServerUrl = $sSourceClass::GetUrl();
					$sApiVersion = eApiVersion::v2_0_0->value;
					
					// - Prepare data to send to the news source.
					$oData = new stdClass();
					$oData->operation = $sOperation;
					$oData->api_version = $sApiVersion;;
					$oData->payload = $sPayload;
					$oData->callback = $sKeyName;
					
					$sData = json_encode($oData);
				
				// - Add HTTP request to external news source.
				// - Add callback method to current iTop environment. Make sure the call back function exists.
										
					// @todo Could become more compact in the future. 
					// But assuming there are not many news sources using this extension at this point, not a priority.
				
					$sClientUrl = utils::GetAbsoluteUrlExecPage().'?'.
						'&exec_module='.Helper::MODULE_CODE.
						'&exec_page=index.php';
					$sSourceClassSlashed = addslashes($sSourceClass);

					$sThirdPartyName = $sSourceClass::GetThirdPartyName();
				
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
								console.log(`Result for news source {$sThirdPartyName}: \${status} \${error} \${xhr.status} \${xhr.statusText}`);
							}
						});

JS;
				
			}
		
		// - Return result.
		
		return $sCode;
	}
	
}

