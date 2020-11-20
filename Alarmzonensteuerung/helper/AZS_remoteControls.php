<?php

/** @noinspection PhpUnused */

/*
 * @module      Alarmzonensteuerung
 *
 * @prefix      AZS
 *
 * @file        AZS_remoteControls.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Alarmzone
 *
 */

declare(strict_types=1);

trait AZS_remoteControls
{
    /**
     * Triggers a remote control action.
     *
     * @param int $SenderID
     */
    public function TriggerRemoteControlAction(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'));
        if (empty($remoteControls)) {
            return;
        }
        foreach ($remoteControls as $remoteControl) {
            if ($remoteControl->ID == $SenderID && $remoteControl->Use) {
                $action = $remoteControl->Action;
                $name = $remoteControl->Name;
                switch ($action) {
                    case 1: # disarm
                        $this->DisarmAlarmZones($name);
                        break;
                    case 2: # arm full protection mode
                        $this->ToggleFullProtectionMode(true, $name);
                        break;

                    case 3: # arm hull protection mode
                        $this->ToggleHullProtectionMode(true, $name);
                        break;

                    case 4: # arm partial protection mode
                        $this->TogglePartialProtectionMode(true, $name);
                        break;

                    case 5: # use a script
                        $scriptID = $remoteControl->ScriptID;
                        if ($scriptID != 0 && @IPS_ObjectExists($scriptID)) {
                            IPS_RunScript($scriptID);
                        }
                        break;

                }
            }
        }
    }
}