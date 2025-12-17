<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\v110;

use JeffreyBostoenExtensions\ServerCommunication\{
    eCryptographyKeyType,
    eCryptographyLibrary,
    Helper
};
use JeffreyBostoenExtensions\ServerCommunication\Base\HttpResponse as Base;

/**
 * Class HttpResponse. Represents an outgoing response to a client. (API v1.1.0). 
 */
class HttpResponse extends Base {

    /**
     * @var string $encryption_library The crypto library used to sign the response.
     */
    public string $encryption_library;


	/**
	 * @inheritDoc
	 */
	public function Sign() : void {

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $this->GetHttpRequest()->GetCryptoLib();

        $this->crypto_lib = $eCryptoLib->value;
        
		if($eCryptoLib == eCryptographyLibrary::Sodium) {
						
			// If Sodium is available, use it to sign the messages.
			// The messages are not secret; the signing is just to verify authenticity.
			$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoSign);
			$sSignature = sodium_crypto_sign_detached(json_encode($this->messages), $sPrivateKey);
			
			$this->signature = sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE);

		}

	}

}
