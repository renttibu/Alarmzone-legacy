<?php

// Declare
declare(strict_types=1);

trait AZON_waterSensors
{
    /**
     * Updates the motion detectors state.
     *
     * @param bool $UseSignalLamp
     * false    = don't use
     * true     = use
     *
     * @param bool $UpdateAlarmZoneControlStates
     * false    = don't use
     * true     = use
     */
    private function UpdateWaterSensorState(bool $UseSignalLamp, bool $UpdateAlarmZoneControlStates): void
    {
        $waterSensorState = false;
        $waterSensors = json_decode($this->ReadPropertyString('WaterSensors'));
        if (!empty($waterSensors)) {
            foreach ($waterSensors as $waterSensor) {
                $id = $waterSensor->ID;
                // Check actual value and alerting value
                $actualValue = boolval(GetValue($id));
                $alertingValue = boolval($waterSensor->AlertingValue);
                if ($actualValue == $alertingValue) {
                    $waterSensorState = true;
                }
            }
        }

        // Set water sensor state
        $this->SetValue('WaterSensorState', $waterSensorState);

        // Set signal lamp
        if ($UseSignalLamp) {
            $this->SetAlarmStateSignalLamp();
        }

        // Update alarm zone control states
        if ($UpdateAlarmZoneControlStates) {
            $this->UpdateAlarmZoneControlStates();
        }
    }

    /**
     * Checks the alerting of a water sensor.
     *
     * @param int $SenderID
     */
    public function CheckWaterSensorAlerting(int $SenderID): void
    {
        $timeStamp = date('d.m.Y, H:i:s');
        $objectName = $this->ReadPropertyString('ObjectName');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        $waterSensors = json_decode($this->ReadPropertyString('WaterSensors'), true);
        if (!empty($waterSensors)) {
            // Check if motion detector is listed
            $key = array_search($SenderID, array_column($waterSensors, 'ID'));
            if (is_int($key)) {
                $sensorName = $waterSensors[$key]['Name'];
                $actualValue = boolval(GetValue($SenderID));
                $alertingValue = boolval($waterSensors[$key]['AlertingValue']);
                // Check alerting value
                if ($actualValue == $alertingValue) {
                    $alarmState = 1;
                    $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayDuration');
                    if ($alertingDelayDuration > 0) {
                        $alarmState = 2;
                    }
                    // Log
                    $text = $sensorName . ' hat Wasser erkannt und einen Alarm ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                    $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                    if ($alarmProtocol != 0 && IPS_ObjectExists($alarmProtocol)) {
                        $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 2);';
                        IPS_RunScriptText($scriptText);
                    }

                    // Set alarm state
                    $this->SetValue('AlarmState', $alarmState);
                    // Set signal lamp
                    $this->SetAlarmStateSignalLamp();
                    // Alarm siren
                    if ($waterSensors[$key]['UseAlarmSiren']) {
                        // Set alarm siren switch
                        $this->ToggleAlarmSiren(true);
                    }
                    // Alarm light
                    if ($waterSensors[$key]['UseAlarmLight']) {
                        $this->ToggleAlarmLight(true);
                    }
                    // Notification
                    if ($waterSensors[$key]['UseAlertNotification']) {
                        $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
                        $messageText = $timeStamp . ' ' . $sensorName . ' hat einen Alarm ausgelöst.';
                        $this->SendNotification($actionText, $messageText, $logText, 2);
                    }
                    // Alarm call
                    if ($waterSensors[$key]['UseAlarmCall']) {
                        $this->ExecuteAlarmCall($sensorName);
                    }
                }
            }
        }

        // Update water sensor state
        $this->UpdateWaterSensorState(true, true);
    }

    /**
     * Displays the registered water sensors.
     */
    public function DisplayRegisteredWaterSensors(): void
    {
        $registeredWaterSensors = [];
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $id => $registeredVariable) {
            foreach ($registeredVariable as $messageType) {
                if ($messageType == VM_UPDATE) {
                    // Water sensors
                    $waterSensors = json_decode($this->ReadPropertyString('WaterSensors'), true);
                    if (!empty($waterSensors)) {
                        $key = array_search($id, array_column($waterSensors, 'ID'));
                        if (is_int($key)) {
                            $name = $waterSensors[$key]['Name'];
                            array_push($registeredWaterSensors, ['id' => $id, 'name' => $name]);
                        }
                    }
                }
            }
        }
        sort($registeredWaterSensors);

        echo "\n\nRegistrierte Wassersensoren:\n\n";
        print_r($registeredWaterSensors);
    }
}