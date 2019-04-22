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
    /**
     * Returns array with keys
     *
     * @return array
     */
    public function getKeys()
    {
        return array_merge(
            parent::getKeys(),
            [
                'priviledge',
                'device',
                'state',
            ]
        );
    }
    
    /**
     * Returns key: 'Privilege'.
     *
     * @return string
     */
    public function getPrivilege()
    {
        return $this->getKey('Privilege');
    }
    
    /**
     * Returns key: 'Device'.
     *
     * @return string
     */
    public function getDevice()
    {
        return $this->getKey('Device');
    }
    
    /**
     * Returns key: 'State'.
     *
     * @return string
     */
    public function getState()
    {
        return $this->getKey('State');
    }    
}
