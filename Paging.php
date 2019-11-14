#!/usr/bin/php
<?php
declare(ticks=1);

require(implode(DIRECTORY_SEPARATOR, array(
    __DIR__,
    'vendor',
    'autoload.php'
)));

use PAMI\Client\Impl\ClientImpl as PamiClient;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Action\ActionMessage;
use PAMI\Listener\IEventListener;
use PAMI\Message\Event\SCCPShowDevicesEvent;
use PAMI\Message\Action\SCCPShowDevicesAction;

date_default_timezone_set('UTC');

$running = true;
// Uid/Pwd Verified against AuthenticationURL (cnf.xml), URL is queryied using Basic Authentication and should return the string 'AUTHORIZED'
$uid = "cisco";
$pwd = "cisco";
$device_array = array();
$uri = "RTPRx:Stop";

if (isset($argv[1])) {
    if (strtolower($argv[1]) == "start") {
        $uri = "RTPMRx:239.0.0.1:21000:100";
    }
}

if (isset($argv[2])) {
       	$device_array = explode(",", $argv[2]);
}

$pamiClientOptions = array(
    'log4php.properties' => 'log4php.properties',
    'host' => '127.0.0.1',
    'scheme' => 'tcp://',
    'port' => 5038,
    'username' => 'admin',
    'secret' => 'secret',
    'connect_timeout' => 10000,
    'read_timeout' => 10000
);

class Pusher
{
	private $uri;
	private $uid;
	private $pwd;
	
	function Pusher($uri, $uid, $pwd) {
		$this->uri = $uri;
		$this->uid = $uid;
		$this->pwd = $pwd;
	}

	function push2phone($ip)
	{
		$response = "";
		$auth = base64_encode($this->uid.":".$this->pwd);
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

$EventBuffer = array();

try {
	$pamiClient = new PamiClient($pamiClientOptions);

	// Open the connection
	$pamiClient->open();

	// Setup a event listener
	$pamiClient->registerEventListener(function (EventMessage $event) {
		global $EventBuffer;
		global $running;

		// If it's a device entry push it to the buffer		
		if ($event->getName() == "SCCPDeviceEntry") {
			echo "SCCPDeviceEntry\n";
			
			array_push($EventBuffer, $event);
		}

		// If table end break out of the main loop
		if ($event->getName() == "TableEnd") {
			echo "SCCPDeviceEntry\n";
			$running = false;
		}
	});

	// Send a SCCPShowDevices action via AMI
	$result = $pamiClient->send(new SCCPShowDevicesAction());

	// Main Loop - Wait for Message to Finish (Max 1 min)
	$time = time();
	while($running && ((time() - $time) < 1)) {
		usleep(100000); // 100ms delay
		$pamiClient->process();
	}
	$pamiClient->close();

	// create a new Pusher
	$pusher = new Pusher($uri, $uid, $pwd);

	// Walk the returned events
	foreach($result->getEvents() as $event) {
		if ($event->getKey('event') == "SCCPDeviceEntry") {
			$devicename = $event->getKey('mac');
			$connection = $event->getKey('address');
			/* check if we set a device_array, and only push to those devices */
			if ($connection != "--" && $event->getKey('act') == "No" && (count($device_array) == 0 || in_array($devicename, $device_array))) {
				// split the connection into ip/port parts
				$ip = substr($connection, 0, strrpos($connection, ":", 1));
				$port = substr(strrchr($connection, ":"), 1);
				
				// Handle IPv4-Mapped IPv6 Address
				if (strpos($ip, "::ffff:")) {
					$ip = substr($ip, 8, -1);
				}
				
				// check if result is a valid IP-Address
				if (!filter_var($ip, FILTER_VALIDATE_IP, NULL) === false) {
					echo 'SCCPDeviceEntry name: ' . $devicename . ', ip:' . $ip . "\n";
					echo $pusher->push2phone($ip);
				}
			}
		}
	}
} catch (Exception $e) {
	print "Exception: " . $e->getMessage();
}
exit(0);
?>
