<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication;


use JeffreyBostoenExtensions\ServerCommunication\{
	Base\HttpRequest,
	Base\HttpResponse,
	Extensions\iServerExtension,
	Extensions\ServerExtension,
	v100\HttpResponse as HttpResponse_100,
	v110\HttpResponse as HttpResponse_110,
	v200\HttpResponse as HttpResponse_200,
	v210\HttpResponse as HttpResponse_210
};

// iTop classes.
use MetaModel;
use utils;

// Generic.
use Exception;
use stdClass;

/**
 * Enum eCryptographyKeyType. Key types.
 * 
 * For good hygiene, it's recommended to use different key pairs for different purposes.
 * 
 * Boxing:  
 * Sealed boxes are designed to anonymously send messages to a refcipient given their public key.
 * Only the recipient can decrypt these messages using their private key. While the recipient can verify the integrity of the message, they cannot verify the identity of the sender.
 * 
 * Signing:
 * Signing is used to generate a digital signature for a message using a private key, which can be verified by anyone processing the corresponding public key.
 */
enum eCryptographyKeyType : string {
	
	case PrivateKeyCryptoSign = 'private_key_crypto_sign';
	case PrivateKeyCryptoBox = 'private_key_crypto_box';
	case PublicKeyCryptoBox = 'public_key_crypto_box';
}



/**
 * Class ServerWorker. An external server that processes incoming HTTP requests and lists all applicable messages for the client (requester).
 */
class ServerWorker {

	/**
	 * @var HttpRequest $oHttpRequest The incoming HTTP request.
	 */
	private $oHttpRequest;

	/**
	 * @var HttpResponse $oHttpResponse The outgoing HTTP response.
	 */
	private $oHttpResponse;

	/**
	 * @var stdClass $oPayload The decoded payload.
	 */
	private $oPayload;

	/**
	 * @var object&iServer[] $aExtensions List of third-party server extensions.
	 */
	private $aExtensions = [];

	
	/**
	 * @var eApiVersion|null $eApiVersion The client's API version.
	 */
	private $eClientApiVersion;
	
	/**
	 * @var eOperation|null $eClientOperation The client's operation.
	 */
	private $eClientOperation;

	/**
	 * @var eOperationMode|null $eClientOperationMode The client's operation mode.
	 */
	private $eClientOperationMode;

	/**
	 * Processes an incoming HTTP request from a client.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function ProcessIncomingRequest() : void {
		
		Helper::Trace('Server received request from client.');

		// - This extension might simply not be enabled.
			
			$bExtensionEnabled = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'enabled', false);
			if(!$bExtensionEnabled) {

				throw new Exception('Server Communication extension not enabled.');

			}

		// - The "server" functionality might simply not be enabled.

			$bServerEnabled = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'server', false);
			if(!$bServerEnabled) {
				
				throw new Exception('Server component not enabled.');
				
			}

        // - Validate API version.

			$sApiVersion = utils::ReadParam('api_version', '', false, 'raw_data');
			$this->eClientApiVersion = eApiVersion::tryFrom($sApiVersion);

			if($this->eClientApiVersion === null) {
				throw new Exception(Helper::Trace('Invalid API version: "%1$s".', $sApiVersion));
			}

		// - Decode payload.

			if(!$this->eClientApiVersion == eApiVersion::v1_0_0) {
				$this->DecodePayload();
			}

		// - Validate operation.


			if($this->eClientApiVersion == eApiVersion::v2_1_0) {

				$sOperation = $this->GetPayload()->operation;

			}
			else {

				$sOperation = utils::ReadParam('operation', '', false, 'raw_data');

			}
					
			$this->eClientOperation = eOperation::tryFrom($sOperation);

			if($this->eClientOperation === null) {
				throw new Exception(Helper::Trace('Invalid operation: "%1$s".', $sOperation));
			}

		// - List the server extensions.

			$this->aExtensions = array_map(function(string $sClass) {
				return new $sClass($this);
			}, Helper::GetImplementations(iServerExtension::class));

			// Now that they're instantiated, only keep the one(s) that can handle the current request.

			$this->aExtensions = array_filter($this->aExtensions, function(ServerExtension $oExtension) {

				return $oExtension->SupportsClient();

			});

			if(count($this->aExtensions) == 0) {
				throw new Exception(Helper::Trace('No server extension available to support the requested API version "%1$s" and operation "%2$s"',
					$sApiVersion,
					$sOperation
				));
			}

		// - Order the extensions.

			usort($this->aExtensions, function(ServerExtension $oExtA, ServerExtension $oExtB) {
				return strcmp($oExtA->GetRank(), $oExtB->GetRank());
			});

		// - Let the extensions process everything.
			
			foreach($this->aExtensions as $oExtension) {
				Helper::Trace('Process - Extension: %1$s', $oExtension::class);
				$oExtension->Process($this);
			}
			
			$oResponse = $this->GetHttpResponse();

			if(!$oResponse instanceof HttpResponse) {
				
				Helper::Trace('No extension generated a response.');

				// Fallback: Some operations just need to return a basic response.
				$oResponse = match($this->GetClientApiVersion()) {
					eApiVersion::v1_0_0 => new HttpResponse_100($this),
					eApiVersion::v1_1_0 => new HttpResponse_110($this),
					eApiVersion::v2_0_0 => new HttpResponse_200($this),
					eApiVersion::v2_1_0 => new HttpResponse_210($this),
				};

			}

		// - Sign, if necessary.

			$oResponse->Sign();

		// - If a callback method is specified, wrap the output in a JSONP callback.

			$sOutput = $oResponse->GetOutput();

			$sCallBackMethod = utils::ReadParam('callback', '', false, 'parameter');
			if($sCallBackMethod != '') {
				$sOutput = $sCallBackMethod.'('.$sOutput.');';
			
			}

			Helper::Trace('Response:');
			Helper::Trace($sOutput);

		// - Print the output.

			$oPage = new JsonPage();
			$oPage->output($sOutput);

	}


	/**
	 * Sets the HTTP request that is currently being handled.
	 *
	 * @return HttpRequest|null
	 */
	public function SetHttpRequest(?HttpRequest $oRequest) : void {

		$this->oHttpRequest = $oRequest;

	}


	/**
	 * Returns the HTTP request that is currently being handled.
	 *
	 * @return HttpRequest|null
	 */
	public function GetHttpRequest() : ?HttpRequest {

		return $this->oHttpRequest;

	}


	/**
	 * Sets the HTTP response that is currently being generated.
	 *
	 * @return HttpRequest|null
	 */
	public function SetHttpResponse(?HttpResponse $oResponse) : void {

		$this->oHttpResponse = $oResponse;

	}


	/**
	 * Returns the HTTP response that is currently being generated.
	 *
	 * @return HttpResponse|null
	 */
	public function GetHttpResponse() : ?HttpResponse {

		return $this->oHttpResponse;

	}

	
	/**
	 * Returns the client API version.
	 *
	 * @return eApiVersion|null
	 */
	public function GetClientApiVersion() : ?eApiVersion {

		return $this->eClientApiVersion;

	}
	

	/**
	 * Returns the requested operation.
	 *
	 * @return eOperation|null
	 */
	public function GetClientOperation() : ?eOperation {

		return $this->eClientOperation;

	}


	/**
	 * Returns the client's operation mode.
	 *
	 * @return eOperationMode|null
	 */
	public function GetClientOperationMode() : ?eOperationMode {

		return $this->eClientOperationMode;

	}


	/**
	 * Returns the server extensions (instances).
	 *
	 * @return object&iServer[]
	 */
	public function GetExtensions() : array {

		return $this->aExtensions;

	}


	/**
	 * Decodes the payload, if set.
	 *
	 * @return void
	 */
	public function DecodePayload() : void {

	
        $sPayload = utils::ReadParam('payload', '', false, 'raw_data');

		if(trim($sPayload) == '') {
			Helper::Trace('Payload is empty.');
			throw new Exception('Payload is empty.');
		}

		Helper::Trace('Received payload: %1$s', $sPayload);
		
		// Payloads can be either encrypted or unencrypted (Sodium not available on the iTop instance that is making the request).
		// Either way, they are base64 encoded.
		$sPayload = base64_decode($sPayload);
		
		// Doesn't seem regular JSON yet; try unsealing.
        // As of now, the only supported encryption method is Sodium.
        // If other encryption methods are added in the future, this logic will need to be updated.
		if(substr($sPayload, 0, 1) !== '{') {

			Helper::Trace('No JSON yet, try unsealing the payload.');
			
			$sPrivateKey = Helper::GetKeySodium(eCryptographyKeyType::PrivateKeyCryptoBox);
			// The public key must match the one defined in for this external server (iExternalServer).
			$sPublicKey = Helper::GetKeySodium(eCryptographyKeyType::PublicKeyCryptoBox);

			$sPayload = sodium_crypto_box_seal_open($sPayload, sodium_crypto_box_keypair_from_secretkey_and_publickey($sPrivateKey, $sPublicKey));
			
		}
		
		$this->oPayload = json_decode($sPayload);

		if($this->oPayload === null) {

            $sErrorMsg = 'Unable to decode the payload. This is probably not JSON.';
			Helper::Trace($sErrorMsg);
			throw new Exception($sErrorMsg);

		}

		Helper::Trace('Payload: %1$s', json_encode($this->oPayload, JSON_PRETTY_PRINT));

	}


	/**
	 * The payload.
	 *
	 * @return stdClass|null
	 */
	public function GetPayload() : ?stdClass {

		return $this->oPayload;

	}

}
