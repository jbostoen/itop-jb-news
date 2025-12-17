<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.251212
 */

namespace JeffreyBostoenExtensions\News\v210;

use JeffreyBostoenExtensions\ServerCommunication\v210\HttpResponse;
use JeffreyBostoenExtensions\News\v200\MessagesTrait;

/**
* Class HttpResponseGetMessagesForInstance. A base class for HTTP responses for getting messages for an instance.
*/
class HttpResponseGetMessagesForInstance extends HttpResponse {

	use MessagesTrait;

	
}
