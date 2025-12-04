<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News;

// iTop internals.
use utils;

use Exception;

/**
 * Class HttpRequest. Represents an incoming HTTP request to the news server.
 */
class HttpRequest {

    /**
     * @var eApiVersion $eApiVersion The requested API version.
     */
    private eApiVersion $eApiVersion;

    /**
     * @var eCryptographyLibrary $eCryptoLib The requested cryptography library.
     */
    private eCryptographyLibrary $eCryptoLib;

    /**
     * @var eOperation $eOperation The requested operation.
     */
    private eOperation $eOperation;

    /**
     * @var HttpRequestPayload|null $oPayload The payload.
     */
    private HttpRequestPayload $oPayload;


    /**
     * @inheritDoc
     * @throws Exception
     */
    public function __construct() {
        
		// - Check if the operation is valid.
			
            $sOperation = utils::ReadParam('operation', '', false, 'parameter');

            if(empty($sOperation)) {
                
                throw new Exception('Missing mandatory parameter "operation".');
            }

            $eOperation = eOperation::tryFrom($sOperation);

            if($eOperation === null) {
                throw new Exception(sprintf('Invalid operation: "%1$s".', $sOperation));
            }

            $this->eOperation = $eOperation;

        // - Check if the API version is valid.
        
            /** @var string $sApiVersion The API version as requested by the client. */
            $sApiVersion = utils::ReadParam('api_version', eApiVersion::v1_0_0->value, false, 'raw_data');

            /** @var eApiVersion $eClientApiVersion The API version as requested by the client. */
            $eClientApiVersion = eApiVersion::tryFrom($sApiVersion);
            
            $this->eApiVersion = $eClientApiVersion;

        // - Decode payload.

            // @todo Remove 1.0 when no client uses this anymore.
            if($eClientApiVersion === eApiVersion::v1_0_0) {
                
                /** @var string $sEncryptionLib The encryption library, as specified by the user. */
                $sClientCryptoLib =  utils::ReadParam('encryption_library', 'none', false, 'parameter');
                
                // Create fake payload so it can be processed similar to API version 1.1.0.
                Helper::Trace('Create fake payload for backward compatibility with API version %1$s', eApiVersion::v1_0_0->value);

                $oPayload = new HttpRequestPayload();
                $oPayload->instance_hash = utils::ReadParam('instance_hash', '', false, 'raw_data');
                $oPayload->instance_hash2 = utils::ReadParam('instance_hash2', '', false, 'raw_data');
                $oPayload->db_uid = utils::ReadParam('db_uid', '', false, 'raw_data');
                $oPayload->env = utils::ReadParam('env', 'production', false, 'raw_data');
                $oPayload->app_name = utils::ReadParam('app_name', '', false, 'raw_data');
                $oPayload->app_version = utils::ReadParam('app_version', '', false, 'raw_data');
                $oPayload->crypto_lib = utils::ReadParam('encryption_library', '', false, 'raw_data');
                $oPayload->api_version = utils::ReadParam('api_version', '', false, 'raw_data');            
                
            
            }
            else {
                
                $sPayload = utils::ReadParam('payload', '', false, 'raw_data');
                $oPayload = $this->DecodePayload($sPayload);
                
                /** @var string $sClientCryptoLib The encryption library, as specified by the client. */
                if($eClientApiVersion == eApiVersion::v1_1_0) {
                    $oPayload->crypto_lib = $oPayload->encryption_library;
                }
                
                
            }

            // - Log the unencrypted payload.

                // Helper::Trace('Payload: %1$s', json_encode($oPayload, JSON_PRETTY_PRINT));

            // - Validate whether the given encryption/signing is possible.

                $bPhpSodiumEnabled = function_exists('sodium_crypto_sign_detached');

                /** @var eCryptographyLibrary $eClientCryptoLib The encryption library, as specified by the client. */
                $eClientCryptoLib = eCryptographyLibrary::tryFrom(strtolower($oPayload->crypto_lib));

                if($eClientCryptoLib === null) {

                    throw new Exception(sprintf('The client requested an unsupported cryptography library: "%1$s".', $oPayload->crypto_lib));

                } 
                elseif($eClientCryptoLib == eCryptographyLibrary::Sodium && !$bPhpSodiumEnabled) {
                    
                    // Stricter implementation: Do not even fall back to non-encrypted version.
                    throw new Exception('The client requested Sodium cryptography, but this server does not have Sodium enabled.');

                }

                $this->eCryptoLib = $eClientCryptoLib;

            // - Validate whether a hash is present.

                if($oPayload->instance_hash == '' || $oPayload->instance_hash2 == '') {
                    
                    throw new Exception('Error: Empty instance hash.');
                    
                }

			// - Validate whether "token" is present.

                if(
                    ($eClientApiVersion !== eApiVersion::v1_0_0 && $eClientApiVersion !== eApiVersion::v1_1_0) &&
                    (
                        !property_exists($oPayload, 'token') || 
                        !is_string($oPayload->token) ||
                        strlen($oPayload->token) != (Helper::CLIENT_TOKEN_BYTES * 2)
                    )
                ) {
                    
                    throw new Exception('Error: Invalid or missing "token" in payload. This is required for API version "%1$s".', $eClientApiVersion->value);
                    
                }

            // - Finally, set the payload.

                $this->oPayload = $oPayload;
        
    }


    /**
     * Returns the requested API version.
     *
     * @return eApiVersion
     */
    public function GetApiVersion() : eApiVersion {

        return $this->eApiVersion;

    }


    /**
     * Returns the requested cryptography library.
     *
     * @return eCryptographyLibrary
     */
    public function GetCryptoLib() : eCryptographyLibrary {

        return $this->eCryptoLib;

    }


    /**
     * Returns the requested operation.
     *
     * @return eOperation
     */
    public function GetOperation() : eOperation {

        return $this->eOperation;

    }


    /**
     * Returns the payload.
     *
     * @return HttpRequestPayload|null
     */
    public function GetPayload() : HttpRequestPayload|null {

        return $this->oPayload;

    }

	
	/**
	 * Decodes the payload that was sent to the server.
	 * 
	 * 1) Perform base64 decoding on the payload.
	 * 2) If the result is not yet a JSON structure yet: Attempt to decrypt.
	 * 3) Decode the JSON structure.
	 *
	 * @param string $sPayload Payload
	 *
	 * @return HttpRequestPayload
	 */
	public function DecodePayload(string $sPayload) : HttpRequestPayload {
	
		if(trim($sPayload) == '') {
			Helper::Trace('Payload is empty.');
			throw new Exception('Payload is empty.');
		}

		Helper::Trace('Received payload: %1$s', $sPayload);
		
		// Payloads can be either encrypted or unencrypted (Sodium not available on the iTop instance that is requesting news messages).
		// Either way, they are base64 encoded.
		$sPayload = base64_decode($sPayload);
		
		// Doesn't seem regular JSON yet; try unsealing
		if(substr($sPayload, 0, 1) !== '{') {

			Helper::Trace('No JSON yet, try unsealing the payload.');
			
			$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoBox);
			// The public key must match the one defined in the "Source" (iSource) for this news provider.
			$sPublicKey = Helper::GetKeySodium(eCryptographyKeyType::PublicKeyCryptoBox);

			$sPayload = sodium_crypto_box_seal_open($sPayload, sodium_crypto_box_keypair_from_secretkey_and_publickey($sPrivateKey, $sPublicKey));
			
		}
		
		$oPayload = json_decode($sPayload);

		if($oPayload === null) {

			Helper::Trace('Unable to decode the payload. This is probably not JSON.');
			throw new Exception('Unable to decode the payload. This is probably not JSON.');

		}

		Helper::Trace('Payload: %1$s', json_encode($oPayload, JSON_PRETTY_PRINT));
		
        $oConvertedPayload = new HttpRequestPayload();

        foreach($oPayload as $sKey => $mValue) {
            Helper::Trace('%1$s = %2$s', $sKey, $mValue);
            $oConvertedPayload->$sKey = $mValue;
        }

		return $oConvertedPayload;
		
	}




}