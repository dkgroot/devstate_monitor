Requirements
------------
- php 5.3 or higher

Installation
------------
```
./composer.phar install -o
```

Upgrade
------------
```
./composer.phar upgrade -o
```

Dependencies
------------
- composer
- psr/log
- dkgroot/pami

Running the example:
--------------------
Terminal 1: Run the example devstate monitor
```
# ./devstate_monitor.php amihost port amiuser amipass
./devstate_monitor.php localhost 5038 admin secret
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

Other example files:
- Paging.php
- ListenDaemon.php
