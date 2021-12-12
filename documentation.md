# Documentation

## Configuration


In iTop configuration file, these settings are available:

```
	'jb-news-client' => array(
		// Module specific settings go here, if any
		'enabled' => true, // Whether the module is enabled or not
		'client' => true, // Acts as client
		'frequency' => 60, // Check the remote news source every N minutes (once the cron job is running)
		'server' => false, // Acts as server
		'source_url' => 'https://127.0.0.1:8182/test-newsroom/demo.php', // Remote news source
		'ttl' => 3600, // How long before checking again (frontend) if a user has new messages
	),
```

## How it works

* a background task retrieves the third party newsroom messages from a remote news source
  * if a new third party newsroom message has been published:
    * a copy is stored on the local iTop instance
    * a record is created for every user to keep track of "unread" messages
  * if a third party newsroom message is no longer returned by the remote news source:
    * the copy will be deleted from the local iTop instance. (use case: mistakes)
  * if a third party newsroom message has changed on the remote source:
    * the local copy will be updated
	* it will NOT be marked as "unread" again (as it might simply be fixing a typo)
* on the local instance, when the newsroom checks for messages, it does so against its local data.
  * if a newsroom message has been displayed, the record that states there's an unread message for the current user will be deleted
  * language of the message is chosen in this preference order:
    * same as user's language
	* English
	* first found
  
## News server recommendations

* Publish an English translation for each message.






