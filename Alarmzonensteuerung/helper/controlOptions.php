<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AZST_controlOptions
{
    /**
     * Disarms the alarm zones.
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function DisarmAlarmZones(string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return false;
        }
        $result = true;
        // Set switches and states
        $this->SetValue('FullProtectionMode', false);
        $this->SetValue('HullProtectionMode', false);
        $this->SetValue('PartialProtectionMode', false);
        $this->SetValue('AlarmSiren', false);
        $actualAlarmState = $this->GetValue('AlarmState');
        $this->SetValue('AlarmState', 0);
        // Check tone acknowledgement
        $useAlarmZoneToneAcknowledgement = false;
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgement == 0 && $toneAcknowledgementScript == 0) {
            $useAlarmZoneToneAcknowledgement = true;
        }
        // Check notification center
        $useAlarmZoneNotification = false;
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationCenter == 0 && $notificationScript == 0) {
            $useAlarmZoneNotification = true;
        }
        // Toggle alarm zones
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $toggle = @AZON_DisarmAlarmZone($id, $SenderID, $useAlarmZoneToneAcknowledgement, $useAlarmZoneNotification);
                if (!$toggle) {
                    $result = false;
                }
            }
        }
        // Tone acknowledgement
        if (!$useAlarmZoneToneAcknowledgement) {
            $this->TriggerToneAcknowledgement();
        }
        // Notification
        if (!$useAlarmZoneNotification) {
            $timeStamp = date('d.m.Y, H:i:s');
            $objectName = $this->ReadPropertyString('ObjectName');
            $systemName = $this->ReadPropertyString('SystemName');
            $location = $this->ReadPropertyString('Location');
            if ($result) {
                $disarmedSymbol = $this->ReadPropertyString('AlarmZonesDisarmedSymbol');
                if (!empty($disarmedSymbol)) {
                    $actionText = $disarmedSymbol . ' ' . $objectName . ' unscharf!';
                } else {
                    $actionText = $objectName . ' unscharf!';
                }
                $messageText = $timeStamp . ' ' . $systemName . ' deaktiviert.';
                $logText = $timeStamp . ', ' . $location . ', ' . $objectName . ', ' . $systemName . ' deaktiviert';
            } else {
                $failureSymbol = $this->ReadPropertyString('AlarmZonesSystemFailure');
                if (!empty($failureSymbol)) {
                    $actionText = $failureSymbol . ' ' . $objectName . ' Systemfehler!';
                } else {
                    $actionText = $objectName . ' Systemfehler!';
                }
                $messageText = $timeStamp . ' ' . $systemName . 'Es konnten nicht alle Alarmzonen deaktiviert werden, bitte prüfen!';
                $logText = $timeStamp . ', ' . $location . ', ' . $objectName . ', ' . $systemName . ': Es konnten nicht alle Alarmzonen deaktiviert werden, bitte prüfen!';
            }
            // Notification Center
            $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
            if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                $this->SendNotification($actionText, $messageText, $logText, 1);
            }
            // Confirm alarm notification
            $this->ConfirmAlarmNotification();
        }
        // Alarm light off
        if ($this->ReadPropertyBoolean('AutomaticTurnOffAlarmLight')) {
            $this->ToggleAlarmLight(false);
        }
        // Cancel Alarm call
        $this->CancelAlarmCall();
        // Turn off alarm siren
        if ($actualAlarmState != 0) {
            $this->ToggleAlarmSiren(false);
        }
        // Update system state
        $this->UpdateStates();
        return $result;
    }

    /**
     * Toggles the full protect mode.
     *
     * @param bool $State
     * false    = disarmed
     * true     = armed
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function ToggleFullProtectMode(bool $State, string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZones($SenderID);
        }
        // Arm
        if ($State) {
            $result = $this->ArmAlarmZones($SenderID, 1);
        }
        return $result;
    }

    /**
     * Toggles the hull protect mode.
     *
     * @param bool $State
     * false    = disarmed
     * true     = armed
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function ToggleHullProtectMode(bool $State, string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZones($SenderID);
        }
        // Arm
        if ($State) {
            $result = $this->ArmAlarmZones($SenderID, 2);
        }
        return $result;
    }

    /**
     * Toggles the partial protect mode.
     *
     * @param bool $State
     * false    = disarmed
     * true     = armed
     *
     * @param string $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function TogglePartialProtectMode(bool $State, string $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = true;
        // Disarm
        if (!$State) {
            $result = $this->DisarmAlarmZones($SenderID);
        }
        // Arm
        if ($State) {
            $result = $this->ArmAlarmZones($SenderID, 3);
        }
        return $result;
    }

    #################### Private

    /**
     * Arms the alarm zones.
     *
     * @param string $SenderID
     *
     * @param int $Mode
     * 1    = Full protection mode
     * 2    = Hull protection mode
     * 3    = Partial protection mode
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    private function ArmAlarmZones(string $SenderID, int $Mode): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return false;
        }
        $result = true;
        switch ($Mode) {
            // Hull protection mode
            case 2:
                $identName = 'HullProtectionMode';
                $modeName = $this->ReadPropertyString('HullProtectionName');
                $symbol = $this->ReadPropertyString('HullProtectionModeArmedSymbol');
                break;

            // Partial protection mode
            case 3:
                $identName = 'PartialProtectionMode';
                $modeName = $this->ReadPropertyString('PartialProtectionName');
                $symbol = $this->ReadPropertyString('PartialProtectionModeArmedSymbol');
                break;

            // Full protection mode
            default:
                $identName = 'FullProtectionMode';
                $modeName = $this->ReadPropertyString('FullProtectionName');
                $symbol = $this->ReadPropertyString('FullProtectionModeArmedSymbol');

        }
        // Set switch
        $this->SetValue($identName, true);
        // Check tone acknowledgement
        $useAlarmZoneToneAcknowledgement = false;
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgement == 0 && $toneAcknowledgementScript == 0) {
            $useAlarmZoneToneAcknowledgement = true;
        }
        // Check notification center
        $useAlarmZoneNotification = false;
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationCenter == 0 && $notificationScript == 0) {
            $useAlarmZoneNotification = true;
        }
        // Toggle alarm zones
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                switch ($Mode) {
                    // Hull protection mode
                    case 2:
                        $toggle = @AZON_ToggleHullProtectMode($id, true, $SenderID, $useAlarmZoneToneAcknowledgement, $useAlarmZoneNotification);
                        break;

                    // Partial protection mode
                    case 3:
                        $toggle = @AZON_TogglePartialProtectMode($id, true, $SenderID, $useAlarmZoneToneAcknowledgement, $useAlarmZoneNotification);
                        break;

                    // Full protection mode
                    default:
                        $toggle = @AZON_ToggleFullProtectMode($id, true, $SenderID, $useAlarmZoneToneAcknowledgement, $useAlarmZoneNotification);
                }
                if (!$toggle) {
                    $result = false;
                }
            }
        }
        // Tone acknowledgement
        if (!$useAlarmZoneToneAcknowledgement && $result) {
            $this->TriggerToneAcknowledgement();
        }
        // Notification
        $timeStamp = date('d.m.Y, H:i:s');
        $systemName = $this->ReadPropertyString('SystemName');
        $objectName = $this->ReadPropertyString('ObjectName');
        $location = $this->ReadPropertyString('Location');
        if ($result) {
            // Set text
            if (!empty($symbol)) {
                $actionText = $symbol . ' ' . $objectName . ' scharf!';
            } else {
                $actionText = $objectName . ' scharf!';
            }
            $messageText = $timeStamp . ' ' . $modeName . ' aktiviert.';
            $logText = $timeStamp . ', ' . $location . ', ' . $objectName . ', ' . $systemName . ' aktiviert';
        } else {
            // Reset switch
            $this->SetValue($identName, false);
            // Set text
            $failureSymbol = $this->ReadPropertyString('AlarmZonesSystemFailure');
            if (!empty($failureSymbol)) {
                $actionText = $failureSymbol . ' ' . $objectName . ' Systemfehler!';
            } else {
                $actionText = $objectName . ' Systemfehler!';
            }
            $messageText = $timeStamp . ' ' . $modeName . 'Es konnten nicht alle Alarmzonen aktiviert werden, bitte prüfen!';
            $logText = $timeStamp . ', ' . $location . ', ' . $objectName . ', ' . $systemName . ': Es konnten nicht alle Alarmzonen aktiviert werden, bitte prüfen!';
        }
        if (!$useAlarmZoneNotification) {
            $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
            if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                $this->SendNotification($actionText, $messageText, $logText, 1);
            }
        }
        // Update system state
        $this->UpdateStates();
        return $result;
    }
}