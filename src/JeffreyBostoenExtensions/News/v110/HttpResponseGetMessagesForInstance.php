<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v110;

use JeffreyBostoenExtensions\News\{
	Base\HttpResponseGetMessagesForInstance as Base,
	eCryptographyKeyType,
	eCryptographyLibrary,
	Helper
};

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
     * @var Message[] $messages The messages to be sent to the client.
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
        
		// - Used encryption library.

			/** @var eCryptographyLibrary $eCryptoLib */
			$eCryptoLib = $this->GetWorker()->GetHttpRequest()->GetCryptoLib();
			$this->encryption_library = $eCryptoLib->value;

		// - Messsages.

			$oSetMessages->Rewind();

			/** @var ThirdPartyNewsMessage $oMessage */
			while($oMessage = $oSetMessages->Fetch()) {

				$this->messages[] = Message::FromThirdPartyNewsMessage($oMessage);

			}
		
		
	}


	/**
	 * @inheritDoc
	 */
	public function Sign() : void {

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $this->GetWorker()->GetHttpRequest()->GetCryptoLib();

		if($eCryptoLib == eCryptographyLibrary::Sodium) {
						
			// If Sodium is available, use it to sign the messages.
			// The messages are not secret; the signing is just to verify authenticity.
			$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoSign);
			$sSignature = sodium_crypto_sign_detached(json_encode($this->messages), $sPrivateKey);
			
			$this->signature = sodium_bin2base64($sSignature, SODIUM_BASE64_VARIANT_URLSAFE);

		}

	}

}
