<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmzone
 *
 * @prefix      AZ
 *
 * @file        AZ_remoteControls.php
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

trait AZ_remoteControls
{
    /**
     * Triggers a remote control action.
     *
     * @param int $SenderID
     *
     * @param bool $ValueChanged
     *
     * @throws Exception
     */
    public function TriggerRemoteControlAction(int $SenderID, bool $ValueChanged): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        //Remote controls
        $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'));
        if (empty($remoteControls)) {
            return;
        }
        foreach ($remoteControls as $remoteControl) {
            $id = $remoteControl->ID;
            if ($id == $SenderID && $remoteControl->Use) {
                $execute = false;
                $triggerType = $remoteControl->TriggerType;
                switch ($triggerType) {
                    case 0:
                        $triggerText = 'Bei Änderung';
                        break;

                    case 1:
                        $triggerText = 'Bei Aktualisierung';
                        break;

                    case 2:
                        $triggerText = 'Bei bestimmtem Wert (einmalig)';
                        break;

                    case 3:
                        $triggerText = 'Bei bestimmtem Wert (mehrmalig)';
                        break;

                    default:
                        $triggerText = 'unbekannt';

                }
                $this->SendDebug(__FUNCTION__, 'Auslöser: ' . $triggerText, 0);
                switch ($triggerType) {
                    case 0: # value changed
                        if ($ValueChanged) {
                            $this->SendDebug(__FUNCTION__, 'Wert hat sich geändert', 0);
                            $execute = true;
                        }
                        break;

                    case 1: # value updated
                        $this->SendDebug(__FUNCTION__, 'Wert hat sich aktualisiert', 0);
                        $execute = true;
                        break;

                    case 2: # defined value, execution once
                        $actualValue = intval(GetValue($id));
                        $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . $actualValue, 0);
                        $triggerValue = $remoteControl->TriggerValue;
                        $this->SendDebug(__FUNCTION__, 'Auslösender Wert: ' . $triggerValue, 0);
                        if ($actualValue == $triggerValue && $ValueChanged) {
                            $execute = true;
                        } else {
                            $this->SendDebug(__FUNCTION__, 'Keine Übereinstimmung!', 0);
                        }
                        break;

                    case 3: # defined value, multiple execution
                        $actualValue = intval(GetValue($id));
                        $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . $actualValue, 0);
                        $triggerValue = $remoteControl->TriggerValue;
                        $this->SendDebug(__FUNCTION__, 'Auslösender Wert: ' . $triggerValue, 0);
                        if ($actualValue == $triggerValue) {
                            $execute = true;
                        } else {
                            $this->SendDebug(__FUNCTION__, 'Keine Übereinstimmung!', 0);
                        }
                        break;

                }
                if ($execute) {
                    $action = $remoteControl->Action;
                    $name = $SenderID . ', ' . $remoteControl->Name;
                    switch ($action) {
                        case 1: # disarm alarm zone
                            $this->DisarmAlarmZone($name);
                            break;

                        case 2: # arm full protection  mode
                            $this->ToggleFullProtectionMode(true, $name);
                            break;

                        case 3: # arm hull protection mode
                            $this->ToggleHullProtectionMode(true, $name);
                            break;

                        case 4: # arm partial protection mode
                            $this->TogglePartialProtectionMode(true, $name);
                            break;

                        case 5: # script
                            $scriptID = $remoteControl->ScriptID;
                            if ($scriptID != 0 && @IPS_ObjectExists($scriptID)) {
                                @IPS_RunScript($scriptID);
                            }
                            break;

                    }
                }
            }
        }
    }
}