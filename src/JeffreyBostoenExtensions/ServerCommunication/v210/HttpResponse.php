<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\v210;

use JeffreyBostoenExtensions\ServerCommunication\{
    eCryptographyKeyType,
    eCryptographyLibrary,
    Helper
};
use JeffreyBostoenExtensions\ServerCommunication\Base\HttpResponse as Base;

/**
 * Class HttpResponse. Represents an outgoing response to a client. (API v2.1.0)
 */
class HttpResponse extends Base {

    /**
     * @var string $crypto_lib The crypto library used to sign the response.
     */
    public string $crypto_lib;

    /**
     * @var string|null $new_client_token The new client token (now stored on the server).
     */
    public ?string $new_client_token;


	
	/**
	 * @inheritDoc
	 */
	public function Sign() : void {

		/** @var HttpRequest_Base $oRequest */
		$oRequest = $this->GetHttpRequest();

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $oRequest->GetCryptoLib();
        
        $this->crypto_lib = $eCryptoLib->value;

		if($eCryptoLib == eCryptographyLibrary::Sodium) {
			
			if(version_compare($oRequest->extension_version, '3.2.251012', '<')) {
				$sToSign = json_encode($this->messages);
			}
			else {
				// Explicitly set this to a null value.
				$this->signature = null;
				$sToSign = json_encode($this);
			}

			// If Sodium is available, use it to sign the messages.
			// The messages are not secret; the signing is just to verify authenticity.
			$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoSign);
			$sSignature = sodium_crypto_sign_detached($sToSign, $sPrivateKey);
			
			$this->signature = sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE);

		}

	}

}
