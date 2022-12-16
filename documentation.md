# Documentation

## Configuration


In iTop's configuration file, these settings are available:

```
	'jb-news-client' => array(
		
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
		
		// Path to private key
		'private_key_file' => '/some/path/sodium_priv.key',
	),
```

## How it works

* A background task retrieves the third party newsroom messages from a remote news source.
  * Step 1: The first HTTP POST request sends a limited set of data (e.g. API and some identifiers) to the news source server in order to fetching the news messages.
    * If a new third party newsroom message has been published:
      * A copy is stored on the local iTop instance.
    * If a third party newsroom message is no longer returned by the remote news source:
      * The copy will be deleted from the local iTop instance. (use case: mistakes)
    * If a third party newsroom message has changed on the remote source:
      * The local copy will be updated.
	* The "read status" of a user for a message will not be reset (as it might simply be a fixed typo in the original message).
  * Step 2: A second HTTP POST request is made to the news source, containing some statistical info (read status).
  * If Sodium is available, the messages can be verified using a known public Sodium key. This is a security-measure to prevent man-in-the-middle-attacks.
 
* On the local instance, when the front-end newsroom checks for messages, it does so against its local data.
  * If a newsroom message has been displayed and the user has clicked on a single message (and is redirected to the URL), a "read status" will be created for this user.
  * If the user has displayed the page with all the messages for this news source, this will also trigger the creation of "read status" objects for this user for each message.
  * Language of the message is chosen in this preference order:
    * Same as user's language
	* English
	* First found language
  
## News server recommendations

* Publish an English translation for each message.



## Evolution of the API

Where possible, the news server API will try to respond with a backward compatible response.  
Servers should not provide a newer response, so there's no need for the client to check what API version the server is using.

### Version 1.1.0

* "target_profiles" has been deprecated and removed.  
  It has been replaced by the more functional "oql" that can be specified to target an audience.
* The API version also contains a 'minor release' version number now, so small changes can be implemented more easily.
* In the server response, the server's API version is also specified. ( `api_version` )
* Introduction of "operation" parameter. Currently two operations are supported: get_messages_for_instance, report_read_statistics


### Version 1.0

This returned a JSON response.

If the request to the endpoint indicated support for Sodium (encryption_library=Sodium), the response was similar to:
```
{
	"encryption_library": "Sodium",
	"messages": [
		{
			"thirdparty_message_id": "jb-20220704-portal",
			"title": "Support portal launched!",
			"icon": {
				"data": "",
				"mimetype": "",
				"filename": ""
			},
			"start_date": "2022-07-04 00:00:00",
			"end_date": null,
			"priority": "1",
			"target_profiles": "Administrator",
			"translations_list": [
				{
					"language": "EN US",
					"title": "Support portal launched!",
					"text": "As of today, there's a support portal where you can find all downloads, tickets, invoices and more!",
					"url": "https://support.jeffreybostoen.be"
				}
			]
		}
	],
	"signature": "85e4ZQKJq-Pe-qH0C9XfOt0AoLKLL-t896ATKqEdMd45xwTIon-DxNaNge8MDqv8SHj7W_JcJnUpXZMorN8tAw=="
}
```

If no encryption was specified, a regular JSON array was returned (the contents of "messages" above).
