Requirements
------------
- php 5.3 or higher

Installation
------------
```
./composer.phar selfupdate
./composer.phar require
```

Running the example:
--------------------
Terminal 1: Run the example devstate monitor
```
# ./example.php amihost port amiuser amipass
./example.php localhost 5038 admin secret
```
Note: Adjust the amihost, port user and password according to your local settings

Terminal 2: Flip Devstate
```
asterisk -rx "devstate change Custom:mystate UNKNOWN"
asterisk -rx "devstate change Custom:mystate NOT_INUSE"
asterisk -rx "devstate change Custom:mystate INUSE"
asterisk -rx "devstate change Custom:mystate BUSY"
asterisk -rx "devstate change Custom:mystate INVALID"
asterisk -rx "devstate change Custom:mystate RINGING"
asterisk -rx "devstate change Custom:mystate RINGINUSE"
asterisk -rx "devstate change Custom:mystate ONHOLD"
asterisk -rx "devstate change Custom:mystate UNAVAILABLE"
```
And see how the monitor reflects this state

