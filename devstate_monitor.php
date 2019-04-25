#!/usr/bin/env php
<?php
/**
 * @category   Asterisk / DeviceState / Pami
 * @package    Simple DevState Monitor
 * @author   Diederik de Groot <ddegroot@talon.nl>
 * @license  http://github.com/dkgroot/PAMI Apache License 2.0
 *
 * Copyright 2016 Diederik de Groot <ddegroot@talon.nl>
 */
if ($argc <5 ) {
    echo "Use: $argv[0] <host> <port> <user> <pass>] ";
    echo "example: ./devstate_monitor.php 192.168.1.20 5038 admin secret
" ;
    exit (254);
}

declare(ticks=1);
////////////////////////////////////////////////////////////////////////////////
// Mandatory stuff to bootstrap.
////////////////////////////////////////////////////////////////////////////////
require(implode(DIRECTORY_SEPARATOR, array(
    __DIR__,
    'vendor',
    'autoload.php'
)));

use PAMI\Client\Impl\ClientImpl;
use PAMI\Listener\IEventListener;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Action\CommandAction;
use PAMI\Message\Action\ListCommandsAction;
use PAMI\Message\Action\ListCategoriesAction;
use PAMI\Message\Action\CoreSettingsAction;
use PAMI\Message\Event\DeviceStateChangeEvent;

class EventListener implements IEventListener
{
    public function handle(EventMessage $event)
    {
        //This Handler will print the incoming message (only for the event we are interested in).
        //var_dump($event);
        if ($event instanceof PAMI\Message\Event\DeviceStateChangeEvent) {
            echo("Got a new DeviceStateChangeEvent\n");
            //var_dump($event);
            echo(" - Device:".$event->getDevice()."\n");
            echo(" - State:".$event->getState()."\n");
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// Code STARTS.
////////////////////////////////////////////////////////////////////////////////

error_reporting(E_ALL);
ini_set('display_errors', 1);

try
{
    $options = array(
        'host' => $argv[1],
        'port' => $argv[2],
        'username' => $argv[3],
        'secret' => $argv[4],

        'connect_timeout' => 10,
        'read_timeout' => 1
    );
    echo("Connecting to host:".$options['host'].":".$options['port']."\n");

    $pami = new ClientImpl($options);
    $pami->registerEventListener(new EventListener());
    $pami->open();

    $time = time();
    while((time() - $time) < $options['connect_timeout'])           // start waiting for events (for ;connect_timeout' minute)
    //while (true)                                                  // or run indefinitly
    {
        $command = new CommandAction("devstate change Custom:mystate1 INUSE");
        $command->setActionId("1432.123");
        $response = $pami->send($command);

        usleep(10000);					// wait 10 ms
        $pami->process();				// poll pami to see if anything happened

        sleep(1);				        // wait 1s
        $response = $pami->send(new CommandAction("devstate change Custom:mystate1 NOT_INUSE"));

        usleep(10000);					// wait 10 ms
        $pami->process();				// poll pami to see if anything happened
    }
    $pami->close(); // send logoff and close the connection.
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
////////////////////////////////////////////////////////////////////////////////
// Code ENDS.
////////////////////////////////////////////////////////////////////////////////
