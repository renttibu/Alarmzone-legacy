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

trait AZ_doorWindowSensors
{
    public function DetermineDoorWindowVariables(): void
    {
        $variables = [];
        foreach (@IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID) as $instanceID) {
            $childrenIDs = @IPS_GetChildrenIDs($instanceID);
            foreach ($childrenIDs as $childrenID) {
                $match = false;
                $object = @IPS_GetObject($childrenID);
                if ($object['ObjectIdent'] == 'STATE') {
                    $match = true;
                }
                if ($match) {
                    // Check for variable
                    if ($object['ObjectType'] == 2) {
                        $name = strstr(@IPS_GetName($instanceID), ':', true);
                        if ($name == false) {
                            $name = @IPS_GetName($instanceID);
                        }
                        $type = IPS_GetVariable($childrenID)['VariableType'];
                        $triggerValue = 'true';
                        if ($type == 1) {
                            $triggerValue = '1';
                        }
                        array_push($variables, [
                            'Use'                         => true,
                            'Name'                        => $name,
                            'ID'                          => $childrenID,
                            'TriggerType'                 => 6,
                            'TriggerValue'                => $triggerValue,
                            'FullProtectionModeActive'    => true,
                            'HullProtectionModeActive'    => true,
                            'PartialProtectionModeActive' => true,
                            'UseAlarmSiren'               => true,
                            'UseAlarmLight'               => true,
                            'UseAlarmCall'                => true]);
                    }
                }
            }
        }
        // Get already listed variables
        $listedVariables = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
        // Add new variables
        if (!empty($listedVariables)) {
            foreach (array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID')) as $addVariable) {
                $name = strstr(@IPS_GetName(@IPS_GetParent($addVariable)), ':', true);
                $type = IPS_GetVariable($addVariable)['VariableType'];
                $triggerValue = 'true';
                if ($type == 1) {
                    $triggerValue = '1';
                }
                array_push($listedVariables, [
                    'Use'                         => true,
                    'Name'                        => $name,
                    'ID'                          => $addVariable,
                    'TriggerType'                 => 6,
                    'TriggerValue'                => $triggerValue,
                    'FullProtectionModeActive'    => true,
                    'HullProtectionModeActive'    => true,
                    'PartialProtectionModeActive' => true,
                    'UseNotification'             => true,
                    'UseAlarmSiren'               => true,
                    'UseAlarmLight'               => true,
                    'UseAlarmCall'                => true]);
            }
        } else {
            $listedVariables = $variables;
        }
        // Sort variables by name
        array_multisort(array_column($listedVariables, 'Name'), SORT_ASC, $listedVariables);
        $listedVariables = array_values($listedVariables);
        // Update variable list
        $value = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'DoorWindowSensors', $value);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Die Tür- und Fenstersensoren wurden erfolgreich ermittelt!';
    }

    public function CheckDoorWindowSensorAlerting(int $SenderID, bool $ValueChanged): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
        if (!empty($doorWindowSensors)) {
            // Check if sensor is listed
            $key = array_search($SenderID, array_column($doorWindowSensors, 'ID'));
            if (is_int($key)) {
                if (!$doorWindowSensors[$key]['Use']) {
                    return;
                }
                $execute = false;
                $open = false;
                $type = IPS_GetVariable($SenderID)['VariableType'];
                $value = $doorWindowSensors[$key]['TriggerValue'];
                switch ($doorWindowSensors[$key]['TriggerType']) {
                    case 0: # on change (bool, integer, float, string)
                        if ($ValueChanged) {
                            $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                            $execute = true;
                            $open = true;
                        }
                        break;

                    case 1: # on update (bool, integer, float, string)
                        $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                        $execute = true;
                        $open = true;
                        break;

                    case 2: # on limit drop, once (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) < intval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 3: # on limit drop, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($SenderID) < intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    $open = true;
                                }
                                break;

                            case 2: # float
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    $open = true;
                                }
                                break;

                        }
                        break;

                    case 4: # on limit exceed, once (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) > intval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 5: # on limit exceed, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($SenderID) > intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    $open = true;
                                }
                                break;

                            case 2: # float
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    $open = true;
                                }
                                break;

                        }
                        break;

                    case 6: # on specific value, once (bool, integer, float, string)
                        switch ($type) {
                            case 0: #bool
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if (GetValueBoolean($SenderID) == boolval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                            case 1: # integer
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) == intval($value)) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    $execute = true;
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                            case 3: # string
                                if ($ValueChanged) {
                                    $execute = true;
                                    if (GetValueString($SenderID) == (string) $value) {
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                                        $open = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 7: # on specific value, every time (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($SenderID) == boolval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    $open = true;
                                }
                                break;

                            case 1: # integer
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($SenderID) == intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    $open = true;
                                }
                                break;

                            case 2: # float
                                $execute = true;
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                                    $open = true;
                                }
                                break;

                            case 3: # string
                                $execute = true;
                                if (GetValueString($SenderID) == (string) $value) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                                    $open = true;
                                }
                                break;

                        }
                        break;
                }
                $this->SendDebug(__FUNCTION__, 'Bedingung erfüllt: ' . json_encode($execute), 0);
                $this->SendDebug(__FUNCTION__, 'Tür / Fenster geöffnet: ' . json_encode($open), 0);
                // Check alarm zone state
                $sensorName = $doorWindowSensors[$key]['Name'];
                $timeStamp = date('d.m.Y, H:i:s');
                $location = $this->ReadPropertyString('Location');
                $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
                $alarmZoneState = $this->GetValue('AlarmZoneState');
                switch ($alarmZoneState) {
                    case 0: # disarmed
                        if ($execute) {
                            $this->CheckDoorWindowState(false);
                            // Protocol
                            $text = $sensorName . ' wurde geschlossen. (ID ' . $SenderID . ')';
                            if ($open) {
                                $text = $sensorName . ' wurde geöffnet. (ID ' . $SenderID . ')';
                            }
                            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                            $this->UpdateAlarmProtocol($logText, 0);
                        }
                        break;

                    case 1: # armed
                    case 3: # partial armed
                        if ($execute) {
                            $this->CheckDoorWindowState(false);
                        }
                        // Variable is black listed
                        if ($this->CheckSensorBlacklist($SenderID)) {
                            if ($execute) {
                                // Protocol
                                $text = $sensorName . ' wurde geschlossen. (ID ' . $SenderID . ')';
                                if ($open) {
                                    $text = $sensorName . ' wurde ohne Alarmauslösung geöffnet. (ID ' . $SenderID . ')';
                                }
                                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                                $this->UpdateAlarmProtocol($logText, 0);
                            }
                        } // Variable is not black listed
                        else {
                            $alerting = false;
                            if ($execute) {
                                // Check if sensor is activated for full protection mode
                                if ($this->GetValue('FullProtectionMode')) {
                                    if ($doorWindowSensors[$key]['FullProtectionModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                // Check if sensor is activated for hull protection mode
                                if ($this->GetValue('HullProtectionMode')) {
                                    if ($doorWindowSensors[$key]['HullProtectionModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                // Check if sensor is activated for partial protection mode
                                if ($this->GetValue('PartialProtectionMode')) {
                                    if ($doorWindowSensors[$key]['PartialProtectionModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                if ($alerting) {
                                    // Alarm state
                                    $this->SetValue('AlarmState', 1);
                                    $this->SetValue('AlertingSensor', $sensorName);
                                    // Options
                                    if ($doorWindowSensors[$key]['UseAlarmSiren']) {
                                        $this->SetValue('AlarmSiren', true);
                                    }
                                    if ($doorWindowSensors[$key]['UseAlarmLight']) {
                                        $this->SetValue('AlarmLight', true);
                                    }
                                    if ($doorWindowSensors[$key]['UseAlarmCall']) {
                                        $this->SetValue('AlarmCall', true);
                                    }
                                    // Protocol
                                    $text = $sensorName . ' wurde geschlossen. (ID ' . $SenderID . ')';
                                    if ($open) {
                                        $text = $sensorName . ' wurde geöffnet und hat einen Alarm ausgelöst. (ID ' . $SenderID . ')';
                                    }
                                    $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                                    $this->UpdateAlarmProtocol($logText, 2);
                                } // Non alerting
                                else {
                                    // Protocol
                                    $text = $sensorName . ' wurde geschlossen. (ID ' . $SenderID . ')';
                                    if ($open) {
                                        $text = $sensorName . ' wurde ohne Alarmauslösung geöffnet. (ID ' . $SenderID . ')';
                                    }
                                    $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                                    $this->UpdateAlarmProtocol($logText, 0);
                                }
                            }
                        }
                        break;

                    case 2: # delayed armed
                    case 4: # delayed partial armed
                        if ($execute) {
                            $this->CheckDoorWindowState(true);
                        }
                        break;

                }
            }
        }
    }

    #################### Private

    private function CheckDoorWindowState(bool $UseProtocol): bool
    {
        $timeStamp = date('d.m.Y, H:i:s');
        // Check all door and window sensors
        $doorWindowState = false;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $open = false;
                if (!$doorWindowSensor->Use) {
                    continue;
                }
                $id = $doorWindowSensor->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    continue;
                }
                $type = IPS_GetVariable($id)['VariableType'];
                $value = $doorWindowSensor->TriggerValue;
                switch ($doorWindowSensor->TriggerType) {
                    case 0: # on change (bool, integer, float, string)
                    case 1: # on update (bool, integer, float, string)
                        $this->SendDebug(__FUNCTION__, 'Bei Änderung und bei Aktualisierung wird nicht berücksichtigt!', 0);
                        break;

                    case 2: # on limit drop, once (integer, float)
                    case 3: # on limit drop, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($id) < intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                    $open = true;
                                }
                                break;

                            case 2: # float
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                    $open = true;
                                }
                                break;

                        }
                        break;

                    case 4: # on limit exceed, once (integer, float)
                    case 5: # on limit exceed, every time (integer, float)
                        switch ($type) {
                            case 1: #integer
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($id) > intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                    $open = true;
                                }
                                break;

                            case 2: # float
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                    $open = true;
                                }
                                break;

                        }
                        break;

                    case 6: # on specific value, once (bool, integer, float, string)
                    case 7: # on specific value, every time (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($id) == boolval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                    $open = true;
                                }
                                break;

                            case 1: # integer
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($id) == intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (integer)', 0);
                                    $open = true;
                                }
                                break;

                            case 2: # float
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                    $open = true;
                                }
                                break;

                            case 3: # string
                                if (GetValueString($id) == (string) $value) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                    $open = true;
                                }
                                break;

                        }
                        break;

                }
                if ($open) {
                    $doorWindowState = true;
                    // Create log entry and add to blacklist
                    if ($UseProtocol) {
                        // Update blacklist
                        $this->AddSensorBlacklist($id);
                        // Protocol
                        $location = $this->ReadPropertyString('Location');
                        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
                        $sensorName = $doorWindowSensor->Name;
                        $text = $sensorName . ' ist noch geöffnet. Bitte prüfen! (ID ' . $id . ')';
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                }
            }
        }
        $this->SetValue('DoorWindowState', $doorWindowState);
        return $doorWindowState;
    }
}