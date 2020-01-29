<?php

// Declare
declare(strict_types=1);

trait AZON_motionDetectors
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
    private function UpdateMotionDetectorState(bool $UseSignalLamp, bool $UpdateAlarmZoneControlStates): void
    {
        $motionState = false;
        $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($motionDetectors)) {
            foreach ($motionDetectors as $motionDetector) {
                $id = $motionDetector->ID;
                // Check actual value and alerting value
                $actualValue = boolval(GetValue($id));
                $alertingValue = boolval($motionDetector->AlertingValue);
                if ($actualValue == $alertingValue) {
                    $motionState = true;
                }
            }
        }

        // Set motion detector state
        $this->SetValue('MotionDetectorState', $motionState);

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
     * Checks the alerting of a motion detector.
     *
     * @param int $SenderID
     */
    public function CheckMotionDetectorAlerting(int $SenderID): void
    {
        $timeStamp = date('d.m.Y, H:i:s');
        $objectName = $this->ReadPropertyString('ObjectName');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'), true);
        if (!empty($motionDetectors)) {
            // Check if motion detector is listed
            $key = array_search($SenderID, array_column($motionDetectors, 'ID'));
            if (is_int($key)) {
                $detectorName = $motionDetectors[$key]['Name'];
                $actualValue = boolval(GetValue($SenderID));
                $alertingValue = boolval($motionDetectors[$key]['AlertingValue']);
                // Check alarm zone state
                $alarmZoneState = $this->GetValue('AlarmZoneState');
                switch ($alarmZoneState) {
                    // 1: Armed
                    case 1:
                        // Check alerting value
                        if ($actualValue == $alertingValue) {
                            $alerting = false;
                            // Check if motion detector is activated for absence mode
                            if ($this->GetValue('AbsenceMode')) {
                                if ($motionDetectors[$key]['AbsenceModeActive']) {
                                    $alerting = true;
                                }
                            }
                            // Check if motion detector is activated for presence mode
                            if ($this->GetValue('PresenceMode')) {
                                if ($motionDetectors[$key]['PresenceModeActive']) {
                                    $alerting = true;
                                }
                            }
                            // Check if motion detector is activated for night mode
                            if ($this->GetValue('NightMode')) {
                                if ($motionDetectors[$key]['NightModeActive']) {
                                    $alerting = true;
                                }
                            }
                            // Check alerting
                            if ($alerting) {
                                $alarmState = 1;
                                $alarmName = 'Alarm';
                                $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayDuration');
                                if ($alertingDelayDuration > 0) {
                                    $alarmState = 2;
                                    $alarmName = 'Voralarm';
                                    $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                                }
                                // Log
                                $text = $detectorName . ' hat eine Bewegung erkannt und einen ' . $alarmName . ' ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                                $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                                    $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 2);';
                                    IPS_RunScriptText($scriptText);
                                }
                                // Set alarm state
                                $this->SetValue('AlarmState', $alarmState);
                                // Set signal lamp
                                $this->SetAlarmStateSignalLamp();
                                // Alarm siren
                                if ($motionDetectors[$key]['UseAlarmSiren']) {
                                    // Set alarm siren switch
                                    $this->ToggleAlarmSiren(true);
                                }
                                // Alarm light
                                if ($motionDetectors[$key]['UseAlarmLight']) {
                                    $this->ToggleAlarmLight(true);
                                }
                                // Notification
                                if ($motionDetectors[$key]['UseAlertNotification']) {
                                    $actionText = $alarmZoneName . ', Alarm ' . $detectorName . '!';
                                    $messageText = $timeStamp . ' ' . $detectorName . ' hat einen Alarm ausgelöst.';
                                    $this->SendNotification($actionText, $messageText, $logText, 2);
                                }
                                // Alarm call
                                if ($motionDetectors[$key]['UseAlarmCall']) {
                                    $this->ExecuteAlarmCall($detectorName);
                                }
                            }
                        }
                        break;

                }
            }
        }

        // Update motion detector state
        $this->UpdateMotionDetectorState(true, true);
    }

    /**
     * Displays the registered motion detectors.
     */
    public function DisplayRegisteredMotionDetectors(): void
    {
        $registeredMotionDetectors = [];
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $id => $registeredVariable) {
            foreach ($registeredVariable as $messageType) {
                if ($messageType == VM_UPDATE) {
                    // Motion detectors
                    $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'), true);
                    if (!empty($motionDetectors)) {
                        $key = array_search($id, array_column($motionDetectors, 'ID'));
                        if (is_int($key)) {
                            $name = $motionDetectors[$key]['Name'];
                            array_push($registeredMotionDetectors, ['id' => $id, 'name' => $name]);
                        }
                    }
                }
            }
        }
        sort($registeredMotionDetectors);

        echo "\n\nRegistrierte Bewegungsmelder:\n\n";
        print_r($registeredMotionDetectors);
    }
}