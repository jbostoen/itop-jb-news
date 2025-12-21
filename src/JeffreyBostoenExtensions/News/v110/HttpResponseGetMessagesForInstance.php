<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News\v110;

use JeffreyBostoenExtensions\News\v100\MessagesTrait;
	
use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	eCryptographyKeyType,
	eCryptographyLibrary,
	Helper,
	v110\HttpResponse,
};


/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends HttpResponse {

	use MessagesTrait;

    /**
     * @var string $encryption_library The crypto library used to sign the response.
     */
    public string $encryption_library;


	/**
	 * @inheritDoc
	 */
	public function GetOutput(): string {

		
		$oRequest = $this->GetHttpRequest();
		$oResponse = $this->GetHttpResponse();
		
		if(
			$oRequest->GetCryptoLib() == eCryptographyLibrary::None &&
			($oRequest->GetApiVersion() == eApiVersion::v1_0_0 || $oRequest->GetApiVersion() == eApiVersion::v1_1_0)
		) {
			
			return json_encode($oResponse->messages);

		}
		
		return parent::GetOutput();
		
	}


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
