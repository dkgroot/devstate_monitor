#!/usr/bin/env php
<?php
/**
 * @category   Asterisk / Generate Call / Pami
 * @package    Simple Call Generator
 * @author   Diederik de Groot <ddegroot@talon.nl>
 * @license  http://github.com/dkgroot/PAMI Apache License 2.0
 *
 * Copyright 2016 Diederik de Groot <ddegroot@talon.nl>
 */
if ($argc > 1) {
    echo "Usage: $argv[0]] ";
    echo "Purpose: Generate a number of subsequent calls from calling_device to recipient_line, answer the incoming call, hangup.";
    echo "edit gen_call.cfg to set parameters";
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
    private $loop;
    
    public function __construct($call_info) {
        $this->call_info = $call_info;
        $this->loop = $call_info['number_of_loops'];
    }
    
    public function setPami($pami) {
        $this->pami = $pami;
    }
    
    public function handle(EventMessage $event)
    {
        $call_info = $this->call_info;
        $pami = $this->pami;
        try {
            if ($event instanceof PAMI\Message\Event\NewstateEvent && $event->getLinkedId() == $this->call_info['linkedid']) {	// Wait for a ChannelState for our LinkedId
                $channel = $event->getChannel();
                $exten = $event->getExten();
                $channelState = $event->getChannelStateDesc();
                if ($exten == $call_info['recipient_line']) {
                    echo("Got a new NewstateEvent\n");
                    echo(" - Channel:".$channel."\n");
                    echo(" - ChannelStateDesc:".$channelState."\n");
                    echo(" - Linkedid:".$event->getLinkedId()."\n");
                    echo(" - Exten:".$exten."\n");
                    if ($channelState === "Ringing") {										// Answer the call
                        $action = new SCCPAnswerCallAction($event->getChannel(), $call_info['recipient_device']);
                        $action->setActionId("1432.124");
                        $response = $pami->send($action);
                    }
                    if ($channelState === "Up") {										// Hangup the call
                        $action = new HangupAction($channel);
                        $action->setActionId("1432.125");
                        $response = $pami->send($action);
                        
                    }
                }
            }
            if ($event instanceof PAMI\Message\Event\DeviceStateChangeEvent && $event->getDevice() == "SCCP/".$call_info['calling_line']) {
                echo("Got a new DeviceStateChangeEvent\n");
                echo(" - Device:".$event->getDevice()."\n");
                echo(" - State:".$event->getState()."\n");
                if ($event->getState() == "NOT_INUSE") {
                    usleep(1000000);												// Give phones a little time to come to standstill
                    echo "Next Call:" . $this->loop . "\n";
                    if ($this->loop--) {											// Generate Next Call
                        $action = new SCCPStartCallAction($call_info['calling_device'], $call_info['calling_line'], $call_info['recipient_line']);
                        $action->setActionId("1432.123");
                        $action->setLinkedId($call_info['linkedid']);
                        $response = $pami->send($action);
                    } else {													// logoff pami immediately so we stop fast
                        $action = new LogoffAction();
                        $response = $pami->send($action);
                    }
                }
            }
            //var_dump($event);
        } catch (Exception $e) {
            //echo $e->getMessage() . "\n";
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
    require('gen_call.cfg');
    echo("Connecting to host:".$options['host'].":".$options['port']."\n");
    
    $pami = new ClientImpl($options);
    $eventListener = new EventListener($call_info);
    $pami->registerEventListener($eventListener);
    $pami->open();
    $eventListener->setPami($pami);
    echo("Connected\n");

    // Generate the initial call
    $action = new SCCPStartCallAction($call_info['calling_device'], $call_info['calling_line'], $call_info['recipient_line']);
    $action->setActionId("1432.123");
    $action->setLinkedId($call_info['linkedid']);
    $response = $pami->send($action);

    // wait for AMI event and handle the rest in the EventListener
    $time = time();
    do {
        usleep(10000);					// wait 10 ms
        $pami->process();				// poll pami to see if anything happened
    } while($pami);

    if (!is_null($pami)) {
        $pami->close(); // send logoff and close the connection.
    }
} catch (Exception $e) {
    //echo $e->getMessage() . "\n";
}
////////////////////////////////////////////////////////////////////////////////
// Code ENDS.
////////////////////////////////////////////////////////////////////////////////
