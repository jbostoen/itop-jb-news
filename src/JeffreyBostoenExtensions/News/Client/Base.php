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
	eOperation,
};

// iTop internals.
use MetaModel;

/**
 * Class Base. A common news client to send and retrieve data from one or more third-party (non Combodo) remote servers (person/organization).
 */
class Base extends BaseClient {


	/**
	 * Gets all the relevant messages for this instance.
	 *
	 * @return void
	 */
	public function GetMessagesForInstance() : void {
		
		SCHelper::Trace('Check for new messages from remote server(s).');
		$eOperation = eOperation::NewsGetMessagesForInstance;
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
		
		$eOperation = eOperation::NewsTelemetry;
		$this->SetCurrentOperation($eOperation);

		SCHelper::Trace('Send (anonymous) data to remote remote servers.');
		
		// Other hooks may have been executed already.
		// Do not leak sensitive data, OQL queries may contain names etc.
			
		// - Post statistics on messages to the news server.

			static::DoPostAll();
		
	}

	
	

}
