<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\v110;

use JeffreyBostoenExtensions\ServerCommunication\{
    eCryptographyLibrary,
    eOperationMode,
    Base\HttpRequest as Base,
};

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

    
    /**
     * @inheritDoc
     */
    public function GetOperationMode(): ?eOperationMode {

        return (isset($_POST['operation']) ? eOperationMode::Cron : eOperationMode::Mitm);

    }



}
