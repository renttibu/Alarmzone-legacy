<?php

// Declare
declare(strict_types=1);

trait AZON_motionDetectors
{
    /**
     * Determines the door and window state variables automatically.
     */
    public function DetermineMotionDetectorVariables(): void
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
                    if ($object['ObjectIdent'] == 'MOTION') {
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
                                'PermanentMotionDetection'                                  => true,
                                'FullProtectionModeActive'                                  => true,
                                'HullProtectionModeActive'                                  => false,
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
            $listedVariables = json_decode($this->ReadPropertyString('MotionDetectors'), true);
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
                            'PermanentMotionDetection'                                  => true,
                            'FullProtectionModeActive'                                  => true,
                            'HullProtectionModeActive'                                  => false,
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
        @IPS_SetProperty($this->InstanceID, 'MotionDetectors', $json);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Bewegungsmelder wurden automatisch ermittelt!';
    }

    /**
     * Checks the alerting of a motion detector.
     *
     * @param int $SenderID
     */
    public function CheckMotionDetectorAlerting(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
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
                    // Armed
                    case 1:
                        // Check alerting value
                        if ($actualValue == $alertingValue) {
                            $alerting = false;
                            // Check if motion detector is activated for absence mode
                            if ($this->GetValue('FullProtectionMode')) {
                                if ($motionDetectors[$key]['FullProtectionModeActive']) {
                                    $alerting = true;
                                }
                            }
                            // Check if motion detector is activated for presence mode
                            if ($this->GetValue('HullProtectionMode')) {
                                if ($motionDetectors[$key]['HullProtectionModeActive']) {
                                    $alerting = true;
                                }
                            }
                            // Check if motion detector is activated for night mode
                            if ($this->GetValue('PartialProtectionMode')) {
                                if ($motionDetectors[$key]['PartialProtectionModeActive']) {
                                    $alerting = true;
                                }
                            }
                            // Check alerting
                            if ($alerting) {
                                $alarmState = 1;
                                $alarmName = 'Alarm';
                                $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelay');
                                if ($alertingDelayDuration > 0) {
                                    $alarmState = 2;
                                    $alarmName = 'Voralarm';
                                    $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                                }
                                // Log
                                $text = $detectorName . ' hat eine Bewegung erkannt und einen ' . $alarmName . ' ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                                    @APRO_UpdateMessages($alarmProtocol, $logText, 2);
                                }
                                // Set alarm state
                                $this->SetValue('AlarmState', $alarmState);
                                // Set motion detector state
                                $this->SetValue('MotionDetectorState', true);
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
                                    $this->TriggerAlarmCall($detectorName);
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

    //#################### Private

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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
}