<?php

// Declare
declare(strict_types=1);

trait AZON_doorWindowSensors
{
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
                        $objectName = $this->ReadPropertyString('ObjectName');
                        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
                        $sensorName = $doorWindowSensor->Name;
                        $text = $sensorName . ' ist noch geöffnet. Bitte prüfen! (ID ' . $id . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                            IPS_RunScriptText($scriptText);
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

    /**
     * Checks the alerting of a door and window sensor.
     *
     * @param int $SenderID
     */
    public function CheckDoorWindowSensorAlerting(int $SenderID): void
    {
        $timeStamp = date('d.m.Y, H:i:s');
        $objectName = $this->ReadPropertyString('ObjectName');
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
                    // 0: Disarmed
                    case 0:
                        // Log
                        $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                            IPS_RunScriptText($scriptText);
                        }
                        break;

                    // 1: Armed
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
                                if ($this->GetValue('AbsenceMode')) {
                                    if ($doorWindowSensors[$key]['AbsenceModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                // Check if sensor is activated for presence mode
                                if ($this->GetValue('PresenceMode')) {
                                    if ($doorWindowSensors[$key]['PresenceModeActive']) {
                                        $alerting = true;
                                    }
                                }
                                // Check if sensor is activated for night mode
                                if ($this->GetValue('NightMode')) {
                                    if ($doorWindowSensors[$key]['NightModeActive']) {
                                        $alerting = true;
                                    }
                                }
                            }
                            $alarmName = 'Alarm';
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayDuration');
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
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", ' . $messageType . ');';
                            IPS_RunScriptText($scriptText);
                        }
                        // Check alerting
                        if ($alerting) {
                            $alarmState = 1;
                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayDuration');
                            if ($alertingDelayDuration > 0) {
                                $alarmState = 2;
                                // Set timer
                                $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                            }
                            // Set alarm state
                            $this->SetValue('AlarmState', $alarmState);
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
                                $this->ExecuteAlarmCall($sensorName);
                            }
                        }
                        break;

                    case 2:
                        $useNotification = true;
                        break;

                }
            }
        }

        // Update door and window state
        $this->UpdateDoorWindowState($useNotification, true, true);
    }

    //#################### Registered door and window sensors

    /**
     * Displays the registered door and window sensors.
     */
    public function DisplayRegisteredDoorWindowSensors(): void
    {
        $registeredDoorWindowSensors = [];
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $id => $registeredVariable) {
            foreach ($registeredVariable as $messageType) {
                if ($messageType == VM_UPDATE) {
                    // Door and window sensors
                    $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                    if (!empty($doorWindowSensors)) {
                        $key = array_search($id, array_column($doorWindowSensors, 'ID'));
                        if (is_int($key)) {
                            $name = $doorWindowSensors[$key]['Name'];
                            array_push($registeredDoorWindowSensors, ['id' => $id, 'name' => $name]);
                        }
                    }
                }
            }
        }
        sort($registeredDoorWindowSensors);
        echo "\n\nRegistrierte Tür- / Fenstersensoren:\n\n";
        print_r($registeredDoorWindowSensors);
    }

    //##################### Blacklist

    /**
     * Resets the blacklist.
     */
    public function ResetBlackList(): void
    {
        $this->SetBuffer('BlackList', json_encode([]));
    }

    /**
     * Displays the blacklist.
     */
    public function DisplayBlackList(): void
    {
        $buffer = $this->GetBuffer('BlackList');
        echo "Buffer string:\n" . $buffer . "\n\n";

        $array = json_decode($buffer, true);
        print_r($array);
    }

    /**
     * Displays the black listed door and window sensors.
     */
    public function DisplayBlackListedDoorWindowSensors(): void
    {
        $variables = [];
        $blackListedSensors = json_decode($this->GetBuffer('BlackList'), true);
        if (!empty($blackListedSensors)) {
            foreach ($blackListedSensors as $blackListedSensor) {
                // Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    $key = array_search($blackListedSensor, array_column($doorWindowSensors, 'ID'));
                    if (is_int($key)) {
                        $sensorName = $doorWindowSensors[$key]['Name'];
                        array_push($variables, ['id' => $blackListedSensor, 'name' => $sensorName]);
                    }
                }
            }
        }
        sort($variables);

        echo "Gesperrte Tür- / Fenstersensoren:\n\n";
        print_r($variables);
    }
}