<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News\v200;

use JeffreyBostoenExtensions\ServerCommunication\{
	eCryptographyLibrary,
	Helper,
	LocalServer\SodiumHelper,
	Protocol\v210\HttpResponse,
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

		// Note: API v2.0.0 only signed the "messages" property.
		Helper::Trace('Signing only the messages.');

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $this->GetHttpRequest()->GetCryptoLib();

        $this->crypto_lib = $eCryptoLib->value;
        
		if($eCryptoLib == eCryptographyLibrary::Sodium) {
			
			$this->signature = SodiumHelper::Sign(json_encode($this->messages));

		}

	}

	
}
