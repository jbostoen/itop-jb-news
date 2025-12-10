<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\Base;

use JeffreyBostoenExtensions\News\{
    DynamicPropertiesTrait,
    Helper,
    eApiVersion,
    eCryptographyKeyType,
    eCryptographyLibrary,
    eOperation,
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
     * @var string $app_version The application version f the iTop instance.
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

        $sPayload = utils::ReadParam('payload', '', false, 'raw_data');

		if(trim($sPayload) == '') {
			Helper::Trace('Payload is empty.');
			throw new Exception('Payload is empty.');
		}

		Helper::Trace('Received payload: %1$s', $sPayload);
		
		// Payloads can be either encrypted or unencrypted (Sodium not available on the iTop instance that is requesting news messages).
		// Either way, they are base64 encoded.
		$sPayload = base64_decode($sPayload);
		
		// Doesn't seem regular JSON yet; try unsealing.
        // As of now, the only supported encryption method is Sodium.
        // If other encryption methods are added in the future, this logic will need to be updated.
		if(substr($sPayload, 0, 1) !== '{') {

			Helper::Trace('No JSON yet, try unsealing the payload.');
			
			$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoBox);
			// The public key must match the one defined in the "Source" (iSource) for this news provider.
			$sPublicKey = Helper::GetKeySodium(eCryptographyKeyType::PublicKeyCryptoBox);

			$sPayload = sodium_crypto_box_seal_open($sPayload, sodium_crypto_box_keypair_from_secretkey_and_publickey($sPrivateKey, $sPublicKey));
			
		}
		
		$oUserPayload = json_decode($sPayload);

		if($oUserPayload === null) {

            $sErrorMsg = 'Unable to decode the payload. This is probably not JSON.';
			Helper::Trace($sErrorMsg);
			throw new Exception($sErrorMsg);

		}

		Helper::Trace('User payload: %1$s', json_encode($oUserPayload, JSON_PRETTY_PRINT));
		
        foreach($oUserPayload as $sKey => $mValue) {

            Helper::Trace('%1$s = %2$s', $sKey, $mValue);
            $this->$sKey = $mValue;
            
        }
		
	}

    


    /**
     * Returns the requested API version.
     *
     * @return eApiVersion|null
     */
    public function GetApiVersion() : eApiVersion|null {

        return eApiVersion::tryFrom(strtolower($this->api_version));

    }

    /**
     * Returns the requested operation.
     *
     * @return eOperation|null
     */
    public function GetOperation() : eOperation|null {

        return eOperation::tryFrom($this->operation);

    }

    /**
     * Returns a new API-specific HttpRequest instance based on client connection parameters.
     *
     * @param ServerWorker $oWorker The worker handling this request.
     * 
     * @return HttpRequest
     * 
     * @throws Exception
     */
    public static function BuildFromClientConnection(ServerWorker $oWorker) : HttpRequest {

        // - Validate API version.

            $sApiVersion = utils::ReadParam('api_version', '', false, 'raw_data');
            $eApiVersion = eApiVersion::tryFrom($sApiVersion);

            if($eApiVersion === null) {
                Helper::Trace('Invalid API version: "%1$s".', $sApiVersion);
                throw new Exception(sprintf('Invalid API version: "%1$s".', $sApiVersion));
            }

        // - Validate operation.

            $sOperation = utils::ReadParam('operation', '', false, 'raw_data');
            $eOperation = eOperation::tryFrom($sOperation);

            if($eOperation === null) {
                Helper::Trace('Invalid operation: "%1$s".', $sOperation);
                throw new Exception(sprintf('Invalid operation: "%1$s".', $sOperation));
            }

        // - Create API-specific HttpRequest instance.

            $sClass = match($eApiVersion) {
                eApiVersion::v1_0_0 => \JeffreyBostoenExtensions\News\v100\HttpRequest::class,
                eApiVersion::v1_1_0 => \JeffreyBostoenExtensions\News\v110\HttpRequest::class,
                eApiVersion::v2_0_0 => \JeffreyBostoenExtensions\News\v200\HttpRequest::class,
            };

            $oRequest = new $sClass();
            $oRequest->SetWorker($oWorker);
            $oRequest->ReadUserProvidedValues();

        // - Validate.

            $oRequest->Validate();

        return $oRequest;

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
     * Returns the requested cryptography library.
     *
     * @return null
     */
    public function GetCryptoLib() : eCryptographyLibrary|null {

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


    /**
     * Builds a response, based on the requested operation and API version.
     * 
     * @return HttpResponse
     */
    public function BuildResponse() : HttpResponse {

        $oResponse = match($this->GetOperation()) {
            eOperation::GetMessagesForInstance => $this->BuildResponseForGetMessagesForInstance(),
            eOperation::ReportReadStatistics => $this->BuildResponseForReportReadStatistics(),

        };

        return $oResponse;
        
    }


    /**
     * Builds a response for the operation that retrieves messages for the instance.
     * 
     * @return HttpResponseGetMessagesForInstance
     */
    public function BuildResponseForGetMessagesForInstance() : HttpResponse {

        $sClass = match($this->GetApiVersion()) {
            eApiVersion::v1_0_0 => \JeffreyBostoenExtensions\News\v100\HttpResponseGetMessagesForInstance::class,
            eApiVersion::v1_1_0 => \JeffreyBostoenExtensions\News\v110\HttpResponseGetMessagesForInstance::class,
            eApiVersion::v2_0_0 => \JeffreyBostoenExtensions\News\v200\HttpResponseGetMessagesForInstance::class,
        };

        return new $sClass($this->oWorker);

    }

    /**
     * Builds a response for the operation that reports read statistics for an instance.
     * 
     * @return HttpResponse
     */
    public function BuildResponseForReportReadStatistics() : HttpResponse {

        $sClass = match($this->GetApiVersion()) {
            eApiVersion::v1_0_0 => \JeffreyBostoenExtensions\News\v100\HttpResponse::class,
            eApiVersion::v1_1_0 => \JeffreyBostoenExtensions\News\v110\HttpResponse::class,
            eApiVersion::v2_0_0 => \JeffreyBostoenExtensions\News\v200\HttpResponse::class,
        };

        return new $sClass($this->oWorker);

    }


}