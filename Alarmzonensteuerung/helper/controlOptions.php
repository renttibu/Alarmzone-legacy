<?php

// Declare
declare(strict_types=1);

trait AZST_controlOptions
{
    /**
     * Toggles the absence mode.
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
    public function ToggleAbsenceMode(bool $State, string $SenderID): bool
    {
        $result = true;
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return false;
        }
        $this->SetValue('AbsenceMode', $State);
        // Check tone acknowledgement
        $useToneAcknowledgement = false;
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgement == 0 && $toneAcknowledgementScript == 0) {
            $useToneAcknowledgement = true;
        }
        // Check notification center
        $useNotification = false;
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationCenter == 0 && $notificationScript == 0) {
            $useNotification = true;
        }
        // Alarm light
        $this->ToggleAlarmLight(false);
        // Alarm call
        $this->CancelAlarmCall();
        // Toggle alarm zones
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $toggle = AZON_ToggleAbsenceMode($id, $State, $SenderID, $useToneAcknowledgement, $useNotification);
                if (!$toggle) {
                    $result = false;
                }
            }
        }
        // Notification
        $alarmObjectName = $this->ReadPropertyString('ObjectName');
        $modeName = $this->ReadPropertyString('AbsenceModeName');
        switch ($State) {
            // Disarm
            case false:
                if ($result) {
                    $text = $modeName . ' deaktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' unscharf!';
                } else {
                    $text = 'Es konnten nicht alle Alarmzonen deaktiviert werden. Bitte prüfen! (ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' Systemfehler!';
                }
                break;

            // Arm
            case true:
                if ($result) {
                    $text = $modeName . ' aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' scharf!';
                } else {
                    $text = 'Es konnten nicht alle Alarmzonen aktiviert werden. Bitte prüfen! (ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' Systemfehler!';
                }
                break;

        }
        if ($useNotification) {
            $timeStamp = date('d.m.Y, H:i:s');
            if (isset($actionText) && isset($text)) {
                $messageText = $timeStamp . ', ' . $alarmObjectName . ', ' . $text;
                // Notification Center
                $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
                if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                    $this->SendNotification($actionText, $messageText, 1);
                }
            }
        }
        // Update system state
        $this->UpdateStates();
        // Return result
        return $result;
    }

    /**
     * Toggles the presence mode.
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
    public function TogglePresenceMode(bool $State, string $SenderID): bool
    {
        $result = true;
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return false;
        }
        $this->SetValue('PresenceMode', $State);
        // Check tone acknowledgement
        $useToneAcknowledgement = false;
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgement == 0 && $toneAcknowledgementScript == 0) {
            $useToneAcknowledgement = true;
        }
        // Check notification center
        $useNotification = false;
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationCenter == 0 && $notificationScript == 0) {
            $useNotification = true;
        }
        // Alarm light
        $this->ToggleAlarmLight(false);
        // Alarm call
        $this->CancelAlarmCall();
        // Toggle alarm zones
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                AZON_TogglePresenceMode($id, $State, $SenderID, $useToneAcknowledgement, $useNotification);
            }
        }
        // Notification
        $alarmObjectName = $this->ReadPropertyString('ObjectName');
        $modeName = $this->ReadPropertyString('PresenceModeName');
        switch ($State) {
            // Disarm
            case false:
                if ($result) {
                    $text = $modeName . ' deaktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' unscharf!';
                } else {
                    $text = 'Es konnten nicht alle Alarmzonen deaktiviert werden. Bitte prüfen! (ID ' . $this->GetIDForIdent('PresenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' Systemfehler!';
                }
                break;

            // Arm
            case true:
                if ($result) {
                    $text = $modeName . ' aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' scharf!';
                } else {
                    $text = 'Es konnten nicht alle Alarmzonen aktiviert werden. Bitte prüfen! (ID ' . $this->GetIDForIdent('PresenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' Systemfehler!';
                }
                break;

        }
        if ($useNotification) {
            $timeStamp = date('d.m.Y, H:i:s');
            if (isset($actionText) && isset($text)) {
                $messageText = $timeStamp . ', ' . $alarmObjectName . ', ' . $text;
                // Notification Center
                $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
                if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                    $this->SendNotification($actionText, $messageText, 1);
                }
            }
        }
        // Update system state
        $this->UpdateStates();
        // Return result
        return $result;
    }

    /**
     * Toggles the night mode.
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
    public function ToggleNightMode(bool $State, string $SenderID): bool
    {
        $result = true;
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return false;
        }
        $this->SetValue('NightMode', $State);
        // Check tone acknowledgement
        $useToneAcknowledgement = false;
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgement == 0 && $toneAcknowledgementScript == 0) {
            $useToneAcknowledgement = true;
        }
        // Check notification center
        $useNotification = false;
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationCenter == 0 && $notificationScript == 0) {
            $useNotification = true;
        }
        // Alarm light
        $this->ToggleAlarmLight(false);
        // Alarm call
        $this->CancelAlarmCall();
        // Toggle alarm zones
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                AZON_ToggleNightMode($id, $State, $SenderID, $useToneAcknowledgement, $useNotification);
            }
        }
        // Notification
        $alarmObjectName = $this->ReadPropertyString('ObjectName');
        $modeName = $this->ReadPropertyString('NightModeName');
        switch ($State) {
            // Disarm
            case false:
                if ($result) {
                    $text = $modeName . ' deaktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' unscharf!';
                } else {
                    $text = 'Es konnten nicht alle Alarmzonen deaktiviert werden. Bitte prüfen! (ID ' . $this->GetIDForIdent('NightMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' Systemfehler!';
                }
                break;

            // Arm
            case true:
                if ($result) {
                    $text = $modeName . ' aktiviert. (SID ' . $SenderID . ', ID ' . $this->GetIDForIdent('AbsenceMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' scharf!';
                } else {
                    $text = 'Es konnten nicht alle Alarmzonen aktiviert werden. Bitte prüfen! (ID ' . $this->GetIDForIdent('NightMode') . ')';
                    $actionText = $alarmObjectName . ', ' . $modeName . ' Systemfehler!';
                }
                break;

        }
        if ($useNotification) {
            $timeStamp = date('d.m.Y, H:i:s');
            if (isset($actionText) && isset($text)) {
                $messageText = $timeStamp . ', ' . $alarmObjectName . ', ' . $text;
                // Notification Center
                $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
                if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                    $this->SendNotification($actionText, $messageText, 1);
                }
            }
        }
        // Update system state
        $this->UpdateStates();
        return $result;
    }
}