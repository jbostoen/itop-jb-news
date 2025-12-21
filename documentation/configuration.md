
## Configuration


In iTop's configuration file, these settings are available:

```
	'jb-news' => array(
		
		// Whether the module is enabled or not.
		'enabled' => true,
		
		// Acts as client.
		'client' => true, 
		
		// Interval in minutes before checking the remote remote server(s) (if the cron job is running).
		'frequency' => 60,
		
		// Acts as server.
		'server' => false,
		
		// Time interval in milliseconds before checking again (frontend) if a user has new messages.
		'ttl' => 3600,

		// OQL which should return User objects. Allows the administrator to restrict who sees the remote servers provided by this extension.
		// Note: if messages were obtained before, they may be present in the localStorage of the browser; and still be displayed for a brief time.
		'oql_target_users' => 'SELECT User',

        // Whether tracing is enabled (only do so while troubleshooting).
        'trace_log' => false,

	),
```
