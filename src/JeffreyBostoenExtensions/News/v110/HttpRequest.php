<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v110;

use JeffreyBostoenExtensions\News\Base\HttpRequest as Base;
use JeffreyBostoenExtensions\News\eCryptographyLibrary;

// Generic.
use stdClass;

/**
* Class HttpRequest. A standard HTTP request.
*/
class HttpRequest extends Base {
    
    /**
     * @var string $encryption_library The encryption library of the iTop instance. ('Sodium' or 'none')
     * */
    public string $encryption_library;

    /** 
     * @var string $app_root_url The app root URL
     * */
    public string $app_root_url;


    /**
     * Returns a HttpRequestPayload built from the connected client.
     *
     * @return HttpRequest
     */
    public static function BuildFromConnectedClient() : HttpRequest {

        $oRequest = new HttpRequest();
        $oRequest->ReadUserProvidedValues();
        
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
        
    }
    
    /**
     * Returns the requested cryptography library.
     *
     * @return eCryptographyLibrary|null
     */
    public function GetCryptoLib() : eCryptographyLibrary|null {

        return eCryptographyLibrary::tryFrom(strtolower($this->encryption_library));

    }



}
