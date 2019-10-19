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
    echo "example: ./call_gen.php 192.168.1.20 5038 admin secret
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
use PAMI\Message\Action\LogoffAction;
use PAMI\Message\Action\ListCommandsAction;
use PAMI\Message\Action\ListCategoriesAction;
use PAMI\Message\Action\CoreSettingsAction;
use PAMI\Message\Action\SCCPStartCallAction;
use PAMI\Message\Action\SCCPAnswerCallAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Event\DeviceStateChangeEvent;
use PAMI\Message\Event\NewchannelEvent;
use PAMI\Message\Event\NewstateEvent;

class EventListener implements IEventListener
{
    private $call_info;
    private $pami;
    
    public function __construct($call_info) {
        $this->call_info = $call_info;
    }
    
    public function setPami($pami) {
        $this->pami = $pami;
    }
    
    public function handle(EventMessage $event)
    {
        //This Handler will print the incoming message (only for the event we are interested in).
        //var_dump($event);
        /*
        if ($event instanceof PAMI\Message\Event\DeviceStateChangeEvent) {
            echo("Got a new DeviceStateChangeEvent\n");
            //var_dump($event);
            echo(" - Device:".$event->getDevice()."\n");
            echo(" - State:".$event->getState()."\n");
        }
        */
        /*
        if ($event instanceof PAMI\Message\Event\NewchannelEvent && $event->getLinkedId() == $this->call_info['linkedid']) {
            echo("Got a new NewchannelEvent\n");
            echo(" - Channel:".$event->getChannel()."\n");
            echo(" - Linkedid:".$event->getLinkedId()."\n");
        }
        */
        if ($event instanceof PAMI\Message\Event\NewstateEvent && $event->getLinkedId() == $this->call_info['linkedid']) {	// found the event we were waiting for
            $channel = $event->getChannel();
            $exten = $event->getExten();
            $channelState = $event->getChannelStateDesc();
            // Answer the call
            try {
                if ($exten == $this->call_info['called_line']) {
                    echo("Got a new NewstateEvent\n");
                    echo(" - Channel:".$channel."\n");
                    echo(" - ChannelStateDesc:".$channelState."\n");
                    echo(" - Linkedid:".$event->getLinkedId()."\n");
                    echo(" - Exten:".$exten."\n");
                    if ($channelState === "Ringing") {										// Answer the call
                        $action = new SCCPAnswerCallAction($event->getChannel(), $this->call_info['called_device']);
                        $action->setActionId("1432.124");
                        $response = $this->pami->send($action);
                    }

                    if ($channelState === "Up") {										// Hangup the call
                        $action = new HangupAction($channel);
                        $action->setActionId("1432.125");
                        $response = $this->pami->send($action);
                        
                        // logoff pami immediately so we stop fast
                        $action = new LogoffAction();
                        $response = $this->pami->send($action);
                    }
                }
            } catch (Exception $e) {
                //echo $e->getMessage() . "\n";
            }
        }
        //var_dump($event);
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
        'read_timeout' => 3
    );
    echo("Connecting to host:".$options['host'].":".$options['port']."\n");
    
    $pami = new ClientImpl($options);

    // read from gen_call.ini instead of static definition
    $call_info = array(
        'caller_device' => "SEPE0D173E11D95",
        'called_device' => "SEP0023043403F9",
        'caller_line' => "98031",
        'called_line' => "98041",
        'linkedid' => "900001",
    );
    
    $eventListener = new EventListener($call_info);
    $pami->registerEventListener($eventListener);
    $pami->open();
    $eventListener->setPami($pami);

    $action = new SCCPStartCallAction($call_info['caller_device'], $call_info['caller_line'], $call_info['called_line']);
    $action->setActionId("1432.123");
    $action->setLinkedId($call_info['linkedid']);
    $response = $pami->send($action);

    // wait for AMI event and handle the rest in the EventListener
    $time = time();
    while($pami && (time() - $time) < $options['connect_timeout'])   // start waiting for events (for ;connect_timeout' minute)
    {
        usleep(10000);					// wait 10 ms
        $pami->process();				// poll pami to see if anything happened
    }
    if (!is_null($pami)) {
        $pami->close(); // send logoff and close the connection.
    }
} catch (Exception $e) {
    //echo $e->getMessage() . "\n";
}
////////////////////////////////////////////////////////////////////////////////
// Code ENDS.
////////////////////////////////////////////////////////////////////////////////
