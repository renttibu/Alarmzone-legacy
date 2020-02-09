<?php

// Declare
declare(strict_types=1);

trait AZON_controlOptions
{
    /**
     * Checks all registered door and window sensors for activation mode.
     *
     * @param int $Mode
     * 1    = Absence Mode
     * 2    = Presence Mode
     * 3    = Night Mode
     *
     * @return bool
     * false    = no activation
     * true     = activate
     */
    public function CheckActivation(int $Mode): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $activation = true;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                // Check if sensor is activated for the requested mode
                switch ($Mode) {
                    // Full protection mode
                    case 1:
                        $mode = $doorWindowSensor->FullProtectionModeActive;
                        break;

                    // Hull protection mode
                    case 2:
                        $mode = $doorWindowSensor->HullProtectionModeActive;
                        break;

                    // Partial protection mode
                    case 3:
                        $mode = $doorWindowSensor->PartialProtectionModeActive;
                        break;

                    default:
                        $mode = false;
                }
                if ($mode) {
                    $id = $doorWindowSensor->ID;
                    // Check actual value and alerting value
                    $actualValue = boolval(GetValue($id));
                    $alertingValue = boolval($doorWindowSensor->AlertingValue);
                    if ($actualValue == $alertingValue) {
                        $activation = false;
                    }
                }
            }
        }
        return $activation;
    }

    /**
     * Activates the alarm zone after delay.
     */
    public function StartActivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        // Check alarm zone state first
        if ($this->GetValue('AlarmZoneState') == 2) {
            // Set new alarm zone state
            $this->SetValue('AlarmZoneState', 1);
            // Get activation mode
            $text = 'Es ist ein unbekannter Status bei der verzögerten Aktivierung aufgetreten.  (ID ' . $this->InstanceID . ')';
            $modeName = $this->ReadPropertyString('AlarmZoneName');
            if ($this->GetValue('FullProtectionMode')) {
                $modeName = $this->ReadPropertyString('FullProtectionName');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('FullProtectionMode') . ')';
            }
            if ($this->GetValue('HullProtectionMode')) {
                $modeName = $this->ReadPropertyString('HullProtectionName');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('HullProtectionMode') . ')';
            }
            if ($this->GetValue('PartialProtectionMode')) {
                $modeName = $this->ReadPropertyString('PartialProtectionName');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('PartialProtectionMode') . ')';
            }
            // Log
            $location = $this->ReadPropertyString('Location');
            $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
            if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                @APRO_UpdateMessages($alarmProtocol, $logText, 1);
            }
            // Notification
            $actionText = $alarmZoneName . ' scharf!';
            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
            $this->SendNotification($actionText, $messageText, $logText, 1);
            // Tone acknowledgement
            $this->TriggerToneAcknowledgement();
            // Signal lamp
            $this->SetSignalLamps();
            // Update alarm zone control states
            $this->UpdateAlarmZoneControlStates();
        }
        // Deactivate timer
        $this->DeactivateStartActivationTimer();
    }

    /**
     * Disables the start activation timer.
     */
    public function DeactivateStartActivationTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Disable timer
        $this->SetTimerInterval('StartActivation', 0);
    }

    /**
     * Disarms the alarm zone.
     *
     * @param string $SenderID
     *
     * @param bool $UseToneAcknowledgement
     * false    = don't use
     * true     = use
     *
     * @param bool $UseNotification
     * false    = don't use
     * true     = use
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function DisarmAlarmZone(string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Set switches and states
        $this->SetValue('FullProtectionMode', false);
        $this->SetValue('HullProtectionMode', false);
        $this->SetValue('PartialProtectionMode', false);
        $this->SetValue('AlarmSiren', false);
        $this->SetValue('AlarmZoneState', 0);
        $actualAlarmState = $this->GetValue('AlarmState');
        $this->SetValue('AlarmState', 0);
        // Set text
        $systemName = $this->ReadPropertyString('SystemName');
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $text = $systemName . ' deaktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent('FullProtectionMode') . ')';
        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
        // Log
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
            @APRO_UpdateMessages($alarmProtocol, $logText, 1);
        }
        // Tone Acknowledgement
        if ($UseToneAcknowledgement) {
            $this->TriggerToneAcknowledgement();
        }
        // Notification
        if ($UseNotification) {
            $actionText = $alarmZoneName . ' unscharf!';
            $messageText = $timeStamp . ' ' . $systemName . ' deaktiviert.';
            $this->SendNotification($actionText, $messageText, $logText, 1);
        }
        // Confirm alarm notification
        $this->ConfirmAlarmNotification();
        // Disable timer
        $this->DeactivateStartActivationTimer();
        // Reset blacklist
        $this->ResetBlackList();
        // Alarm light
        //$this->ToggleAlarmLight(false);
        // Alarm call
        $this->CancelAlarmCall();
        // Check door and window sensors
        $this->UpdateDoorWindowState(false, true, true);
        // Signal lamps
        $this->SetSignalLamps();
        // Turn off alarm siren
        if ($actualAlarmState != 0) {
            $this->ToggleAlarmSiren(false);
        }
        // Update alarm zone control states
        $this->UpdateAlarmZoneControlStates();
        return $result;
    }

    /**
     * Toggles the full protect mode.
     *
     * @param bool $State
     * false    = disarm
     * true     = arm
     *
     * @param string $SenderID
     *
     * @param bool $UseToneAcknowledgement
     * false    = don't use
     * true     = use
     *
     * @param bool $UseNotification
     * false    = don't use
     * true     = use
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function ToggleFullProtectMode(bool $State, string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID, $UseToneAcknowledgement, $UseNotification);
        }
        // Arm
        if ($State) {
            $result = $this->ArmAlarmZone($SenderID, 1, $UseToneAcknowledgement, $UseNotification);
        }
        return $result;
    }

    /**
     * Toggles the hull protect mode.
     *
     * @param bool $State
     * false = disarm
     * true = arm
     *
     * @param string $SenderID
     *
     * @param bool $UseToneAcknowledgement
     * false    = don't use
     * true     = use
     *
     * @param bool $UseNotification
     * false    = don't use
     * true     = use
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function ToggleHullProtectMode(bool $State, string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID, $UseToneAcknowledgement, $UseNotification);
        }
        // Arm
        if ($State) {
            $result = $this->ArmAlarmZone($SenderID, 2, $UseToneAcknowledgement, $UseNotification);
        }
        return $result;
    }

    /**
     * Toggles the partial protect mode.
     *
     * @param bool $State
     * false = disarm
     * true = arm
     *
     * @param string $SenderID
     *
     * @param bool $UseToneAcknowledgement
     * false    = don't use
     * true     = use
     *
     * @param bool $UseNotification
     * false    = don't use
     * true     = use
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function TogglePartialProtectMode(bool $State, string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZone($SenderID, $UseToneAcknowledgement, $UseNotification);
        }
        // Arm
        if ($State) {
            $result = $this->ArmAlarmZone($SenderID, 3, $UseToneAcknowledgement, $UseNotification);
        }
        return $result;
    }

    /**
     * Sets the alarm state to alarm.
     */
    public function SetAlarmState(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Disable Timer
        $this->SetTimerInterval('SetAlarmState', 0);
        // Set alarm state
        $alarmState = $this->GetValue('AlarmState');
        if ($alarmState != 0) {
            $this->SetValue('AlarmState', 1);
        }
        // Set signal lamp
        $this->SetSignalLamps();
    }

    //#################### Private

    /**
     * Arms the alarm zone.
     *
     * @param string $SenderID
     *
     * @param int $Mode
     * 1    = FullProtectionMode
     * 2    = HullProtectionMode
     * 3    = PartialProtectionMode
     *
     * @param bool $UseToneAcknowledgement
     * false    = don't use
     * true     = use
     *
     * @param bool $UseNotification
     * false    = don't use
     * true     = use
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    private function ArmAlarmZone(string $SenderID, int $Mode, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        switch ($Mode) {
            // Hull protection mode
            case 2:
                $checkActivationModeName = 'CheckHullProtectionModeActivation';
                $identName = 'HullProtectionMode';
                $modeName = $this->ReadPropertyString('HullProtectionName');
                $activationDelayName = 'HullProtectionModeActivationDelay';
                $fullProtectionState = false;
                $hullProtectionState = true;
                $partialProtectionState = false;
                break;

            // Partial protection mode
            case 3:
                $checkActivationModeName = 'CheckPartialProtectionModeActivation';
                $identName = 'PartialProtectionMode';
                $modeName = $this->ReadPropertyString('PartialProtectionName');
                $activationDelayName = 'PartialProtectionModeActivationDelay';
                $fullProtectionState = false;
                $hullProtectionState = false;
                $partialProtectionState = true;
                break;

            // Full protection mode
            default:
                $checkActivationModeName = 'CheckFullProtectionModeActivation';
                $identName = 'FullProtectionMode';
                $modeName = $this->ReadPropertyString('FullProtectionName');
                $activationDelayName = 'FullProtectionModeActivationDelay';
                $fullProtectionState = true;
                $hullProtectionState = false;
                $partialProtectionState = false;
        }
        // Set switches and states
        $this->SendDebug(__FUNCTION__, 'Switche gesetzt ' . microtime(true), 0);
        $this->SetValue('FullProtectionMode', $fullProtectionState);
        $this->SetValue('HullProtectionMode', $hullProtectionState);
        $this->SetValue('PartialProtectionMode', $partialProtectionState);
        $this->SetValue('AlarmSiren', false);
        $this->SetValue('AlarmState', 0);
        // Always check doors and windows and inform user about open doors and windows
        $this->UpdateDoorWindowState(true, true, true);
        // Check for activation mode
        $alarmZoneActivation = true;
        if ($this->ReadPropertyBoolean($checkActivationModeName)) {
            $alarmZoneActivation = $this->CheckActivation($Mode);
        }
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        // Abort activation
        if (!$alarmZoneActivation) {
            $result = false;
            $this->SetValue('FullProtectionMode', false);
            $this->SetValue('HullProtectionMode', false);
            $this->SetValue('PartialProtectionMode', false);
            // Set text
            $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent($identName) . ')';
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            // Log
            if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                @APRO_UpdateMessages($alarmProtocol, $logText, 0);
            }
            // Notification
            $actionText = $alarmZoneName . ' Abbruch durch Sensorenprüfung!';
            $messageText = $timeStamp . ' ' . $modeName . ' Abbruch.';
            $this->SendNotification($actionText, $messageText, $logText, 1);
            // Disable timer
            $this->DeactivateStartActivationTimer();
            // Reset blacklist
            $this->ResetBlackList();
        }
        // Continue activation
        if ($alarmZoneActivation) {
            // Check for activation delay
            $alarmZoneActivationDelayDuration = $this->ReadPropertyInteger($activationDelayName);
            if ($alarmZoneActivationDelayDuration > 0) {
                // Activate timer
                $milliseconds = $alarmZoneActivationDelayDuration * 1000;
                $this->SetTimerInterval('StartActivation', $milliseconds);
                // Set alarm zone state
                $this->SetValue('AlarmZoneState', 2);
                // Set text
                $text = $modeName . ' wird in ' . $alarmZoneActivationDelayDuration . ' Sekunden automatisch aktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent($identName) . ')';
                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                // Log
                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                    @APRO_UpdateMessages($alarmProtocol, $logText, 0);
                }
                // Tone Acknowledgement
                if ($UseToneAcknowledgement) {
                    $this->TriggerToneAcknowledgement();
                }
                // Notification
                if ($UseNotification) {
                    $actionText = $alarmZoneName . ' verzögert scharf!';
                    $messageText = $timeStamp . ' ' . $modeName . ' wird verzögert aktiviert.';
                    $this->SendNotification($actionText, $messageText, $logText, 1);
                }
            } // Activate mode immediately
            else {
                // Set alarm zone state
                $this->SetValue('AlarmZoneState', 1);
                // Set text
                $text = $modeName . ' aktiviert. (ID ' . $SenderID . ', ID ' . $this->GetIDForIdent($identName) . ')';
                $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                // Log
                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                    @APRO_UpdateMessages($alarmProtocol, $logText, 1);
                }
                // Tone Acknowledgement
                if ($UseToneAcknowledgement) {
                    $this->TriggerToneAcknowledgement();
                }
                // Notification
                if ($UseNotification) {
                    $actionText = $alarmZoneName . ' scharf!';
                    $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
                    $this->SendNotification($actionText, $messageText, $logText, 1);
                }
            }
        }
        // Signal lamps
        $this->SetSignalLamps();
        // Update alarm zone control states
        $this->UpdateAlarmZoneControlStates();
        return $result;
    }
}