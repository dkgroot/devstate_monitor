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
if ($argc <7 ) {
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

// patching the offical PAMI release
require(implode(DIRECTORY_SEPARATOR, array(
    __DIR__,
    'patches',
    'DeviceStateChangeEvent.php'
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
            echo("Got a DeviceStateChangeEvent\n");
            //var_dump($event);
            $array = $event->getKeys();
            echo("device:".$array['device']."\n");
            echo("state:".$array['state']."\n");
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

        'connect_timeout' => 60,
        'read_timeout' => 60
    );
    echo("Connecting to host:".$options['host'].":".$options['port']."\n");

    $pami = new ClientImpl($options);
    $pami->registerEventListener(new EventListener());
    $pami->open();

    // start waiting for events (for 1 minute)
    $time = time();
    while((time() - $time) < 60)
    {
        usleep(1000);					// wait 10 ms
        $pami->process();				// poll pami to see if anything happened
    }
    $pami->close(); // send logoff and close the connection.
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
////////////////////////////////////////////////////////////////////////////////
// Code ENDS.
////////////////////////////////////////////////////////////////////////////////
