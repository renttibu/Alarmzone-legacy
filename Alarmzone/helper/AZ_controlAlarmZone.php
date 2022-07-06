<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzone
 */

declare(strict_types=1);

trait AZ_controlAlarmZone
{
    public function CheckActivation(int $Mode): bool
    {
        /*
         * $Mode
         * 1    = full protection mode
         * 2    = hull protection mode
         * 3    = partial protection mode
         */
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (empty($vars)) {
            return false;
        }
        $activation = true;
        foreach ($vars as $var) {
            // Check if sensor is activated for the requested mode
            switch ($Mode) {
                    case 1: # full protection mode
                        $mode = $var->FullProtectionModeActive;
                        $checkActivation = $var->CheckFullProtectionActivation;
                        break;

                    case 2: # hull protection mode
                        $mode = $var->HullProtectionModeActive;
                        $checkActivation = $var->CheckHullProtectionActivation;
                        break;

                    case 3: # partial protection mode
                        $mode = $var->PartialProtectionModeActive;
                        $checkActivation = $var->CheckPartialProtectionActivation;
                        break;

                    default:
                        $mode = false;
                        $checkActivation = false;
                }
            if ($mode) {
                if ($var->Use && $checkActivation) {
                    $id = $var->ID;
                    if ($id == 0 || @!IPS_ObjectExists($id)) {
                        continue;
                    }
                    $type = IPS_GetVariable($id)['VariableType'];
                    $value = $var->TriggerValue;
                    switch ($var->TriggerType) {
                        case 0: #on change (bool, integer, float, string)
                        case 1: #on update (bool, integer, float, string)
                            $this->SendDebug(__FUNCTION__, 'Bei Änderung und bei Aktualisierung wird nicht berücksichtigt!', 0);
                            break;

                        case 2: #on limit drop, once (integer, float)
                        case 3: #on limit drop, every time (integer, float)
                            switch ($type) {
                                case 1: #integer
                                    if (GetValueInteger($id) < intval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                        $activation = false;
                                    }
                                    break;

                                case 2: #float
                                    if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                        $activation = false;
                                    }
                                    break;

                            }
                            break;

                        case 4: #on limit exceed, once (integer, float)
                        case 5: #on limit exceed, every time (integer, float)
                            switch ($type) {
                                case 1: #integer
                                    if (GetValueInteger($id) > intval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                        $activation = false;
                                    }
                                    break;

                                case 2: #float
                                    if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                        $activation = false;
                                    }
                                    break;

                            }
                            break;

                        case 6: #on specific value, once (bool, integer, float, string)
                        case 7: #on specific value, every time (bool, integer, float, string)
                            switch ($type) {
                                case 0: #bool
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if (GetValueBoolean($id) == boolval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                        $activation = false;
                                    }
                                    break;

                                case 1: #integer
                                    if (GetValueInteger($id) == intval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (integer)', 0);
                                        $activation = false;
                                    }
                                    break;

                                case 2: #float
                                    if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                        $activation = false;
                                    }
                                    break;

                                case 3: #string
                                    if (GetValueString($id) == (string) $value) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                        $activation = false;
                                    }
                                    break;

                            }
                            break;

                    }
                }
            }
        }
        return $activation;
    }

    public function StartActivation(): void
    {
        $this->DeactivateStartActivationTimer();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->ResetBlacklist();
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        // Full protection
        $mode = 1;
        $checkActivationModeName = 'CheckFullProtectionModeActivation';
        $identName = 'FullProtectionMode';
        $modeName = $this->ReadPropertyString('FullProtectionName');
        // Hull protection
        if ($this->GetValue('HullProtectionMode')) {
            $mode = 2;
            $checkActivationModeName = 'CheckHullProtectionModeActivation';
            $identName = 'HullProtectionMode';
            $modeName = $this->ReadPropertyString('HullProtectionName');
        }
        // Partial protection
        if ($this->GetValue('PartialProtectionMode')) {
            $mode = 3;
            $checkActivationModeName = 'CheckPartialProtectionModeActivation';
            $identName = 'PartialProtectionMode';
            $modeName = $this->ReadPropertyString('PartialProtectionName');
        }
        $alarmZoneActivation = true;
        if ($this->ReadPropertyBoolean($checkActivationModeName)) {
            $alarmZoneActivation = $this->CheckActivation($mode);
        }
        if (!$alarmZoneActivation) {
            $this->SetValue('FullProtectionMode', false);
            $this->SetValue('HullProtectionMode', false);
            $this->SetValue('PartialProtectionMode', false);
            $this->SetValue('AlarmZoneState', 0);
            $this->SetValue('AlarmState', 0);
            $this->SetValue('AlertingSensor', 'OK');
            $this->SetValue('AlarmSiren', false);
            $this->SetValue('AlarmLight', false);
            $this->SetValue('AlarmCall', false);
            $this->ResetBlacklist();
            $this->DeactivateStartActivationTimer();
            // Protocol
            $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent($identName) . ')';
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $this->UpdateAlarmProtocol($logText, 0);
        } else {
            $this->CheckDoorWindowState(true); # add to blacklist
            $state = 1; # armed
            if ($this->ReadPropertyBoolean('DetailedAlarmZoneState') && $this->GetValue('DoorWindowState')) {
                $state = 3; # partial armed
            }
            $this->SetValue('AlarmZoneState', $state);
            // Protocol
            $text = $modeName . ' aktiviert. (Einschaltverzögerung, ID ' . $this->GetIDForIdent($identName) . ')';
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $this->UpdateAlarmProtocol($logText, 1);
        }
    }

    public function DeactivateStartActivationTimer(): void
    {
        $this->SetTimerInterval('StartActivation', 0);
    }

    public function DisarmAlarmZone(string $SenderID): bool
    {
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $this->SetValue('FullProtectionMode', false);
        $this->SetValue('HullProtectionMode', false);
        $this->SetValue('PartialProtectionMode', false);
        $this->SetValue('AlarmZoneState', 0);
        $this->SetValue('AlarmState', 0);
        $this->SetValue('AlertingSensor', 'OK');
        $this->SetValue('AlarmSiren', false);
        $this->SetValue('AlarmLight', false);
        $this->SetValue('AlarmCall', false);
        $this->DeactivateStartActivationTimer();
        $this->ResetBlacklist();
        // Protocol
        $systemName = $this->ReadPropertyString('SystemName');
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $text = $systemName . ' deaktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent('FullProtectionMode') . ')';
        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
        $this->UpdateAlarmProtocol($logText, 1);
        $this->CheckDoorWindowState(false);
        return true;
    }

    public function ToggleFullProtectionMode(bool $State, string $SenderID): bool
    {
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID);
        }
        // Arm
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
            $result = $this->ArmAlarmZone($SenderID, 1);
        }
        return $result;
    }

    public function ToggleHullProtectionMode(bool $State, string $SenderID): bool
    {
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID);
        }
        // Arm
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
            $result = $this->ArmAlarmZone($SenderID, 2);
        }
        return $result;
    }

    public function TogglePartialProtectionMode(bool $State, string $SenderID): bool
    {
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID);
        }
        // Arm
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
            $result = $this->ArmAlarmZone($SenderID, 3);
        }
        return $result;
    }

    #################### Private

    private function ArmAlarmZone(string $SenderID, int $Mode): bool
    {
        /*
         * $Mode
         * 1    = full protection mode
         * 2    = hull protection mode
         * 3    = partial protection mode
         */
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
                break;

            case 3: # partial protection mode
                $checkActivationModeName = 'CheckPartialProtectionModeActivation';
                $identName = 'PartialProtectionMode';
                $modeName = $this->ReadPropertyString('PartialProtectionName');
                $activationDelayName = 'PartialProtectionModeActivationDelay';
                $fullProtectionState = false;
                $hullProtectionState = false;
                $partialProtectionState = true;
                break;

            default: # full protection mode
                $checkActivationModeName = 'CheckFullProtectionModeActivation';
                $identName = 'FullProtectionMode';
                $modeName = $this->ReadPropertyString('FullProtectionName');
                $activationDelayName = 'FullProtectionModeActivationDelay';
                $fullProtectionState = true;
                $hullProtectionState = false;
                $partialProtectionState = false;
        }
        $this->SetValue('FullProtectionMode', $fullProtectionState);
        $this->SetValue('HullProtectionMode', $hullProtectionState);
        $this->SetValue('PartialProtectionMode', $partialProtectionState);
        $this->SetValue('AlarmState', 0);
        $this->SetValue('AlertingSensor', 'OK');

        $this->ResetBlacklist();

        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');

        // Check activation delay
        $alarmZoneActivationDelayDuration = $this->ReadPropertyInteger($activationDelayName);
        if ($alarmZoneActivationDelayDuration > 0) {
            $this->CheckDoorWindowState(false); # don't add to blacklist
            // Activate timer
            $milliseconds = $alarmZoneActivationDelayDuration * 1000;
            $this->SetTimerInterval('StartActivation', $milliseconds); # Methode -> StartActivation
            $stateValue = 2; //0; 2022-07-06 Changed back to value 2
            if ($this->ReadPropertyBoolean('DetailedAlarmZoneState') && $this->GetValue('DoorWindowState')) {
                $stateValue = 4;
            }
            $this->SetValue('AlarmZoneState', $stateValue);
            // Protocol
            $text = $modeName . ' wird in ' . $alarmZoneActivationDelayDuration . ' Sekunden automatisch aktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent($identName) . ')';
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $this->UpdateAlarmProtocol($logText, 0);
        } else {
            // Check for activation
            $alarmZoneActivation = true;
            if ($this->ReadPropertyBoolean($checkActivationModeName)) {
                $alarmZoneActivation = $this->CheckActivation($Mode);
            }
            if (!$alarmZoneActivation) {
                $result = false;
                $this->SetValue('FullProtectionMode', false);
                $this->SetValue('HullProtectionMode', false);
                $this->SetValue('PartialProtectionMode', false);
                $this->SetValue('AlarmZoneState', 0);
                $this->SetValue('AlarmState', 0);
                $this->SetValue('AlertingSensor', 'OK');
                $this->SetValue('AlarmSiren', false);
                $this->SetValue('AlarmLight', false);
                $this->SetValue('AlarmCall', false);
                $this->ResetBlacklist();
                $this->DeactivateStartActivationTimer();
                // Protocol
                $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent($identName) . ')';
                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                $this->UpdateAlarmProtocol($logText, 0);
            } else {
                $this->CheckDoorWindowState(true); # add to blacklist
                $state = 1; # armed
                if ($this->ReadPropertyBoolean('DetailedAlarmZoneState') && $this->GetValue('DoorWindowState')) {
                    $state = 3; # partial armed
                }
                $this->SetValue('AlarmZoneState', $state);
                // Protocol
                $text = $modeName . ' aktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent($identName) . ')';
                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                $this->UpdateAlarmProtocol($logText, 1);
            }
        }
        return $result;
    }
}