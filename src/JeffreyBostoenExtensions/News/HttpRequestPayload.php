<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250909
 */

namespace JeffreyBostoenExtensions\News;


/**
* Class HttpRequestPayload. A standard payload.
*/
class HttpRequestPayload {

    /** @var string $instance_hash The instance hash of the iTop instance. */
    public string $instance_hash = '';

    /** @var string $instance_hash2 The instance hash 2 of the iTop instance. */
    public string $instance_hash2 = '';

    /** @var string $db_uid The database UID of the iTop instance. */
    public string $db_uid = '';

    /** @var string $env The environment of the iTop instance (default: 'production'). */
    public string $env = '';
    
    /** @var string $app_name The application name of the iTop instance (default: 'iTop'). */
    public string $app_name = '';
    
    /** @var string $app_version The application version f the iTop instance. */
    public string $app_version = '';
    
    /** @var string $crypto_lib The encryption library of the iTop instance. (default: 'Sodium') */
    public string $crypto_lib = '';

    /** @var string $api_version The API version the client is using. */
    public string $api_version = '';

    /** @var string $operation The requested operation. */
    public string $operation = '';

    /** @var string $mode The mode ('cron', 'mitm') */
    public string $mode = '';

    /** @var string $token A token that should be provided to the third-party news source; and should be refreshed every time by the news source and stored. */
    public string $token = '';

    /**  @var string $app_root_url The app root URL */
    public string $app_root_url = '';

    
    private $dynamicProps = [];  // container for dynamic properties

    // Magic method to handle dynamic setting of properties
    public function __set($name, $value) {
        // Store the dynamic property in the container
        $this->dynamicProps[$name] = $value;
    }

    // Magic method to handle dynamic getting of properties
    public function __get($name) {
        // Return dynamic property if it exists
        return $this->dynamicProps[$name] ?? null;
    }

    // Optional: Check if dynamic property exists
    public function __isset($name) {
        return isset($this->dynamicProps[$name]);
    }

    // Optional: Unset dynamic property
    public function __unset($name) {
        unset($this->dynamicProps[$name]);
    }

    // For accessing regular properties
    public function getExistingProp() {
        return $this->existingProp;
    }

}