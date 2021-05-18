<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzone
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait AZ_motionDetectors
{
    public function DetermineMotionDetectorVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $variables = [];
        $instanceIDs = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
        if (!empty($instanceIDs)) {
            foreach ($instanceIDs as $instanceID) {
                $childrenIDs = @IPS_GetChildrenIDs($instanceID);
                foreach ($childrenIDs as $childrenID) {
                    $match = false;
                    $object = @IPS_GetObject($childrenID);
                    if ($object['ObjectIdent'] == 'MOTION') {
                        $match = true;
                    }
                    if ($match) {
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
                                'Trigger'                     => 6,
                                'Value'                       => $triggerValue,
                                'FullProtectionModeActive'    => true,
                                'HullProtectionModeActive'    => false,
                                'PartialProtectionModeActive' => true,
                                'UseNotification'             => true,
                                'UseAlarmSiren'               => true,
                                'UseAlarmLight'               => true,
                                'UseAlarmCall'                => true]);
                        }
                    }
                }
            }
        }
        // Get already listed variables
        $listedVariables = json_decode($this->ReadPropertyString('MotionDetectors'), true);
        // Add new variables
        if (!empty($listedVariables)) {
            $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
            if (!empty($addVariables)) {
                foreach ($addVariables as $addVariable) {
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
                        'Trigger'                     => 6,
                        'Value'                       => $triggerValue,
                        'FullProtectionModeActive'    => true,
                        'HullProtectionModeActive'    => true,
                        'PartialProtectionModeActive' => true,
                        'UseNotification'             => true,
                        'UseAlarmSiren'               => true,
                        'UseAlarmLight'               => true,
                        'UseAlarmCall'                => true]);
                }
            }
        } else {
            $listedVariables = $variables;
        }
        // Sort variables by name
        array_multisort(array_column($listedVariables, 'Name'), SORT_ASC, $listedVariables);
        $listedVariables = array_values($listedVariables);
        // Update variable list
        $value = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'MotionDetectors', $value);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Bewegungsmelder wurden automatisch ermittelt!';
    }

    public function CheckMotionDetectorAlerting(int $SenderID, bool $ValueChanged): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Sender: ' . $SenderID . ', Wert hat sich geändert: ' . json_encode($ValueChanged), 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('MotionDetectors'), true);
        if (empty($vars)) {
            return false;
        }
        $key = array_search($SenderID, array_column($vars, 'ID'));
        if (!is_int($key)) {
            return false;
        }
        if (!$vars[$key]['Use']) {
            return false;
        }
        $execute = false;
        $motionDetected = false;
        $type = IPS_GetVariable($SenderID)['VariableType'];
        $value = $vars[$key]['Value'];
        switch ($vars[$key]['Trigger']) {
            case 0: # on change (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                if ($ValueChanged) {
                    $execute = true;
                    $motionDetected = true;
                }
                break;

            case 1: # on update (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                $execute = true;
                $motionDetected = true;
                break;

            case 2: # on limit drop, once (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) < intval($value)) {
                                $motionDetected = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                $motionDetected = true;
                            }
                        }
                        break;

                }
                break;

            case 3: # on limit drop, every time (integer, float)
                switch ($type) {
                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) < intval($value)) {
                            $motionDetected = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                            $motionDetected = true;
                        }
                        break;

                }
                break;

            case 4: # on limit exceed, once (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) > intval($value)) {
                                $motionDetected = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                $motionDetected = true;
                            }
                        }
                        break;

                }
                break;

            case 5: # on limit exceed, every time (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) > intval($value)) {
                            $motionDetected = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                            $motionDetected = true;
                        }
                        break;

                }
                break;

            case 6: # on specific value, once (bool, integer, float, string)
                switch ($type) {
                    case 0: # bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if (GetValueBoolean($SenderID) == boolval($value)) {
                                $motionDetected = true;
                            }
                        }
                        break;

                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) == intval($value)) {
                                $motionDetected = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                $motionDetected = true;
                            }
                        }
                        break;

                    case 3: # string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueString($SenderID) == (string) $value) {
                                $motionDetected = true;
                            }
                        }
                        break;

                }
                break;

            case 7: # on specific value, every time (bool, integer, float, string)
                switch ($type) {
                    case 0: # bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if (GetValueBoolean($SenderID) == boolval($value)) {
                            $motionDetected = true;
                        }
                        break;

                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) == intval($value)) {
                            $motionDetected = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                            $motionDetected = true;
                        }
                        break;

                    case 3: # string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                        $execute = true;
                        if (GetValueString($SenderID) == (string) $value) {
                            $motionDetected = true;
                        }
                        break;

                }
                break;

        }
        $this->SendDebug(__FUNCTION__, 'Bedingung erfüllt: ' . json_encode($execute), 0);
        $this->SendDebug(__FUNCTION__, 'Bewegung erkannt: ' . json_encode($motionDetected), 0);
        // Check alarm zone state
        $sensorName = $vars[$key]['Name'];
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmZoneState = $this->GetValue('AlarmZoneState');
        switch ($alarmZoneState) {
            case 0: # disarmed
                if ($execute) {
                    $this->CheckMotionDetectorState();
                }
                break;

            case 1: # armed
                if ($execute) {
                    $this->CheckMotionDetectorState();
                }
                $alerting = false;
                $alertingDelayDuration = 0;
                $alertingMode = 0;
                if ($execute && $motionDetected) {
                    // Check if sensor is activated for full protection mode
                    if ($this->GetValue('FullProtectionMode')) {
                        if ($vars[$key]['FullProtectionModeActive']) {
                            $alerting = true;
                            $alertingMode = 1;
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayFullProtectionMode');
                        }
                    }
                    // Check if sensor is activated for hull protection mode
                    if ($this->GetValue('HullProtectionMode')) {
                        if ($vars[$key]['HullProtectionModeActive']) {
                            $alerting = true;
                            $alertingMode = 2;
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayHullProtectionMode');
                        }
                    }
                    // Check if sensor is activated for partial protection mode
                    if ($this->GetValue('PartialProtectionMode')) {
                        if ($vars[$key]['PartialProtectionModeActive']) {
                            $alerting = true;
                            $alertingMode = 3;
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayPartialProtectionMode');
                        }
                    }
                    if ($alerting) {
                        // Pre Alarm
                        if ($alertingDelayDuration > 0) {
                            // Alarm state
                            $this->SetValue('AlarmState', 2);
                            $this->SetValue('AlertingSensor', $sensorName);
                            $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                            // Buffer
                            $alertingSensor = json_encode([
                                'id'                => $SenderID,
                                'name'              => $sensorName,
                                'alertingMode'      => $alertingMode,
                                'fullProtection'    => $vars[$key]['FullProtectionModeActive'],
                                'hullProtection'    => $vars[$key]['HullProtectionModeActive'],
                                'partialProtection' => $vars[$key]['PartialProtectionModeActive'],
                                'useNotification'   => $vars[$key]['UseNotification'],
                                'useAlarmSiren'     => $vars[$key]['UseAlarmSiren'],
                                'useAlarmLight'     => $vars[$key]['UseAlarmLight'],
                                'useAlarmCall'      => $vars[$key]['UseAlarmCall']]);
                            $this->SetBuffer('LastAlertingSensor', $alertingSensor);
                            // Log
                            $text = $sensorName . ' hat eine Bewegung erkannt und einen Voralarm ausgelöst. (ID ' . $SenderID . ')';
                            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                            $this->UpdateAlarmProtocol($logText, 2);
                            // Notification
                            if ($vars[$key]['UseNotification']) {
                                $actionText = $alarmZoneName . ', Voralarm ' . $sensorName . '!';
                                $alarmSymbol = $this->ReadPropertyString('PreAlarmSymbol');
                                if (!empty($alarmSymbol)) {
                                    $actionText = $alarmSymbol . ' ' . $alarmZoneName . ', Voralarm ' . $sensorName . '!';
                                }
                                $messageText = $timeStamp . ' ' . $sensorName . ' hat einen Voralarm ausgelöst.';
                                $this->SendNotification($actionText, $messageText, $logText, 2);
                            }
                        } // Alarm
                        else {
                            // Alarm state
                            $this->SetValue('AlarmState', 1);
                            $this->SetValue('AlertingSensor', $sensorName);
                            // Log
                            $text = $sensorName . ' hat eine Bewegung erkannt und einen Alarm ausgelöst. (ID ' . $SenderID . ')';
                            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                            $this->UpdateAlarmProtocol($logText, 2);
                            // Options
                            if ($vars[$key]['UseAlarmSiren']) {
                                $this->SetValue('AlarmSiren', true);
                            }
                            if ($vars[$key]['UseAlarmLight']) {
                                $this->SetValue('AlarmLight', true);
                            }
                            if ($vars[$key]['UseAlarmCall']) {
                                $this->SetValue('AlarmCall', true);
                            }
                            // Notification
                            if ($vars[$key]['UseNotification']) {
                                $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
                                $alarmSymbol = $this->ReadPropertyString('AlarmSymbol');
                                if (!empty($alarmSymbol)) {
                                    $actionText = $alarmSymbol . ' ' . $alarmZoneName . ', Alarm ' . $sensorName . '!';
                                }
                                $messageText = $timeStamp . ' ' . $sensorName . ' hat eine Bewegung erkannt und einen Alarm ausgelöst.';
                                $this->SendNotification($actionText, $messageText, $logText, 2);
                            }
                        }
                    }
                }
                break;

            case 2: # delayed
                if ($execute) {
                    $this->CheckMotionDetectorState();
                }
                break;

        }
        return $motionDetected;
    }

    #################### Private

    private function CheckMotionDetectorState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $state = false;
        $vars = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                if (!$var->Use) {
                    continue;
                }
                $id = $var->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    continue;
                }
                $type = IPS_GetVariable($id)['VariableType'];
                $value = $var->Value;
                switch ($var->Trigger) {
                    case 0: # on change (bool, integer, float, string)
                    case 1: # on update (bool, integer, float, string)
                        $this->SendDebug(__FUNCTION__, 'Bei Änderung und bei Aktualisierung wird nicht berücksichtigt!', 0);
                        break;

                    case 2: # on limit drop, once (integer, float)
                    case 3: # on limit drop, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($id) < intval($value)) {
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                    $state = true;
                                }
                                break;

                        }
                        break;

                    case 4: # on limit exceed, once (integer, float)
                    case 5: # on limit exceed, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($id) > intval($value)) {
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                    $state = true;
                                }
                                break;

                        }
                        break;

                    case 6: # on specific value, once (bool, integer, float, string)
                    case 7: # on specific value, every time (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($id) == boolval($value)) {
                                    $state = true;
                                }
                                break;

                            case 1: # integer
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (integer)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($id) == intval($value)) {
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                    $state = true;
                                }
                                break;

                            case 3: # string
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                if (GetValueString($id) == (string) $value) {
                                    $state = true;
                                }
                                break;

                        }
                        break;

                }
            }
        }
        $this->SetValue('MotionDetectorState', $state);
        return $state;
    }
}