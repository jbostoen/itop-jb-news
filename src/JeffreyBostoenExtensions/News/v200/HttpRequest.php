<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v200;

use JeffreyBostoenExtensions\News\{
    Helper,
    eCryptographyLibrary,
};
use JeffreyBostoenExtensions\News\Base\HttpRequest as Base;

// Generic.
use Exception;

/**
* Class HttpRequest. A standard HTTP request.
*/
class HttpRequest extends Base {
    
    /** @var string $crypto_lib The encryption library of the iTop instance. (default: 'Sodium') */
    public string $crypto_lib;

    /** @var string $mode The mode ('cron', 'mitm') */
    public string $mode;

    /** @var string $token A token that should be provided to the third-party news source; and should be refreshed every time by the news source and stored. */
    public string $token;

    /** @var string $extension_version The version of the extension that is making the request. (So: client version). */
    public string $extension_version;

    
    /**
     * Returns a HttpRequest built from the connected client.
     *
     * @return HttpRequest
     */
    public static function BuildFromConnectedClient() : HttpRequest {

        $oRequest = new HttpRequest();
        $oRequest->ReadUserProvidedValues();
        
        $oRequest->Validate();

        return $oRequest;
        
    }


    /**
     * @inheritDoc
     * 
     * Specifically:
     * - Validates whether a valid "token" is present.
     *
     * @return void
     * @throws Exception
     */
    public function Validate() : void {

            parent::Validate();

        // - Validate whether the specified crypto library is valid.

            $this->ValidateCryptoLib();
        
        // - Validate whether "token" is present.

            if(strlen($this->token) != (Helper::CLIENT_TOKEN_BYTES * 2)) {
                
                throw new Exception('Error: Invalid or missing "token" in payload. This is required for API version "%1$s".', $this->GetApiVersion()->value);
                
            }
        
    }


    /**
     * Returns the requested cryptography library.
     *
     * @return eCryptographyLibrary|null
     */
    public function GetCryptoLib() : eCryptographyLibrary|null {

        return eCryptographyLibrary::tryFrom(strtolower($this->crypto_lib));

    }
    
    
}