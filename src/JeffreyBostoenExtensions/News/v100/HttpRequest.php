<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News\v100;

use JeffreyBostoenExtensions\News\Base\HttpRequest as Base;
use JeffreyBostoenExtensions\News\eCryptographyLibrary;

// iTop internals.
use utils;

/**
* Class HttpRequest. A standard HTTP request.
*/
class HttpRequest extends Base {
    
    /**
     * @var string $encryption_library The encryption library of the iTop instance. ('Sodium' or 'none')
     * */
    public string $encryption_library;

    /**
     * Returns a HttpRequestPayload built from the connected client.
     *
     * @return HttpRequestPayload
     */
    public static function BuildFromConnectedClient() : HttpRequest {

        // Unlike API 1.1.0 onwards, API 1.0.0 does not have a separate payload object.
        $oRequest = new HttpRequest();
        $oRequest->api_version = utils::ReadParam('api_version', '', false, 'raw_data');
        $oRequest->operation = utils::ReadParam('operation', '', false, 'raw_data');
        $oRequest->instance_hash = utils::ReadParam('instance_hash', '', false, 'raw_data');
        $oRequest->instance_hash2 = utils::ReadParam('instance_hash2', '', false, 'raw_data');
        $oRequest->db_uid = utils::ReadParam('db_uid', '', false, 'raw_data');
        $oRequest->env = utils::ReadParam('env', 'production', false, 'raw_data');
        $oRequest->app_name = utils::ReadParam('app_name', '', false, 'raw_data');
        $oRequest->app_version = utils::ReadParam('app_version', '', false, 'raw_data');
        $oRequest->encryption_library = utils::ReadParam('encryption_library', '', false, 'raw_data');
        
        return $oRequest;


    }

    
    /**
     * Returns the requested cryptography library.
     *
     * @return eCryptographyLibrary|null
     */
    public function GetCryptoLib() : eCryptographyLibrary|null {

        return eCryptographyLibrary::tryFrom(strtolower($this->encryption_library));

    }


}