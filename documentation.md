# Documentation

## Configuration


In iTop configuration file, these settings are available:

```
	'jb-news-client' => array(
		// Module specific settings go here, if any
		'enabled' => true, // Whether the module is enabled or not
		'client' => true, // Acts as client
		'frequency' => 60, // Interval in minutes before checking the remote news source(s) (if the cron job is running)
		'server' => false, // Acts as server
		'ttl' => 3600, // Time interval in milliseconds before checking again (frontend) if a user has new messages
	),
```

## How it works

* A background task retrieves the third party newsroom messages from a remote news source.
  * If a new third party newsroom message has been published:
    * A copy is stored on the local iTop instance
    * A record is created for every user to keep track of the read status of the message - no matter whether the user is within the target audience or not.
  * If a third party newsroom message is no longer returned by the remote news source:
    * The copy will be deleted from the local iTop instance. (use case: mistakes)
  * If a third party newsroom message has changed on the remote source:
    * The local copy will be updated
	* The message will NOT be marked as "unread" again (as it might simply be fixing a typo)
* On the local instance, when the newsroom checks for messages, it does so against its local data.
  * If a newsroom message has been displayed, the "read time" attribute will be updated for the record linking the user and the message.
  * Language of the message is chosen in this preference order:
    * Same as user's language
	* English
	* First found language
  
## News server recommendations

* Publish an English translation for each message.



## Evolution of the API

Where possible, the news server API will try to respond with a backward compatible response.

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
