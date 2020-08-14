<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AZON_waterSensors
{
    /**
     * Checks the alerting of a water sensor.
     *
     * @param int $SenderID
     * @throws Exception
     */
    public function CheckWaterSensorAlerting(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
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
                    $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelay');
                    if ($alertingDelayDuration > 0) {
                        $alarmState = 2;
                    }
                    // Log
                    $text = $sensorName . ' hat Wasser erkannt und einen Alarm ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                    $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                    if ($alarmProtocol != 0 && IPS_ObjectExists($alarmProtocol)) {
                        @APRO_UpdateMessages($alarmProtocol, $logText, 2);
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
                        $this->TriggerAlarmCall($sensorName);
                    }
                }
            }
        }
        // Update water sensor state
        $this->UpdateWaterSensorState(true, true);
    }

    #################### Private

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
     * @throws Exception
     */
    private function UpdateWaterSensorState(bool $UseSignalLamp, bool $UpdateAlarmZoneControlStates): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
}