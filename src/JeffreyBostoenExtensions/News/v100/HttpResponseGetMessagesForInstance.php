<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v100;

use JeffreyBostoenExtensions\News\Base\HttpResponseGetMessagesForInstance as Base;

// iTop internals.
use DBObjectSet;

// iTop classes.
use ThirdPartyNewsMessage;

/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends Base {

    /**
     * @var string $encryption_library The crypto library used to sign the response.
     */
    public string $encryption_library;

    /**
     * @var array $messages The messages to be sent to the client.
     */
    public array $messages = [];

    /**
     * @var string|null $signature The signature of the response.
     */
    public ?string $signature;

	/**
	 * Builds the response.
	 * 
	 * This must be handled in subclasses.
	 *
	 * @param DBObjectSet $oSetMessages
	 * @return void
	 */
    public function BuildResponse(DBObjectSet $oSetMessages) : void {

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $this->GetWorker()->GetHttpRequest()->GetCryptoLib();
		$this->encryption_library = $eCryptoLib->value;
        
		$oSetMessages->Rewind();

		/** @var ThirdPartyNewsMessage $oMessage */
		while($oMessage = $oSetMessages->Fetch()) {
			$this->messages[] = Message::FromThirdPartyNewsMessage($oMessage);
		}

	}

}