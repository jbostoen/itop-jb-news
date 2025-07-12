
## Configuration


In iTop's configuration file, these settings are available:

```
	'jb-news' => array(
		
		// Whether the module is enabled or not.
		'enabled' => true, 
		
		// Acts as client.
		'client' => true, 
		
		// Interval in minutes before checking the remote news source(s) (if the cron job is running).
		'frequency' => 60,
		
		// Acts as server.
		'server' => false,
		
		// Time interval in milliseconds before checking again (frontend) if a user has new messages.
		'ttl' => 3600,

		// OQL which should return User objects. Allows the administrator to restrict who sees the news sources provided by this extension.
		// Note: if messages were obtained before, they may be present in the localStorage of the browser; and still be displayed for a brief time.
		'oql_target_users' => 'SELECT User',
		
		// Path to private key (only required if acting as a news source/server).
		'private_key_file' => '/some/path/sodium_priv.key',
		
		// Path to Sodium keys (only required if acting as a news source/server)
		'sodium' => [
			'private_key_crypto_sign' => '/somepath/sodium_sign_priv.key',
			'private_key_crypto_box' => '/somepath/sodium_box_priv.key',
			'public_key_crypto_box' => '/somepath/sodium_box_pub.key',
		]

        // Experimental: Specify names (one string = one name) of news sources that should not be checked.
        'disabled_sources' => [],

        // Whether to disable sending statistics.
        'disable_reporting' => false,

        // Whether SSL/TLS certificates of remote news servers must be verified.
        'curl_ssl_verify' => true,

        // Whether tracing is enabled (only do so while troubleshooting).
        'trace_log' => false,

	),
```
