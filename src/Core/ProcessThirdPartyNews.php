<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220607
 *
 */

namespace jb_itop_extensions\NewsProvider;

// iTop internals
use \iBackgroundProcess;
use \MetaModel;

/**
 * Class ProcessThirdPartyNews. a background process which pulls news messages from a third party news source.
 */
class ProcessThirdPartyNews implements iBackgroundProcess {
	
	/**
	 * @var \String Module code
	 */
	public const MODULE_CODE = 'jb-news';
	
	/**
	 * @inheritdoc
	 */
	public function GetPeriodicity() {
		
		// Periodicity (is returned in seconds)
		return (Int)(MetaModel::GetModuleSetting(static::MODULE_CODE, 'frequency', 60) * 60); // minutes
		
	}
	
	/**
	 * @inheritdoc
	 */
	public function Process($iTimeLimit) {
		
		
		$this->Trace(static::MODULE_CODE.' - Processing ...');
		
		try {
			
			NewsClient::RetrieveFromRemoteServer($this);
			NewsClient::PostToRemoteServer($this);
			NewsClient::SyncLinks();
			
		}
		catch(Exception $e) {
			$this->Trace($e->GetMessage());
		}
		
		$this->Trace(static::MODULE_CODE.' - Finished.');
		
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
		echo $sMessage.PHP_EOL;
		
	}
	
}
