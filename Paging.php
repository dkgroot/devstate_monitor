#!/usr/bin/php -q
<?php
declare(ticks=1);

# Example use case:
#
# [multicast-paging]
# exten => 400,1,Answer()
# exten => 400,n,System(sudo /var/lib/asterisk/scripts/paging.php start SEPxxxx,SEPyyy,SEPzzz)
# exten => 400,n,NoOp(${SYSTEMSTATUS})
# exten => 400,n,Page(console/dsp&MulticastRTP/basic/239.0.0.1:21000)
# exten => h,1,System(sudo /var/lib/asterisk/scripts/paging.php stop SEPxxxx,SEPyyy,SEPzzz)

require(implode(DIRECTORY_SEPARATOR, array(
    __DIR__,
    'vendor',
    'autoload.php'
)));

use PAMI\Client\Impl\ClientImpl as PamiClient;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Action\ActionMessage;
use PAMI\Listener\IEventListener;
use PAMI\Message\Action\SCCPShowDevicesAction;

date_default_timezone_set('UTC');

require('Paging.cfg');
$running = true;
// Uid/Pwd Verified against AuthenticationURL (cnf.xml), URL is queryied using Basic Authentication and should return the string 'AUTHORIZED'
$device_array = array();
$uri = "RTPRx:Stop";

if (isset($argv[1])) {
    if (strtolower($argv[1]) == "start") {
        $uri = "RTPMRx:" . $options['multicast']['ip'] . ":" . $options['multicast']['port'] . ":100";
    }
}

if (isset($argv[2])) {
       	$device_array = explode(",", $argv[2]);
}

class Pusher
{
	private $options;
	private $uri;
	
	function Pusher($uri) {
		global $options;
		$this->options = $options;
		$this->uri = $uri;
	}

	function push2phone($ip)
	{
		$response = "";
		$auth = base64_encode($this->options['phone']['uid'].":".$this->options['phone']['pwd']);
		$xml = "<CiscoIPPhoneExecute><ExecuteItem Priority=\"0\" URL=\"".$this->uri."\"/></CiscoIPPhoneExecute>";
		$xml = "XML=".urlencode($xml);

		$post = "POST /CGI/Execute HTTP/1.0\r\n";
		$post .= "Host: $ip\r\n";
		$post .= "Authorization: Basic $auth\r\n";
		$post .= "Connection: close\r\n";
		$post .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$post .= "Content-Length: ".strlen($xml)."\r\n\r\n";

		$fp = fsockopen ( $ip, 80, $errno, $errstr, 30);
		if(!$fp) {
			echo "$errstr ($errno)<br>\n"; 
		} else {
			fputs($fp, $post.$xml);
			flush();
			while (!feof($fp))
			{
				$response .= fgets($fp, 128);
				// parse response, throw exception
				flush();
			}
		}
		return $response;
	}
}

function debug($text) {
	global $options;
	if ($options['debug']) {
		echo($text . "\n");
	}
}

$EventBuffer = array();
try {
	//$pamiClient = new PamiClient($pamiClientOptions);
	$pamiClient = new PamiClient($options);

	// create a new Pusher
	$pusher = new Pusher($uri);
	debug('Created new pusher.');

	// Open the connection
	$pamiClient->open();
	debug('Pami Connected.');

	// Send a SCCPShowDevices action via AMI
	$action = new SCCPShowDevicesAction();
	$result = $pamiClient->send($action);
	debug('SCCPShowDevicesAction sent to asterisk.');
	debug('Processing Results...');
	$device_table=$result->getTable('Devices');
	foreach($device_table['Entries'] as $key => $entry) {
		// print_r($entry);
		$devicename = $entry->getKey('mac');
		$connection = $entry->getKey('address');
		$active = $entry->getKey('act') == 1;
		// check if we set a device_array, and only push to those devices
		debug("Checking events: devicename:$devicename, connection:$connection, active:$active");
		
		if ($connection != "--" && !$active && (count($device_array) == 0 || in_array($devicename, $device_array))) {
			// split the connection into ip/port parts
			$ip = substr($connection, 0, strrpos($connection, ":", 1));
			$port = substr(strrchr($connection, ":"), 1);
			
			debug("match found: ip:$ip, port:$port");
			
			// Handle IPv4-Mapped IPv6 Address
			if (strpos($ip, "::ffff:")) {
				$ip = substr($ip, 8, -1);
			}
			
			// check if result is a valid IP-Address
			if (!filter_var($ip, FILTER_VALIDATE_IP, NULL) === false) {
				debug("Pushing Message to devicename:$devicename, ip:$ip\n");
				echo $pusher->push2phone($ip);
			}
		}
	}

	$pamiClient->close();
	debug('Pami disconnected.');
} catch (Exception $e) {
	print "Exception: " . $e->getMessage();
}
exit(0);
?>
