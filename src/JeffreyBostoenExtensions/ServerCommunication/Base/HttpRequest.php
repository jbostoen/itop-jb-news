<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\Base;

use JeffreyBostoenExtensions\ServerCommunication\{
    Helper,
    eApiVersion,
    eCryptographyKeyType,
    eCryptographyLibrary,
    eOperation,
    eOperationMode,
    ServerWorker,
    ServerWorkerTrait
};

// iTop internals.
use utils;

// Generic.
use Exception;
use stdClass;

/**
* Class HttpRequest. A standard HTTP request payload.
*
* This class serves different purposes:
*
*
* Client:  
*  Used to build the request.
*
* Server:  
*  A wrapper, so the same function/method name (e.g. GetCryptoLib) can be used to derive data  
*  from the original request (which used to have "encryption_library" and now "crypto_lib").
*
* This class extends the stdClass, so it can show any properties.
*/
class HttpRequest extends stdClass {

    use ServerWorkerTrait;

    /**
     * @var string $api_version The API version the client is using.
     * */
    public string $api_version = eApiVersion::v1_0_0->value;

    /**
     * @var string $operation The requested operation.
     **/
    public string $operation;

    /**
     * @var string $instance_hash The instance hash of the iTop instance.
     * */
    public string $instance_hash;

    /** 
     * @var string $instance_hash2 The instance hash 2 of the iTop instance.
     * */
    public string $instance_hash2;

    /**
     * @var string $db_uid The database UID of the iTop instance.
     * */
    public string $db_uid;

    /**
     * @var string $env The environment of the iTop instance (default: 'production').
     * */
    public string $env;
    
    /**
     * @var string $app_name The application name of the iTop instance (default: 'iTop').
     * */
    public string $app_name;
    
    /**
     * @var string $app_version The application version of the iTop instance.
     */
    public string $app_version;

    /**
     * @var string $mode The mode ('cron', 'mitm')
     **/
    public string $mode;



    
    /**
     * @inheritDoc
     * 
     * @param ServerWorker|null $oWorker The worker handling this request.
     */
    public function __construct(?ServerWorker $oWorker = null) {

        $this->SetWorker($oWorker);
        $oWorker->SetHttpRequest($this);

    }

	
	/**
	 * Sets the payload values based on data sent to the server.
     * 
     * Sets the API version and operation (derived from URL parameters).
     * 
     * For API version 1.1.0 and higher:
	 * - Perform base64 decoding on the payload.
	 * - If the result is not yet a JSON structure yet: Attempt to decrypt.
	 * - Decode the JSON structure.
     * 
	 */
	public function ReadUserProvidedValues() : void {
        
        $this->api_version = utils::ReadParam('api_version', '', false, 'raw_data');
        $this->operation = utils::ReadParam('operation', '', false, 'raw_data');
		
        foreach($this->GetPayload() as $sKey => $mValue) {

            Helper::Trace('%1$s = %2$s', $sKey, $mValue);
            $this->$sKey = $mValue;
            
        }
		
	}


    /**
     * Performs some validations.
     *
     * @return void
     * 
     * @throws Exception
     */
    public function Validate() : void {
        
        // - Validate whether a hash is present.

            if($this->instance_hash == '' || $this->instance_hash2 == '') {
                    
                throw new Exception('Error: Empty instance hash.');
                
            }
        
    }


    /**
     * Returns the operation mode.
     *
     * @return eOperationMode|null
     */
    public function GetOperationMode() : ?eOperationMode {

        return null;

    }

    /**
     * Returns the requested cryptography library.
     *
     * @return null
     */
    public function GetCryptoLib() : ?eCryptographyLibrary {

        return null;

    }

    /**
     * Validates whether the encryption library is valid.
     *
     * @return void
     * @throws Exception
     */
    public function ValidateCryptoLib() : void {
        
        $bPhpSodiumEnabled = function_exists('sodium_crypto_sign_detached');

        /** @var eCryptographyLibrary $eClientCryptoLib The encryption library, as specified by the client. */
        $eClientCryptoLib = $this->GetCryptoLib();

        if($eClientCryptoLib === null) {

            throw new Exception(sprintf('The client requested an unsupported cryptography library: "%1$s".', $this->GetCryptoLib));

        } 
        elseif($eClientCryptoLib == eCryptographyLibrary::Sodium && !$bPhpSodiumEnabled) {
            
            // Stricter implementation: Do not even fall back to non-encrypted version.
            throw new Exception('The client requested Sodium cryptography, but this server does not have Sodium enabled.');

        }
        

    }


}
