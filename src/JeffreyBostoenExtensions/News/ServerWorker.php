<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News;

use JeffreyBostoenExtensions\News\Base\{
	HttpRequest,
	HttpResponse,
    HttpResponseGetMessagesForInstance
};

// iTop classes.
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use utils;

// Generic.
use Exception;
use stdClass;
use ThirdPartyNewsMessage;

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
 * Interface iServerExtension. Interface to implement server-side actions when a client connects.
 */
interface iServerExtension {
	
	/**
	 * A hook that can execute logic as soon as the incoming HTTP request is received, before any other processing is done.
	 * For example: Keep track of which instances last connected, process custom info that may have been added in the payload.
	 *
	 * @param ServerWorker $oWorker The incoming HTTP request.
	 *
	 * @return void
	 *
	 */
	public function PreProcess(ServerWorker $oWorker) : void;
	
	
	/**
	 * A hook that can execute logic after all other processing is done.
	 *
	 * @param ServerWorker The server worker handling the request.
	 *
	 * @return void
	 *
	 */
	public function PostProcess(ServerWorker $oWorker) : void;


	/**
	 * A hook to allow additional filtering of any messages that may be returned to the client.
	 *
	 * @param ServerWorker The server worker handling the request.
	 * @param DBObjectSet $oSet The object set to filter.
	 *
	 * @return DBObjectSet
	 *
	 */
	public function ProcessMessages(ServerWorker $oWorker, DBObjectSet $oSet) : void;
	
}

/**
 * Class ServerWorker. A news server that processes incoming HTTP requests and lists all applicable messages for the news client (requester).
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
	 * @var object&iServer[] $aExtensions List of third-party server extensions.
	 */
	private $aExtensions = [];
	

	/**
	 * Processes an incoming HTTP request from a news client.
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

				throw new Exception('News extension not enabled.');

			}

		// - The "server" functionality might simply not be enabled.

			$bServerEnabled = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'server', false);
			if(!$bServerEnabled) {
				
				throw new Exception('Server not enabled.');
				
			}
		
		$oRequest = HttpRequest::BuildFromClientConnection($this);
		$this->oHttpRequest = $oRequest;

		// Don't use Combodo's JsonPage. The server response will be JSONP.
		$oPage = new JsonPage();

		$eClientApiVersion = $oRequest->GetApiVersion();
		$eClientCryptoLib = $oRequest->GetCryptoLib();


		$this->aExtensions = array_map(function(string $sClass) {
			return new $sClass;
		}, Helper::GetImplementations(iServerExtension::class));

		// - Execute pre-processors.
			
			foreach($this->aExtensions as $oExtension) {
				$oExtension->PreProcess($this);
			}

		// - Build response.

			$oResponse = $oRequest->BuildResponse();
			$this->oHttpResponse = $oResponse;

		// - Last change to alter response.
			
			foreach($this->aExtensions as $oExtension) {
				$oExtension->PostProcess($this);
			}

		// - Sign, if necessary.

			$oResponse->Sign();

		// - Only for old API versions (newer ones keep the structure):

			if(
				$oResponse instanceof HttpResponseGetMessagesForInstance && 
				$oRequest->GetCryptoLib() == eCryptographyLibrary::None &&
				($oRequest->GetApiVersion() == eApiVersion::v1_0_0 || $oRequest->GetApiVersion() == eApiVersion::v1_1_0)
			) {
				
				$oResponse = $oResponse->messages;

			}

		// - If a callback method is specified, wrap the output in a JSONP callback.

			$sOutput = json_encode($oResponse);

			$sCallBackMethod = utils::ReadParam('callback', '', false, 'parameter');
			if($sCallBackMethod != '') {
				$sOutput = $sCallBackMethod.'('.$sOutput.');';
			
			}

			Helper::Trace('Response:');
			Helper::Trace($sOutput);

		// - Print the output.
			$oPage->output($sOutput);

	}


	/**
	 * Returns the HTTP request that is currently being handled.
	 *
	 * @return HttpRequest
	 */
	public function GetHttpRequest() : HttpRequest {

		return $this->oHttpRequest;

	}


	/**
	 * Returns the HTTP response that is currently being created.
	 *
	 * @return HttpResponse
	 */
	public function GetHttpResponse() : HttpResponse {

		return $this->oHttpResponse;

	}


	/**
	 * Returns the server extensions (instances).
	 *
	 * @return object&iServer[]
	 */
	public function GetExtensions() : array {

		return $this->aExtensions;

	}

}
