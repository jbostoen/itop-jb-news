<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v110;

use JeffreyBostoenExtensions\News\Base\HttpResponse as Base;

/**
 * Class HttpResponse. Represents an outgoing response to a client. (API v1.1.0). 
 */
class HttpResponse extends Base {

    /**
     * @var string $encryption_library The crypto library used to sign the response.
     */
    public string $encryption_library;

}