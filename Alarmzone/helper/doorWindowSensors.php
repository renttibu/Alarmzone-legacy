<?php

// Declare
declare(strict_types=1);

trait AZON_doorWindowSensors
{
    /**
     * Determines the door and window state variables automatically.
     */
    public function DetermineDoorWindowVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $listedVariables = [];
        $instanceIDs = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_MODULE_GUID);
        if (!empty($instanceIDs)) {
            $variables = [];
            foreach ($instanceIDs as $instanceID) {
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
                            array_push($variables, [
                                'Name'                                                      => $name,
                                'ID'                                                        => $childrenID,
                                'AlertingValue'                                             => 1,
                                'FullProtectionModeActive'                                  => true,
                                'HullProtectionModeActive'                                  => true,
                                'PartialProtectionModeActive'                               => true,
                                'UseAlertNotification'                                      => true,
                                'UseAlarmSiren'                                             => true,
                                'UseAlarmLight'                                             => true,
                                'UseAlarmCall'                                              => true]);
                        }
                    }
                }
            }
            // Get already listed variables
            $listedVariables = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
            // Delete non existing variables anymore
            if (!empty($listedVariables)) {
                $deleteVariables = array_diff(array_column($listedVariables, 'ID'), array_column($variables, 'ID'));
                if (!empty($deleteVariables)) {
                    foreach ($deleteVariables as $key => $deleteVariable) {
                        unset($listedVariables[$key]);
                    }
                }
            }
            // Add new variables
            if (!empty($listedVariables)) {
                $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
                if (!empty($addVariables)) {
                    foreach ($addVariables as $addVariable) {
                        $name = strstr(@IPS_GetName(@IPS_GetParent($addVariable)), ':', true);
                        array_push($listedVariables, [
                            'Name'                                                      => $name,
                            'ID'                                                        => $addVariable,
                            'AlertingValue'                                             => 1,
                            'FullProtectionModeActive'                                  => true,
                            'HullProtectionModeActive'                                  => true,
                            'PartialProtectionModeActive'                               => true,
                            'UseAlertNotification'                                      => true,
                            'UseAlarmSiren'                                             => true,
                            'UseAlarmLight'                                             => true,
                            'UseAlarmCall'                                              => true]);
                    }
                }
            } else {
                $listedVariables = $variables;
            }
        }
        // Sort variables by name
        usort($listedVariables, function ($a, $b)
        {
            return $a['Name'] <=> $b['Name'];
        });
        // Rebase array
        $listedVariables = array_values($listedVariables);
        // Update variable list
        $json = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'DoorWindowSensors', $json);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Tür- / Fenstersensoren wurden automatisch ermittelt!';
    }

    /**
     * Checks the alerting of a door and window sensor.
     *
     * @param int $SenderID
     */
    public function CheckDoorWindowSensorAlerting(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        $useNotification = false;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
        if (!empty($doorWindowSensors)) {
            // Check if sensor is listed
            $key = array_search($SenderID, array_column($doorWindowSensors, 'ID'));
            if (is_int($key)) {
                $sensorName = $doorWindowSensors[$key]['Name'];
                $actualValue = boolval(GetValue($SenderID));
                $alertingValue = boolval($doorWindowSensors[$key]['AlertingValue']);
                $stateText = 'geschlossen';
                if ($actualValue == $alertingValue) {
                    $stateText = 'geöffnet';
                }
                // Check alarm zone state
                $alarmZoneState = $this->GetValue('AlarmZoneState');
                switch ($alarmZoneState) {
                    // Disarmed
                    case 0:
                        // Log
                        $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            @APRO_UpdateMessages($alarmProtocol, $logText, 0);
                        }
                        break;
                    // Armed
                    case 1:
                        $alerting = false;
                        // Check if variable is black listed
                        $blackListed = false;
                        $blackListedSensors = json_decode($this->GetBuffer('BlackList'), true);
                        if (in_array($SenderID, $blackListedSensors)) {
                            $blackListed = true;
                        }
                        // Variable is black listed
                        if ($blackListed) {
                            $messageType = 0;
                            if ($actualValue != $alertingValue) {
                                $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                            } else {
                                $text = $sensorName . ' wurde ohne Alarmauslösung ' . $stateText . '. (ID ' . $SenderID . ')';
                            }
                        }
                        // Variable is not black listed
                        else {
                            // Check alerting value
                            if ($actualValue == $alertingValue) {
                                // Check if sensor is activated for absence mode
                                if ($this->GetValue('FullProtectionMode')) {
                                    if ($doorWindowSensors[$key]['FullProtectionModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                // Check if sensor is activated for presence mode
                                if ($this->GetValue('HullProtectionMode')) {
                                    if ($doorWindowSensors[$key]['HullProtectionModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                // Check if sensor is activated for night mode
                                if ($this->GetValue('PartialProtectionMode')) {
                                    if ($doorWindowSensors[$key]['PartialProtectionModeActive']) {
                                        $alerting = true;
                                    }
                                }
                            }
                            $alarmName = 'Alarm';
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelay');
                            if ($alertingDelayDuration > 0) {
                                $alarmName = 'Voralarm';
                            }
                            if ($actualValue != $alertingValue) {
                                $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                                $messageType = 0;
                            } else {
                                $text = $sensorName . ' wurde ' . $stateText . ' und hat einen ' . $alarmName . ' ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                                $messageType = 2;
                            }
                        }
                        // Log
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            @APRO_UpdateMessages($alarmProtocol, $logText, $messageType);
                        }
                        // Check alerting
                        if ($alerting) {
                            $alarmState = 1;
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelay');
                            if ($alertingDelayDuration > 0) {
                                $alarmState = 2;
                                // Set timer
                                $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                            }
                            // Set alarm state
                            $this->SetValue('AlarmState', $alarmState);
                            // Set door and window state
                            $this->SetValue('DoorWindowState', true);
                            // Set signal lamp
                            $this->SetAlarmStateSignalLamp();
                            // Alarm siren
                            if ($doorWindowSensors[$key]['UseAlarmSiren']) {
                                $this->ToggleAlarmSiren(true);
                            }
                            // Alarm light
                            if ($doorWindowSensors[$key]['UseAlarmLight']) {
                                $this->ToggleAlarmLight(true);
                            }
                            // Notification
                            if ($doorWindowSensors[$key]['UseAlertNotification']) {
                                $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
                                $messageText = $timeStamp . ' ' . $sensorName . ' hat einen Alarm ausgelöst.';
                                $this->SendNotification($actionText, $messageText, $logText, 2);
                            }
                            // Alarm call
                            if ($doorWindowSensors[$key]['UseAlarmCall']) {
                                $this->TriggerAlarmCall($sensorName);
                            }
                        }
                        break;

                    // Delayed
                    case 2:
                        $useNotification = true;
                        break;

                }
            }
        }
        // Update door and window state
        $this->UpdateDoorWindowState($useNotification, true, true);
    }

    //##################### Blacklist

    /**
     * Resets the blacklist.
     */
    public function ResetBlackList(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $this->SetBuffer('BlackList', json_encode([]));
    }

    //#################### Private

    /**
     * Updates the door and window state.
     *
     * @param bool $UseNotification
     * false    = don't notify
     * true     = notify
     *
     * @param bool $UseSignalLamp
     * false    = don't use
     * true     = use
     *
     * @param bool $UpdateAlarmZoneControlStates
     * false    = don't use
     * true     = use
     */
    private function UpdateDoorWindowState(bool $UseNotification, bool $UseSignalLamp, bool $UpdateAlarmZoneControlStates): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        // Check all door and window sensors
        $doorWindowState = false;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $id = $doorWindowSensor->ID;
                // Check actual value and alerting value
                $actualValue = boolval(GetValue($id));
                $alertingValue = boolval($doorWindowSensor->AlertingValue);
                if ($actualValue == $alertingValue) {
                    $doorWindowState = true;
                    // Inform user, create log entry and add to blacklist
                    if ($UseNotification) {
                        // Log
                        $location = $this->ReadPropertyString('Location');
                        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
                        $sensorName = $doorWindowSensor->Name;
                        $text = $sensorName . ' ist noch geöffnet. Bitte prüfen! (ID ' . $id . ')';
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            @APRO_UpdateMessages($alarmProtocol, $logText, 0);
                        }
                        // Notification
                        $actionText = $alarmZoneName . ', ' . $sensorName . ' ist noch geöffnet!';
                        $messageText = $timeStamp . ' ' . $sensorName . ' ist noch geöffnet.';
                        $this->SendNotification($actionText, $messageText, $logText, 1);
                        // Update blacklist
                        $blackList = json_decode($this->GetBuffer('BlackList'), true);
                        array_push($blackList, $id);
                        $this->SetBuffer('BlackList', json_encode(array_unique($blackList)));
                    }
                }
            }
        }
        // Set door and window state
        $this->SetValue('DoorWindowState', $doorWindowState);
        // Set signal lamp
        if ($UseSignalLamp) {
            $this->SetDoorWindowStateSignalLamp();
        }
        // Update alarm zone control states
        if ($UpdateAlarmZoneControlStates) {
            $this->UpdateAlarmZoneControlStates();
        }
    }
}