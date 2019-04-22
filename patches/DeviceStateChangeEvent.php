<?php
/**
 * @category   Pami
 * @package    Message
 * @subpackage Event
 * @author   Diederik de Groot <ddegroot@talon.nl>
 * @license  http://github.com/dkgroot/PAMI Apache License 2.0
 *
 * Copyright 2016 Diederik de Groot <ddegroot@talon.nl>
 */
namespace PAMI\Message\Event;
use PAMI\Message\Event\EventMessage;
class DeviceStateChangeEvent extends EventMessage
{
    /*public static function getMessageKeys()
    {
        return array_merge(
            parent::getMessageKeys(),
            [
                'device',
                'state',
            ]
        );
    }
    */
    public function getKeys()
    {
        return array_merge(
            parent::getKeys(),
            [
                'device',
                'state',
            ]
        );
    }
}
