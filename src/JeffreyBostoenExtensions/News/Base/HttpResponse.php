<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\Base;

use JeffreyBostoenExtensions\News\{
    ServerWorker,
    ServerWorkerTrait
};

// Generic.
use stdClass;

/**
* Class HttpResponse. A base class for HTTP responses.
*/
class HttpResponse extends stdClass {

	use ServerWorkerTrait;

    /**
     * @var array $messages The messages to be sent to the client.
     */
    public array $messages = [];

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

    }


	/**
	 * Signs the response, if needed.
	 *
	 * @return void
	 */
	public function Sign() : void {

	}

}