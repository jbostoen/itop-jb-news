<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 *
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use iBackgroundProcess;
use MetaModel;

// Generic.
use Exception;

/**
 * Class BackgroundProcess. A background process that pulls news messages from third-party external servers.
 */
class BackgroundProcess implements iBackgroundProcess {
	
	/**
	 * @inheritdoc
	 */
	public function GetPeriodicity() {
		
		// Periodicity (is returned in seconds)
		return (Int)(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'frequency', 60) * 60); // minutes
		
	}
	
	/**
	 * @inheritdoc
	 */
	public function Process($iTimeLimit) {

		if(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'enabled', false) == false) {
			// The extension is not enabled, so don't do anything.
			return;
		}
		
		
		Helper::Trace('Executing background task: Fetch messages from external servers.');
		
		try {
			
			Client::RetrieveMessagesFromExternalServer();
			Client::PostStatisticsToRemoteServer();
			
		}
		catch(Exception $e) {
			Helper::Trace($e->GetMessage());
		}
		
		Helper::Trace('Finished background task.');
		
	}
	
}
