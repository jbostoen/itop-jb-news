<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-11-04 15:45:48
 *
 * Definition of ScheduledProcessCrabSync
 */

namespace jb_itop_extensions\NewsClient;

// iTop internals
use \CoreUnexpectedValue;
use \iBackgroundProcess;
use \MetaModel;
use \utils;

/**
 * Class ScheduledProcessThirdPartyNews
 */
class ProcessThirdPartyNews implements iBackgroundProcess {
	
	/**
	 * @var \String Module code
	 */
	public const MODULE_CODE = 'jb-news-client';
	
	/**
	 * @inheritdoc
	 */
	public function GetPeriodicity() {
		
		return (Int)MetaModel::GetModuleSetting(self::MODULE_CODE, 'frequency', 1 * 60); // minutes
		
	}
	
	/**
	 * @inheritdoc
	 */
	public function Process($iTimeLimit) {
		
		$this->Trace(self::MODULE_CODE.' - Processing News ...');
		
		try {
			
			NewsClient::RetrieveFromRemoteServer($this);
			NewsClient::PostToRemoteServer($this);
			
		}
		catch(Exception $e) {
			$this->Trace($e->GetMessage());
		}
		
		$this->Trace(self::MODULE_CODE.' - Finished processing.');
		
	}
	
	/**
	 * Could be used for debugging.
	 *
	 * @param \String $sMessage Message to put in the trace log (CRON output)
	 * @param \String $sType Type of message. Possible values: info, error
	 *
	 * @return void
	 */
	public function Trace($sMessage, $sType = 'info') {
		
		// Nothing
		// echo $sMessage;
				
	}
	
}
