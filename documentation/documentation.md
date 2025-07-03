# Documentation


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

## Privacy / GDPR

* Connections will be made from the iTop server to the news source server.
* Be aware: upon longer failure, this may be shifted to one of the iTop back-office users. Communicate this to your users.

