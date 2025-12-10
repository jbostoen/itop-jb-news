<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v200;

use JeffreyBostoenExtensions\News\{
	eCryptographyKeyType,
	eCryptographyLibrary,
	Helper,
	Base\HttpResponseGetMessagesForInstance as Base
};

// iTop internals.
use DBObjectSet;
use JeffreyBostoenExtensions\News\v100\HttpRequest;
// iTop classes.
use ThirdPartyNewsMessage;

// Generic.
use stdClass;

/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends Base {

    /**
     * @var string $crypto_lib The crypto library used to sign the response.
     */
    public string $crypto_lib;

    /** 
     * @var object|null $icons The icon library. Key = "ref_md5", value = an Icon. */
    public ?object $icons;

    /**
     * @var string|null $refresh_token The refresh token.
     */
    public ?string $refresh_token;

	/**
     * @var Message[] $messages The messages to be sent to the client.
	 */
	public array $messages = [];

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
		$this->crypto_lib = $eCryptoLib->value;

		$oSetMessages->Rewind();

		$oIconLib = new stdClass;

		/** @var ThirdPartyNewsMessage $oObj */
		while($oObj = $oSetMessages->Fetch()) {

			$oMessage = Message::FromThirdPartyNewsMessage($oObj);
			$this->messages[] = $oMessage;

			$oIcon = $oMessage->GetIcon();

			if($oIcon !== null) {
				
				$sIconRef = $oIcon->GetRef();
				$oIconLib->$sIconRef = $oIcon;

			}


		}

		$this->icons = $oIconLib;
		
		// The 'refresh_token' should be set by one iServerExtension.

	}

	
	/**
	 * @inheritDoc
	 */
	public function Sign() : void {

		/** @var ServerWorker $oWorker */
		$oWorker = $this->GetWorker();

		/** @var HttpRequest $oRequest */
		$oRequest = $oWorker->GetHttpRequest();

		/** @var eCryptographyLibrary $eCryptoLib */
		$eCryptoLib = $oRequest->GetCryptoLib();

		if($eCryptoLib == eCryptographyLibrary::Sodium) {
			
			if(version_compare($oRequest->extension_version, '3.2.251012', '<')) {
				$sToSign = json_encode($this->messages);
			}
			else {
				// Explicitly set. It will be set to the real signature later.
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
