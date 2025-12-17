<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.221223
 *
 */

namespace JeffreyBostoenExtensions\ServerCommunication\Extensions;

use JeffreyBostoenExtensions\ServerCommunication\{
	eApiVersion,
	eOperation,
	eOperationMode,
	ServerWorker,
	ServerWorkerTrait,
};

/**
 * Interface iServerExtension. Interface to implement server-side actions when a client connects.
 */
interface iServerExtension {
	
    /**
     * Lists the supported API version(s).
     *
     * @return eApiVersion[]
     */
    public function GetSupportedApiVersions() : array;

    /**
     * Lists the supported operation(s).
     *
     * @return eOperation[]
     */
    public function GetSupportedOperations() : array;

	/**
	 * Gets the rank. Lower rank = executed first.
	 *
	 * @return int
	 */
	public function GetRank() : int;


	/**
	 * A hook that allows processing the incoming request.
	 *
	 * @return void
	 */
	public function Process() : void;


	/**
	 * Returns the server worker handling the request.
	 * 
	 * This provides access (once set) to the current HTTP request and response.
	 *
	 * @return ServerWorker
	 */
	public function GetWorker() : ServerWorker;
	

	/**
	 * Whether the server extension supports what the client requested.
	 *
	 * @return bool
	 */
	public function SupportsClient() : bool;
	

}

/**
 * Class ServerExtension. Defines custom server actions.
 * 
 * By design, it supports all API versions - but no operations.
 */
class ServerExtension implements iServerExtension {

	use ServerWorkerTrait;

	/**
	 * @inheritDoc
	 */
	public function GetRank() : int {
		return 0;
	}

	/**
	 * @inheritDoc
	 * 
	 * The base class supports all API versions.
	 */
	public function GetSupportedApiVersions() : array {

		return [
			eApiVersion::v1_0_0,
			eApiVersion::v1_1_0,
			eApiVersion::v2_0_0,
			eApiVersion::v2_1_0,
		];

	}


	/**
	 * @inheritDoc
	 * 
	 * The base class does not support any operations.
	 */
	public function GetSupportedOperations() : array {

		return [];

	}


	/**
	 * @inheritDoc
	 * 
	 * Checks whether the requested API version and operation are supported.
	 *
	 * @return boolean
	 */
	public function SupportsClient() : bool {

		$bValidApi = in_array($this->GetWorker()->GetClientApiVersion(), $this->GetSupportedApiVersions());
		$bValidOperation = in_array($this->GetWorker()->GetClientOperation(), $this->GetSupportedOperations());
		
		return $bValidApi && $bValidOperation;

	}


	/**
	 * @inheritDoc
	 */ 
	public function Process() : void {
				

	}


}
