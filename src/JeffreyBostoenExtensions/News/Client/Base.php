<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 *
 */

namespace JeffreyBostoenExtensions\News\Client;

use JeffreyBostoenExtensions\News\{
	Helper,
};

use JeffreyBostoenExtensions\ServerCommunication\{
	Client\Base as BaseClient,
	Helper as SCHelper,
	RemoteServers\Base as BaseRemoteServer,
	eOperation,
};

// iTop internals.
use DBObjectSearch;
use DBObjectSet;
use MetaModel;

/**
 * Class Base. A common news client to send and retrieve data from one or more third-party (non Combodo) remote servers (person/organization).
 */
class Base extends BaseClient {

	/**
	 * @inheritDoc
	 */
	public function GetRemoteServers(): array {
		
		/** @var array $aDateTimes Key: name of news server, value: last retrieval date */
		$aDateTimes = [];

		// - Get the real timestamp for each remote server with one OQL query.

			$oFilter = DBObjectSearch::FromOQL_AllData('
				SELECT KeyValueStore 
				WHERE 
					namespace = :namespace AND 
					key_name LIKE "%_last_retrieval"
			', [
				'namespace' => Helper::MODULE_CODE
			]);
			$oSet = new DBObjectSet($oFilter);
			
			while($oKeyValue = $oSet->Fetch()) {

				$sKey = str_replace('_last_retrieval', '', $oKeyValue->Get('key_name'));
				$aDateTimes[$sKey] = $oKeyValue->Get('value');

			}


		$aRemoteServers = parent::GetRemoteServers();

		return array_filter($aRemoteServers, function(BaseRemoteServer $oExternalServer) use ($aDateTimes) {
				
			// - Check if it's necessary to add the script to poll the remote server.
				
			$sKeyName = $oExternalServer->GetSanitizedName();
			$sLastRetrieved = array_key_exists($sKeyName, $aDateTimes) ? $aDateTimes[$sKeyName] : '1970-01-01 00:00:00';
			
			// The cron job runs every X minutes.
			$iFrequency = (int)(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'frequency', 60) * 60);

			// Add some extra leniency, as the cron job is preferred.
			// It should be within X, where X = (frequency + 15 mins)
			$iMinTime = strtotime('-'.$iFrequency.' minutes -15 minutes');

			SCHelper::Trace('Source: %1$s - Last retrieved: %2$s - Frequency (minutes): %3$s - Invoke if last requested before: %4$s',
				$sKeyName,
				$sLastRetrieved,
				$iFrequency,
				date('Y-m-d H:i:s', $iMinTime)
			);

			// Only keep if the last retrieval date is too long ago.
			return (strtotime($sLastRetrieved) < $iMinTime);

		});

	}
	

	/**
	 * Gets all the relevant messages for this instance.
	 *
	 * @return void
	 */
	public function GetMessagesForInstance() : void {
		
		SCHelper::Trace('Check for new messages from remote server(s).');
		$eOperation = eOperation::GetMessagesForInstance;
		$this->SetCurrentOperation($eOperation);
		static::DoPostAll();
			
	}
	
	
	/**
	 * Posts info to the remote server(s), unless this is disabled (iTop configuration).
	 * 
	 * This could be used to report statistics about (un)read messages.
	 *
	 * @return void
	 *
	 */
	public function ReportReadStatistics() : void {

		if(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'disable_reporting', false) == true) {
			SCHelper::Trace('Reporting has been disabled.');
			return;
		}

		SCHelper::Trace('Report statistics to remote server(s).');
		
		$eOperation = eOperation::ReportReadStatistics;
		$this->SetCurrentOperation($eOperation);

		SCHelper::Trace('Send (anonymous) data to remote remote servers.');
		
		// Other hooks may have been executed already.
		// Do not leak sensitive data, OQL queries may contain names etc.
			
		// - Post statistics on messages to the news server.

			static::DoPostAll();
		
	}

	
	

}
