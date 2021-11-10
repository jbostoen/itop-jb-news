<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-11-04 15:45:48
 *
 * Definition of ScheduledProcessCrabSync
 */

namespace jb_itop_extensions\NewsClient;

// jb-jb_itop_extensions
use \jb_itop_extensions\components\ScheduledProcess;

// iTop internals
use \CoreUnexpectedValue;
use \iScheduledProcess;
use \MetaModel;
use \utils;

/**
 * Class ScheduledProcessThirdPartyNews
 */
class ScheduledProcessThirdPartyNews extends ScheduledProcess implements iScheduledProcess {
	
	/**
	 * @var \String Module code
	 */
	public const MODULE_CODE = 'jb-news-client';

	/**
	 * Constructor.
	 */
	public function __construct() {
		
		parent::__construct();

	}

	/**
	 * @inheritdoc
	 */
	public function Process($iTimeLimit) {
		
		$this->Trace(self::MODULE_CODE.' - Processing News ...');
		
		try {
			
			NewsClient::GetMessages($this);
			// NewsClient::PostMessageReadStatus();
			
		}
		catch(Exception $e) {
			$this->Trace($e->GetMessage());
		}
		
		$this->Trace(self::MODULE_CODE.' - Finished processing News from Jeffrey Bostoen.');
		
	}
	
}
