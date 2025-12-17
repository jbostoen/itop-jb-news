<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\v210;

use JeffreyBostoenExtensions\ServerCommunication\{
    Helper,
    eCryptographyLibrary,
    eOperationMode,
};
use JeffreyBostoenExtensions\ServerCommunication\Base\HttpRequest as Base;

// Generic.
use Exception;

/**
* Class HttpRequest. A standard HTTP request.
*/
class HttpRequest extends Base {
    
    /** @var string $crypto_lib The encryption library of the iTop instance. (default: 'Sodium') */
    public string $crypto_lib;

    /** @var string $mode The mode (see eMode) */
    public string $mode;

    /** @var string $client_token A token to provide to the third-party external server for instance identification. */
    public string $client_token;

    /** @var string $new_client_token The token to provide to the third-party external server; and which should become the new client token once confirmed by the server. */
    public string $new_client_token;

    /** @var string $extension_version The version of the extension that is making the request. (So: client version). */
    public string $extension_version;


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
                
                throw new Exception('Error: Invalid or missing "token" in payload.');
                
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
    
    
    /**
     * @inheritDoc
     */
    public function GetOperationMode(): ?eOperationMode {

        return eOperationMode::tryFrom($this->mode);

    }
    
}
