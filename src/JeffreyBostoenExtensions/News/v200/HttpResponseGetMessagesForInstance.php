<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News\v200;

use JeffreyBostoenExtensions\ServerCommunication\{
	eCryptographyKeyType,
	eCryptographyLibrary,
	Helper,
	v200\HttpResponse,
};


/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends HttpResponse {

	use MessagesTrait;


	/**
	 * @inheritDoc
	 */
	public function Sign() : void {

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $this->GetHttpRequest()->GetCryptoLib();

        $this->encryption_library = $eCryptoLib->value;
        
		if($eCryptoLib == eCryptographyLibrary::Sodium) {
			
			$this->signature = Helper::SignWithSodium(json_encode($this->messages));

		}

	}

	
}
