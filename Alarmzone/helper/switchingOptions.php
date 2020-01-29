<?php

// Declare
declare(strict_types=1);

trait AZON_switchingOptions
{
    /**
     * Checks all registered door and window sensors for activation mode.
     *
     * @param int $Mode
     * 0    = Absence Mode
     * 1    = Presence Mode
     * 2    = Night Mode
     *
     * @return bool
     * false    = no activation
     * true     = activate
     */
    public function CheckActivation(int $Mode): bool
    {
        $activation = true;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                // Check if sensor is activated for the requested mode
                switch ($Mode) {
                    // Absence mode
                    case 0:
                        $mode = $doorWindowSensor->AbsenceModeActive;
                        break;

                    // Presence mode
                    case 1:
                        $mode = $doorWindowSensor->PresenceModeActive;
                        break;

                    // Night mode
                    case 2:
                        $mode = $doorWindowSensor->NightModeActive;
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
        $timeStamp = date('d.m.Y, H:i:s');
        // Check alarm zone state first
        if ($this->GetValue('AlarmZoneState') == 2) {
            // Set new alarm zone state
            $this->SetValue('AlarmZoneState', 1);
            // Get activation mode
            $text = 'Es ist ein unbekannter Status bei der verzögerten Aktivierung aufgetreten.  (ID ' . $this->InstanceID . ')';
            $modeName = $this->ReadPropertyString('AlarmZoneName');
            if ($this->GetValue('AbsenceMode')) {
                $modeName = $this->ReadPropertyString('AbsenceModeName');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
            }
            if ($this->GetValue('PresenceMode')) {
                $modeName = $this->ReadPropertyString('PresenceModeName');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('PresenceMode') . ')';
            }
            if ($this->GetValue('NightMode')) {
                $modeName = $this->ReadPropertyString('NightModeName');
                $text = 'Der ' . $modeName . ' wurde durch die Einschaltverzögerung automatisch aktiviert. (ID ' . $this->GetIDForIdent('NightMode') . ')';
            }
            // Log
            $objectName = $this->ReadPropertyString('ObjectName');
            $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
            $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
            $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
            if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                IPS_RunScriptText($scriptText);
            }
            // Notification
            $actionText = $alarmZoneName . ' scharf!';
            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
            $this->SendNotification($actionText, $messageText, $logText, 1);
            // Tone acknowledgement
            $this->ExecuteToneAcknowledgement();
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
        // Disable timer
        $this->SetTimerInterval('StartActivation', 0);
    }

    /**
     * Toggles the absence mode.
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
    public function ToggleAbsenceMode(bool $State, string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $result = true;
        $timeStamp = date('d.m.Y, H:i:s');
        // Get alarm zone properties
        $objectName = $this->ReadPropertyString('ObjectName');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        // Check alarm zone difference
        $differentAlarmZoneState = false;
        if ($State != $this->GetValue('AbsenceMode')) {
            $differentAlarmZoneState = true;
        }
        $modeName = $this->ReadPropertyString('AbsenceModeName');

        //#################### Disarm

        // Deactivate absence mode
        if (!$State) {
            // Set switches
            $this->SetValue('AbsenceMode', false);
            $this->SetValue('PresenceMode', false);
            $this->SetValue('NightMode', false);
            $this->SetValue('AlarmSiren', false);
            // Set alarm zone state
            $this->SetValue('AlarmZoneState', 0);
            // Set alarm state
            $this->SetValue('AlarmState', 0);
            if ($differentAlarmZoneState) {
                // Log
                $text = $modeName . ' deaktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                    $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                    IPS_RunScriptText($scriptText);
                }
                // Notification
                if ($UseNotification) {
                    $actionText = $alarmZoneName . ' unscharf!';
                    $messageText = $timeStamp . ' ' . $modeName . ' deaktiviert.';
                    $this->SendNotification($actionText, $messageText, $logText, 1);
                }
            }
            // Disable timer
            $this->DeactivateStartActivationTimer();
            // Reset blacklist
            $this->ResetBlackList();
            // Toggle alarm sirens off
            $this->ToggleAlarmSiren(false);
            // Alarm light
            $this->ToggleAlarmLight(false);
            // Alarm call
            $this->CancelAlarmCall();
            // Check door and window sensors
            $this->UpdateDoorWindowState(false, true, true);
        }

        //#################### Arm

        // Activate absence mode
        if ($State) {
            // Always check doors and windows and inform user about open doors and windows
            $this->UpdateDoorWindowState(true, true, true);
            // Check for activation mode
            $alarmZoneActivation = true;
            if ($this->ReadPropertyBoolean('CheckAbsenceModeActivation')) {
                $alarmZoneActivation = $this->CheckActivation(0);
            }
            // Abort activation
            if (!$alarmZoneActivation) {
                $result = false;
                if ($differentAlarmZoneState) {
                    // Log
                    $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                    if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                        $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                        IPS_RunScriptText($scriptText);
                    }
                    // Notification
                    if ($UseNotification) {
                        $actionText = $alarmZoneName . ' Abbruch durch Sensorenprüfung!';
                        $messageText = $timeStamp . ' ' . $modeName . ' Abbruch.';
                        $this->SendNotification($actionText, $messageText, $logText, 1);
                    }
                }
                // Disable timer
                $this->DeactivateStartActivationTimer();
                // Reset blacklist
                $this->ResetBlackList();
            }
            // Continue activation
            if ($alarmZoneActivation) {
                // Check for activation delay
                $alarmZoneActivationDelayDuration = $this->ReadPropertyInteger('AbsenceModeActivationDelayDuration');
                if ($alarmZoneActivationDelayDuration > 0) {
                    // Activate timer
                    $milliseconds = $alarmZoneActivationDelayDuration * 1000;
                    $this->SetTimerInterval('StartActivation', $milliseconds);
                    // Set switches
                    $this->SetValue('AbsenceMode', true);
                    $this->SetValue('PresenceMode', false);
                    $this->SetValue('NightMode', false);
                    $this->SetValue('AlarmSiren', false);
                    // Set alarm zone state
                    $this->SetValue('AlarmZoneState', 2);
                    // Set alarm state
                    $this->SetValue('AlarmState', 0);
                    if ($differentAlarmZoneState) {
                        // Log
                        $text = $modeName . ' wird in ' . $alarmZoneActivationDelayDuration . ' Sekunden automatisch aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                            IPS_RunScriptText($scriptText);
                        }
                        // Notification
                        if ($UseNotification) {
                            $actionText = $alarmZoneName . ' verzögert scharf!';
                            $messageText = $timeStamp . ' ' . $modeName . ' wird verzögert aktiviert.';
                            $this->SendNotification($actionText, $messageText, $logText, 1);
                        }
                    }
                }
                // Activate absence mode immediately
                else {
                    // Set switches
                    $this->SetValue('AbsenceMode', true);
                    $this->SetValue('PresenceMode', false);
                    $this->SetValue('NightMode', false);
                    $this->SetValue('AlarmSiren', false);
                    // Set alarm zone state
                    $this->SetValue('AlarmZoneState', 1);
                    // Set alarm state
                    $this->SetValue('AlarmState', 0);
                    if ($differentAlarmZoneState) {
                        // Log
                        $text = $modeName . ' aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                            IPS_RunScriptText($scriptText);
                        }
                        // Notification
                        if ($UseNotification) {
                            $actionText = $alarmZoneName . ' scharf!';
                            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
                            $this->SendNotification($actionText, $messageText, $logText, 1);
                        }
                    }
                }
            }
        }
        // Tone Acknowledgement
        if ($UseToneAcknowledgement && $differentAlarmZoneState) {
            $this->ExecuteToneAcknowledgement();
        }
        // Signal lamps
        $this->SetSignalLamps();

        // Update alarm zone control states
        $this->UpdateAlarmZoneControlStates();
        return $result;
    }

    /**
     * Toggles the presence mode.
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
    public function TogglePresenceMode(bool $State, string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $result = true;
        $timeStamp = date('d.m.Y, H:i:s');
        // Get alarm zone properties
        $objectName = $this->ReadPropertyString('ObjectName');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        // Check alarm zone difference
        $differentAlarmZoneState = false;
        if ($State != (bool) $this->GetValue('PresenceMode')) {
            $differentAlarmZoneState = true;
        }
        $modeName = $this->ReadPropertyString('PresenceModeName');

        //#################### Disarm

        // Deactivate presence mode
        if (!$State) {
            // Set switches
            $this->SetValue('AbsenceMode', false);
            $this->SetValue('PresenceMode', false);
            $this->SetValue('NightMode', false);
            $this->SetValue('AlarmSiren', false);
            // Set alarm zone state
            $this->SetValue('AlarmZoneState', 0);
            // Set alarm state
            $this->SetValue('AlarmState', 0);
            if ($differentAlarmZoneState) {
                // Log
                $text = $modeName . ' deaktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('PresenceMode') . ')';
                $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                    $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                    IPS_RunScriptText($scriptText);
                }
                // Notification
                if ($UseNotification) {
                    $actionText = $alarmZoneName . ' unscharf!';
                    $messageText = $timeStamp . ' ' . $modeName . ' deaktiviert.';
                    $this->SendNotification($actionText, $messageText, $logText, 1);
                }
            }
            // Disable timer
            $this->DeactivateStartActivationTimer();
            // Reset blacklist
            $this->ResetBlackList();
            // Toggle alarm sirens off
            $this->ToggleAlarmSiren(false);
            // Alarm light
            $this->ToggleAlarmLight(false);
            // Alarm call
            $this->CancelAlarmCall();
            // Check door and window sensors
            $this->UpdateDoorWindowState(false, true, true);
        }

        //#################### Arm

        // Activate absence mode
        if ($State) {
            // Always check doors and windows and inform user about open doors and windows
            $this->UpdateDoorWindowState(true, true, true);
            // Check for activation mode
            $alarmZoneActivation = true;
            if ($this->ReadPropertyBoolean('CheckPresenceModeActivation')) {
                $alarmZoneActivation = $this->CheckActivation(1);
            }
            // Abort activation
            if (!$alarmZoneActivation) {
                $result = false;
                if ($differentAlarmZoneState) {
                    // Log
                    $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent('PresenceMode') . ')';
                    $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                    if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                        $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                        IPS_RunScriptText($scriptText);
                    }
                    // Notification
                    if ($UseNotification) {
                        $actionText = $alarmZoneName . ' Abbruch durch Sensorenprüfung!';
                        $messageText = $timeStamp . ' ' . $modeName . ' Abbruch.';
                        $this->SendNotification($actionText, $messageText, $logText, 1);
                    }
                }
                // Disable timer
                $this->DeactivateStartActivationTimer();
                // Reset blacklist
                $this->ResetBlackList();
            }
            // Continue activation
            if ($alarmZoneActivation) {
                // Check for activation delay
                $alarmZoneActivationDelayDuration = $this->ReadPropertyInteger('PresenceModeActivationDelayDuration');
                if ($alarmZoneActivationDelayDuration > 0) {
                    // Activate timer
                    $milliseconds = $alarmZoneActivationDelayDuration * 1000;
                    $this->SetTimerInterval('StartActivation', $milliseconds);
                    // Set switches
                    $this->SetValue('AbsenceMode', false);
                    $this->SetValue('PresenceMode', true);
                    $this->SetValue('NightMode', false);
                    $this->SetValue('AlarmSiren', false);
                    // Set alarm zone state
                    $this->SetValue('AlarmZoneState', 2);
                    // Set alarm state
                    $this->SetValue('AlarmState', 0);
                    if ($differentAlarmZoneState) {
                        // Log
                        $text = $modeName . ' wird in ' . $alarmZoneActivationDelayDuration . ' Sekunden automatisch aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('PresenceMode') . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                            IPS_RunScriptText($scriptText);
                        }
                        // Notification
                        if ($UseNotification) {
                            $actionText = $alarmZoneName . ' verzögert scharf!';
                            $messageText = $timeStamp . ' ' . $modeName . ' wird verzögert aktiviert.';
                            $this->SendNotification($actionText, $messageText, $logText, 1);
                        }
                    }
                }
                // Activate presence mode immediately
                else {
                    // Set switches
                    $this->SetValue('AbsenceMode', false);
                    $this->SetValue('PresenceMode', true);
                    $this->SetValue('NightMode', false);
                    $this->SetValue('AlarmSiren', false);
                    // Set alarm zone state
                    $this->SetValue('AlarmZoneState', 1);
                    // Set alarm state
                    $this->SetValue('AlarmState', 0);
                    if ($differentAlarmZoneState) {
                        // Log
                        $text = $modeName . ' aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('PresenceMode') . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                            IPS_RunScriptText($scriptText);
                        }
                        // Notification
                        if ($UseNotification) {
                            $actionText = $alarmZoneName . ' scharf!';
                            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
                            $this->SendNotification($actionText, $messageText, $logText, 1);
                        }
                    }
                }
            }
        }
        // Tone Acknowledgement
        if ($UseToneAcknowledgement && $differentAlarmZoneState) {
            $this->ExecuteToneAcknowledgement();
        }
        // Signal lamps
        $this->SetSignalLamps();

        // Update alarm zone control states
        $this->UpdateAlarmZoneControlStates();
        return $result;
    }

    /**
     * Toggles the night mode.
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
    public function ToggleNightMode(bool $State, string $SenderID, bool $UseToneAcknowledgement, bool $UseNotification): bool
    {
        $result = true;
        $timeStamp = date('d.m.Y, H:i:s');
        // Get alarm zone properties
        $objectName = $this->ReadPropertyString('ObjectName');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        // Check alarm zone difference
        $differentAlarmZoneState = false;
        if ($State != $this->GetValue('NightMode')) {
            $differentAlarmZoneState = true;
        }
        $modeName = $this->ReadPropertyString('NightModeName');

        //#################### Disarm

        // Deactivate night mode
        if (!$State) {
            // Set switches
            $this->SetValue('AbsenceMode', false);
            $this->SetValue('PresenceMode', false);
            $this->SetValue('NightMode', false);
            $this->SetValue('AlarmSiren', false);
            // Set alarm zone state
            $this->SetValue('AlarmZoneState', 0);
            // Set alarm state
            $this->SetValue('AlarmState', 0);
            if ($differentAlarmZoneState) {
                // Log
                $text = $modeName . ' deaktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('NightMode') . ')';
                $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                    $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                    IPS_RunScriptText($scriptText);
                }
                // Notification
                if ($UseNotification) {
                    $actionText = $alarmZoneName . ' unscharf!';
                    $messageText = $timeStamp . ' ' . $modeName . ' deaktiviert.';
                    $this->SendNotification($actionText, $messageText, $logText, 1);
                }
            }
            // Disable timer
            $this->DeactivateStartActivationTimer();
            // Reset blacklist
            $this->ResetBlackList();
            // Toggle alarm sirens off
            $this->ToggleAlarmSiren(false);
            // Alarm light
            $this->ToggleAlarmLight(false);
            // Alarm call
            $this->CancelAlarmCall();
            // Check door and window sensors
            $this->UpdateDoorWindowState(false, true, true);
        }

        //#################### Arm

        // Activate night mode
        if ($State) {
            // Always check doors and windows and inform user about open doors and windows
            $this->UpdateDoorWindowState(true, true, true);
            // Check for activation mode
            $alarmZoneActivation = true;
            if ($this->ReadPropertyBoolean('CheckNightModeActivation')) {
                $alarmZoneActivation = $this->CheckActivation(2);
            }
            // Abort activation
            if (!$alarmZoneActivation) {
                $result = false;
                if ($differentAlarmZoneState) {
                    // Log
                    $text = 'Die Aktivierung wurde durch die Sensorenprüfung abgebrochen! (ID ' . $this->GetIDForIdent('NightMode') . ')';
                    $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                    if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                        $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                        IPS_RunScriptText($scriptText);
                    }
                    // Notification
                    if ($UseNotification) {
                        $actionText = $alarmZoneName . ' Abbruch durch Sensorenprüfung!';
                        $messageText = $timeStamp . ' ' . $modeName . ' Abbruch.';
                        $this->SendNotification($actionText, $messageText, $logText, 1);
                    }
                }
                // Disable timer
                $this->DeactivateStartActivationTimer();
                // Reset blacklist
                $this->ResetBlackList();
            }
            // Continue activation
            if ($alarmZoneActivation) {
                // Check for activation delay
                $alarmZoneActivationDelayDuration = $this->ReadPropertyInteger('NightModeActivationDelayDuration');
                if ($alarmZoneActivationDelayDuration > 0) {
                    // Activate timer
                    $milliseconds = $alarmZoneActivationDelayDuration * 1000;
                    $this->SetTimerInterval('StartActivation', $milliseconds);
                    // Set switches
                    $this->SetValue('AbsenceMode', false);
                    $this->SetValue('PresenceMode', false);
                    $this->SetValue('NightMode', true);
                    $this->SetValue('AlarmSiren', false);
                    // Set alarm zone state
                    $this->SetValue('AlarmZoneState', 2);
                    // Set alarm state
                    $this->SetValue('AlarmState', 0);
                    if ($differentAlarmZoneState) {
                        // Log
                        $text = $modeName . ' wird in ' . $alarmZoneActivationDelayDuration . ' Sekunden automatisch aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('NightMode') . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 0);';
                            IPS_RunScriptText($scriptText);
                        }
                        // Notification
                        if ($UseNotification) {
                            $actionText = $alarmZoneName . ' verzögert scharf!';
                            $messageText = $timeStamp . ' ' . $modeName . ' wird verzögert aktiviert.';
                            $this->SendNotification($actionText, $messageText, $logText, 1);
                        }
                    }
                }
                // Activate absence mode immediately
                else {
                    // Set switches
                    $this->SetValue('AbsenceMode', false);
                    $this->SetValue('PresenceMode', false);
                    $this->SetValue('NightMode', true);
                    $this->SetValue('AlarmSiren', false);
                    // Set alarm zone state
                    $this->SetValue('AlarmZoneState', 1);
                    // Set alarm state
                    $this->SetValue('AlarmState', 0);
                    if ($differentAlarmZoneState) {
                        // Log
                        $text = $modeName . ' aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('NightMode') . ')';
                        $logText = $timeStamp . ', ' . $objectName . ', ' . $alarmZoneName . ', ' . $text;
                        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                            $scriptText = 'APRO_UpdateMessages(' . $alarmProtocol . ', "' . $logText . '", 1);';
                            IPS_RunScriptText($scriptText);
                        }
                        // Notification
                        if ($UseNotification) {
                            $actionText = $alarmZoneName . ' scharf!';
                            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
                            $this->SendNotification($actionText, $messageText, $logText, 1);
                        }
                    }
                }
            }
        }
        // Tone Acknowledgement
        if ($UseToneAcknowledgement && $differentAlarmZoneState) {
            $this->ExecuteToneAcknowledgement();
        }

        // Signal lamps
        $this->SetSignalLamps();

        // Update alarm zone control states
        $this->UpdateAlarmZoneControlStates();
        return $result;
    }

    /**
     * Sets the alarm state to alarm.
     */
    public function SetAlarmState(): void
    {
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
}