<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmzone
 *
 * @prefix      AZ
 *
 * @file        AZ_controlAlarmZone.php
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

trait AZ_controlAlarmZone
{
    /**
     * Checks all registered door and window sensors for activation mode.
     *
     * @param int $Mode
     * 1    = full protection mode
     * 2    = hull protection mode
     * 3    = partial protection mode
     *
     * @return bool
     * false    = abort activation
     * true     = activate
     *
     * @throws Exception
     */
    public function CheckActivation(int $Mode): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($vars)) {
            return false;
        }
        $activation = true;
        foreach ($vars as $var) {
            //Check if sensor is activated for the requested mode
            switch ($Mode) {
                    case 1: # full protection mode
                        $mode = $var->FullProtectionModeActive;
                        break;

                    case 2: # hull protection mode
                        $mode = $var->HullProtectionModeActive;
                        break;

                    case 3: # partial protection mode
                        $mode = $var->PartialProtectionModeActive;
                        break;

                    default:
                        $mode = false;
                }
            if ($mode) {
                if ($var->Use) {
                    $id = $var->ID;
                    if (boolval(GetValue($id)) == boolval($var->AlertingValue)) {
                        $activation = false;
                    }
                }
            }
        }
        return $activation;
    }

    /**
     * Activates the alarm zone after the defined delay, used by timer.
     */
    public function StartActivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->DeactivateStartActivationTimer();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $timeStamp = date('d.m.Y, H:i:s');
        if ($this->GetValue('AlarmZoneState') == 2) { # delayed
            $this->SetValue('AlarmZoneState', 1); # armed
            //Get activation mode
            $text = 'Es ist ein unbekannter Status bei der verzögerten Aktivierung aufgetreten.  (ID ' . $this->InstanceID . ')';
            $modeName = $this->ReadPropertyString('AlarmZoneName');
            if ($this->GetValue('FullProtectionMode')) {
                $modeName = $this->ReadPropertyString('FullProtectionName');
                $armedSymbol = $this->ReadPropertyString('FullProtectionModeArmedSymbol');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('FullProtectionMode') . ')';
            }
            if ($this->GetValue('HullProtectionMode')) {
                $modeName = $this->ReadPropertyString('HullProtectionName');
                $armedSymbol = $this->ReadPropertyString('HullProtectionModeArmedSymbol');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('HullProtectionMode') . ')';
            }
            if ($this->GetValue('PartialProtectionMode')) {
                $modeName = $this->ReadPropertyString('PartialProtectionName');
                $armedSymbol = $this->ReadPropertyString('PartialProtectionModeArmedSymbol');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('PartialProtectionMode') . ')';
            }
            //Log
            $location = $this->ReadPropertyString('Location');
            $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $this->UpdateAlarmProtocol($logText, 1);
            //Notification
            if (!empty($armedSymbol)) {
                $actionText = $armedSymbol . ' ' . $alarmZoneName . ' scharf!';
            } else {
                $actionText = $alarmZoneName . ' scharf!';
            }
            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
            $this->SendNotification($actionText, $messageText, $logText, 1);
        }
    }

    /**
     * Disables the start activation timer.
     */
    public function DeactivateStartActivationTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('StartActivation', 0);
    }

    /**
     * Disarms the alarm zone.
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function DisarmAlarmZone(string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $this->SetValue('FullProtectionMode', false);
        $this->SetValue('HullProtectionMode', false);
        $this->SetValue('PartialProtectionMode', false);
        $this->SetValue('AlarmZoneState', 0);
        $this->SetValue('AlarmState', 0);
        //Log
        $systemName = $this->ReadPropertyString('SystemName');
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $text = $systemName . ' deaktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent('FullProtectionMode') . ')';
        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
        $this->UpdateAlarmProtocol($logText, 1);
        //Notification
        $disarmedSymbol = $this->ReadPropertyString('AlarmZoneDisarmedSymbol');
        if (!empty($disarmedSymbol)) {
            $actionText = $disarmedSymbol . ' ' . $alarmZoneName . ' unscharf!';
        } else {
            $actionText = $alarmZoneName . ' unscharf!';
        }
        $messageText = $timeStamp . ' ' . $systemName . ' deaktiviert.';
        $this->SendNotification($actionText, $messageText, $logText, 1);
        $this->ConfirmAlarmNotification();
        $this->DeactivateStartActivationTimer();
        $this->ResetBlacklist();
        $this->CheckDoorWindowState(false);
        return true;
    }

    /**
     * Toggles the full protection mode.
     *
     * @param bool $State
     * false    = disarm
     * true     = arm
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     *
     * @throws Exception
     */
    public function ToggleFullProtectionMode(bool $State, string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        //Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID);
        }
        //Arm
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
            $result = $this->ArmAlarmZone($SenderID, 1);
        }
        return $result;
    }

    /**
     * Toggles the hull protection mode.
     *
     * @param bool $State
     * false = disarm
     * true = arm
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     *
     * @throws Exception
     */
    public function ToggleHullProtectionMode(bool $State, string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        //Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID);
        }
        //Arm
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
            $result = $this->ArmAlarmZone($SenderID, 2);
        }
        return $result;
    }

    /**
     * Toggles the partial protection mode.
     *
     * @param bool $State
     * false = disarm
     * true = arm
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     *
     * @throws Exception
     */
    public function TogglePartialProtectionMode(bool $State, string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        //Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID);
        }
        //Arm
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
            $result = $this->ArmAlarmZone($SenderID, 3);
        }
        return $result;
    }

    /**
     * Sets the alarm state to alarm, used by timer.
     */
    public function SetAlarmState(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('SetAlarmState', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->GetValue('AlarmState') != 0) { # no alarm
            $this->SetValue('AlarmState', 1); # alarm
        }
    }

    #################### Private

    /**
     * Arms the alarm zone.
     *
     * @param string $SenderID
     *
     * @param int $Mode
     * 1    = full protection mode
     * 2    = hull protection mode
     * 3    = partial protection mode
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     *
     * @throws Exception
     */
    private function ArmAlarmZone(string $SenderID, int $Mode): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = true;
        switch ($Mode) {
            case 2: # hull protection mode
                $checkActivationModeName = 'CheckHullProtectionModeActivation';
                $identName = 'HullProtectionMode';
                $modeName = $this->ReadPropertyString('HullProtectionName');
                $activationDelayName = 'HullProtectionModeActivationDelay';
                $fullProtectionState = false;
                $hullProtectionState = true;
                $partialProtectionState = false;
                $armedSymbol = $this->ReadPropertyString('HullProtectionModeArmedSymbol');
                break;

            case 3: # partial protection mode
                $checkActivationModeName = 'CheckPartialProtectionModeActivation';
                $identName = 'PartialProtectionMode';
                $modeName = $this->ReadPropertyString('PartialProtectionName');
                $activationDelayName = 'PartialProtectionModeActivationDelay';
                $fullProtectionState = false;
                $hullProtectionState = false;
                $partialProtectionState = true;
                $armedSymbol = $this->ReadPropertyString('PartialProtectionModeArmedSymbol');
                break;

            default: # full protection mode
                $checkActivationModeName = 'CheckFullProtectionModeActivation';
                $identName = 'FullProtectionMode';
                $modeName = $this->ReadPropertyString('FullProtectionName');
                $activationDelayName = 'FullProtectionModeActivationDelay';
                $fullProtectionState = true;
                $hullProtectionState = false;
                $partialProtectionState = false;
                $armedSymbol = $this->ReadPropertyString('FullProtectionModeArmedSymbol');
        }
        $this->SetValue('FullProtectionMode', $fullProtectionState);
        $this->SetValue('HullProtectionMode', $hullProtectionState);
        $this->SetValue('PartialProtectionMode', $partialProtectionState);
        $this->SetValue('AlarmState', 0);
        //Always check doors and windows and inform user about open doors and windows
        $this->ResetBlacklist();
        $this->CheckDoorWindowState(true);
        //Check for activation mode
        $alarmZoneActivation = true;
        if ($this->ReadPropertyBoolean($checkActivationModeName)) {
            $alarmZoneActivation = $this->CheckActivation($Mode);
        }
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        //Abort activation
        if (!$alarmZoneActivation) {
            $result = false;
            $this->SetValue('FullProtectionMode', false);
            $this->SetValue('HullProtectionMode', false);
            $this->SetValue('PartialProtectionMode', false);
            $this->ResetBlacklist();
            //Log
            $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent($identName) . ')';
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $this->UpdateAlarmProtocol($logText, 0);
            //Notification
            $failureSymbol = $this->ReadPropertyString('AlarmZoneSystemFailure');
            if (!empty($failureSymbol)) {
                $actionText = $failureSymbol . ' ' . $alarmZoneName . ' Abbruch durch Sensorenprüfung!';
            } else {
                $actionText = $alarmZoneName . ' Abbruch durch Sensorenprüfung!';
            }
            $messageText = $timeStamp . ' ' . $modeName . ' Abbruch.';
            $this->SendNotification($actionText, $messageText, $logText, 1);
            $this->DeactivateStartActivationTimer();
            $this->ResetBlacklist();
        }
        //Continue activation
        if ($alarmZoneActivation) {
            //Check for activation delay
            $alarmZoneActivationDelayDuration = $this->ReadPropertyInteger($activationDelayName);
            if ($alarmZoneActivationDelayDuration > 0) {
                //Activate timer
                $milliseconds = $alarmZoneActivationDelayDuration * 1000;
                $this->SetTimerInterval('StartActivation', $milliseconds);
                $this->SetValue('AlarmZoneState', 2);
                //Log
                $text = $modeName . ' wird in ' . $alarmZoneActivationDelayDuration . ' Sekunden automatisch aktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent($identName) . ')';
                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                $this->UpdateAlarmProtocol($logText, 0);
                //Notification
                $delayedArmedSymbol = $this->ReadPropertyString('AlarmZoneDelayedArmedSymbol');
                if (!empty($delayedArmedSymbol)) {
                    $actionText = $delayedArmedSymbol . ' ' . $alarmZoneName . ' verzögert scharf!';
                } else {
                    $actionText = $alarmZoneName . ' verzögert scharf!';
                }
                $messageText = $timeStamp . ' ' . $modeName . ' wird verzögert aktiviert.';
                $this->SendNotification($actionText, $messageText, $logText, 1);
            }//Activate mode immediately
            else {
                $this->SetValue('AlarmZoneState', 1);
                //Log
                $text = $modeName . ' aktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent($identName) . ')';
                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                $this->UpdateAlarmProtocol($logText, 1);
                //Notification
                if (!empty($armedSymbol)) {
                    $actionText = $armedSymbol . ' ' . $alarmZoneName . ' scharf!';
                } else {
                    $actionText = $alarmZoneName . ' scharf!';
                }
                $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
                $this->SendNotification($actionText, $messageText, $logText, 1);
            }
        }
        return $result;
    }
}