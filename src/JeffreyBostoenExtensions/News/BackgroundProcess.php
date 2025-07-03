<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.241108
 *
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use iBackgroundProcess;
use MetaModel;

// Generic.
use Exception;

/**
 * Class BackgroundProcess. A background process that pulls news messages from third-party news sources.
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
		
		
		Helper::Trace('Executing background task: Fetch messages from news sources.');
		
		try {
			
			Client::RetrieveFromRemoteServer();
			Client::PostToRemoteServer();
			
		}
		catch(Exception $e) {
			Helper::Trace($e->GetMessage());
		}
		
		Helper::Trace('Finished background task.');
		
	}
	
}
