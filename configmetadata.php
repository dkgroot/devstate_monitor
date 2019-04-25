#!/usr/bin/env php
<?php
/**
 * @category   Asterisk / Demo Config MetaData / Pami
 * @package    Simple Config MetaData Demo
 * @author   Diederik de Groot <ddegroot@talon.nl>
 * @license  http://github.com/dkgroot/PAMI Apache License 2.0
 *
 * Copyright 2016 Diederik de Groot <ddegroot@talon.nl>
 */
if ($argc <5 ) {
    echo "Use: $argv[0] <host> <port> <user> <pass>] ";
    echo "example: $argv[0] 192.168.1.20 5038 admin secret
" ;
    exit (254);
}

ini_set(
    'include_path',
    implode(
        PATH_SEPARATOR,
        array(
            implode(DIRECTORY_SEPARATOR), 
            './vendor/php/log4php',
            ini_get('include_path'),
        )
    )
);


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
use PAMI\Message\Action\GetConfigJSONAction;
use PAMI\Message\Action\SCCPConfigMetaDataAction;

class EventListener implements IEventListener
{
    public function handle(EventMessage $event)
    {
        //This Handler will print the incoming message (only for the event we are interested in).
        //var_dump($event);
        /*
        if ($event instanceof PAMI\Message\Event\DeviceStateChangeEvent) {
            echo("Got a new DeviceStateChangeEvent\n");
            echo(" - Device:".$event->getDevice()."\n");
            echo(" - State:".$event->getState()."\n");
        }
        */
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
        'log4php.properties' => realpath(__DIR__) . DIRECTORY_SEPARATOR . 'log4php.properties',
        
        'host' => $argv[1],
        'port' => $argv[2],
        'username' => $argv[3],
        'secret' => $argv[4],

        'connect_timeout' => 2,
        'read_timeout' => 2
    );
    echo("Connecting to host:".$options['host'].":".$options['port']."\n");

    $pami = new ClientImpl($options);
    $pami->registerEventListener(new EventListener());
    $pami->open();

    $response = $pami->send(new SCCPConfigMetaDataAction());
    var_dump($response);
    print_r($response->getJSON());

    $response = $pami->send(new SCCPConfigMetaDataAction("general"));
    print_r($response->getJSON());

    $response = $pami->send(new SCCPConfigMetaDataAction("device"));
    print_r($response->getJSON());

    $time = time();
    while((time() - $time) < $options['connect_timeout'])
    {
        usleep(10000);                   // wait 10 ms
        $pami->process();                // poll pami to see if anything happened
    }
    $pami->close(); // send logoff and close the connection.
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
////////////////////////////////////////////////////////////////////////////////
// Code ENDS.
////////////////////////////////////////////////////////////////////////////////
    
