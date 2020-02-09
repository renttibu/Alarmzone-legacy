<?php

// Declare
declare(strict_types=1);

trait AZON_smokeDetectors
{
    /**
     * Checks the alerting of a smoke detector.
     *
     * @param int $SenderID
     */
    public function CheckSmokeDetectorAlerting(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'), true);
        if (!empty($smokeDetectors)) {
            // Check if motion detector is listed
            $key = array_search($SenderID, array_column($smokeDetectors, 'ID'));
            if (is_int($key)) {
                $detectorName = $smokeDetectors[$key]['Name'];
                $actualValue = boolval(GetValue($SenderID));
                $alertingValue = boolval($smokeDetectors[$key]['AlertingValue']);
                // Check alerting value
                if ($actualValue == $alertingValue) {
                    $alarmState = 1;
                    $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelay');
                    if ($alertingDelayDuration > 0) {
                        $alarmState = 2;
                    }
                    // Log
                    $text = $detectorName . ' hat Rauch erkannt und einen Alarm ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                    $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                    if ($alarmProtocol != 0 && IPS_ObjectExists($alarmProtocol)) {
                        @APRO_UpdateMessages($alarmProtocol, $logText, 2);
                    }
                    // Set alarm state
                    $this->SetValue('AlarmState', $alarmState);
                    // Set signal lamp
                    $this->SetAlarmStateSignalLamp();
                    // Alarm siren
                    if ($smokeDetectors[$key]['UseAlarmSiren']) {
                        // Set alarm siren switch
                        $this->ToggleAlarmSiren(true);
                    }
                    // Alarm light
                    if ($smokeDetectors[$key]['UseAlarmLight']) {
                        $this->ToggleAlarmLight(true);
                    }
                    // Notification
                    if ($smokeDetectors[$key]['UseAlertNotification']) {
                        $actionText = $alarmZoneName . ', Alarm ' . $detectorName . '!';
                        $messageText = $timeStamp . ' ' . $detectorName . ' hat einen Alarm ausgelöst.';
                        $this->SendNotification($actionText, $messageText, $logText, 2);
                    }
                    // Alarm call
                    if ($smokeDetectors[$key]['UseAlarmCall']) {
                        $this->TriggerAlarmCall($detectorName);
                    }
                }
            }
        }
        // Update smoke detector state
        $this->UpdateSmokeDetectorState(true, true);
    }

    //#################### Private

    /**
     * Updates the smoke detectors state.
     *
     * @param bool $UseSignalLamp
     * false    = don't use
     * true     = use
     *
     * @param bool $UpdateAlarmZoneControlStates
     * false    = don't use
     * true     = use
     */
    private function UpdateSmokeDetectorState(bool $UseSignalLamp, bool $UpdateAlarmZoneControlStates): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $smokeState = false;
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($smokeDetectors)) {
            foreach ($smokeDetectors as $smokeDetector) {
                $id = $smokeDetector->ID;
                // Check actual value and alerting value
                $actualValue = boolval(GetValue($id));
                $alertingValue = boolval($smokeDetector->AlertingValue);
                if ($actualValue == $alertingValue) {
                    $smokeState = true;
                }
            }
        }
        // Set smoke detector state
        $this->SetValue('SmokeDetectorState', $smokeState);
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