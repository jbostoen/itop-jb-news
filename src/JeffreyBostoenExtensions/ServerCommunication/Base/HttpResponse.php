<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\ServerCommunication\Base;

use JeffreyBostoenExtensions\ServerCommunication\{
    ServerWorker,
    ServerWorkerTrait
};

// Generic.
use stdClass;

/**
* Class HttpResponse. A base class for HTTP responses.
*
* This class extends the stdClass, so it can show any properties.
* 
*/
class HttpResponse extends stdClass {

	use ServerWorkerTrait;

    /**
     * @var string|null $signature The signature of the response.
     */
    public ?string $signature;


    /**
     * @inheritDoc
     * 
     * @param ServerWorker $oWorker The worker handling this response.
     */
    public function __construct(?ServerWorker $oWorker) {

        $this->SetWorker($oWorker);
        $oWorker->SetHttpResponse($this);

    }


	/**
	 * Signs the response, if needed.
	 *
	 * @return void
	 */
	public function Sign() : void {

	}


    /**
     * Returns the output.
     * 
     * Note: This is mainly here to support legacy answers (API 1.0.0, 1.1.0) for news servers.
     *
     * @return string
     */
    public function GetOutput() : string {
        
        return json_encode($this);

    }

}
