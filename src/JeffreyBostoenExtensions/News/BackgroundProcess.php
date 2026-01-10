<?php

/**
 * @copyright   Copyright (c) 2019-2026 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.260110
 *
 */

namespace JeffreyBostoenExtensions\News;

use JeffreyBostoenExtensions\ServerCommunication\{
	Helper as SCHelper,
	eOperationMode
};

use JeffreyBostoenExtensions\News\Client\Base as Client;

// iTop internals.
use iBackgroundProcess;
use MetaModel;

// Generic.
use Exception;

/**
 * Class BackgroundProcess. A background process that pulls news messages from third-party remote servers.
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
		if(MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'client', false) == false) {
			// The client is not enabled, so don't do anything.
			return;
		}
		
		
		SCHelper::Trace('Executing background task: Connect to news servers.');
		
		try {
			
			$oClient = new Client(eOperationMode::Cron);
			$oClient->GetMessagesForInstance();
			$oClient->ReportReadStatistics();

			
		}
		catch(Exception $e) {
			SCHelper::Trace($e->GetMessage());
		}
		
		SCHelper::Trace('Finished background task.');
		
	}
	
}
