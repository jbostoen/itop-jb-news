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
	Message
};

use JeffreyBostoenExtensions\ServerCommunication\{
	Client\Base as BaseClient,
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
		
		return parent::GetRemoteServers();
		
		$aSources = parent::GetRemoteServers();
		$aLastRetrieved = static::GetLastRetrievedDateTimePerRemoteServersource();

		return array_filter($aSources, function(BaseRemoteServer $oExternalServer) use ($aLastRetrieved) {
				
			// - Check if it's necessary to add the script to poll the remote server.
				
			$sKeyName = $oExternalServer->GetSanitizedName();
			$sLastRetrieved = $aLastRetrieved[$sKeyName];
			
			// The cron job runs every X minutes.
			$iFrequency = (int)(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'frequency', 60) * 60);

			// Add some extra leniency, as the cron job is preferred.
			// It should be within X, where X = (frequency + 15 mins)
			$iMinTime = strtotime('-'.$iFrequency.' minutes -15 minutes');

			Helper::Trace('Source: %1$s - Last retrieved: %2$s - Frequency (minutes): %3$s - Invoke if last requested before: %4$s',
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
	 * Returns an object set of key/value pairs. Each key will be an identifier for a remote server, and the value a timestamp.
	 *
	 * @return array Hashtable where the key is the remote server name, and the value is the last retrieved date/time.
	 */
	public function GetLastRetrievedDateTimePerRemoteServersource() : array {
		
		$aDateTimes = [];

		// - Ensure every remote server has a last retrieved date/time.

			/** @var BaseRemoteServer $oExternalServer */
			foreach(static::GetRemoteServers() as $oExternalServer) {

				$sExtServerSource = $oExternalServer->GetSanitizedName();
				$aDateTimes[$sExtServerSource] = '1970-01-01 00:00:00'; // Default value
			
			}

		// - Where available, get the real timestamp.

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

		Helper::Trace('Last retrieved timestamps: %1$s', json_encode($aDateTimes, JSON_PRETTY_PRINT));

		return $aDateTimes;
		
	}


	/**
	 * Gets all the relevant messages for this instance.
	 *
	 * @return void
	 */
	public function GetMessagesForInstance() : void {
		
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
			Helper::Trace('Reporting has been disabled.');
			return;
		}
		
		$eOperation = eOperation::ReportReadStatistics;
		$this->SetCurrentOperation($eOperation);

		Helper::Trace('Send (anonymous) data to remote remote servers.');
		
		// Other hooks may have been executed already.
		// Do not leak sensitive data, OQL queries may contain names etc.
			
		// - Post statistics on messages to the news server.

			static::DoPostAll();
		
	}

	
	

}
