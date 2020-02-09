<?php

// Declare
declare(strict_types=1);

trait AZON_notificationCenter
{
    //#################### Private

    /**
     * Sends a notification.
     *
     * @param string $ActionText
     *
     * @param string $MessageText
     *
     * @param string $LogText
     *
     * @param int $MessageType
     * 0    = Notification
     * 1    = Acknowledgement
     * 2    = Alert
     * 3    = Sabotage
     * 4    = Battery
     */
    private function SendNotification(string $ActionText, string $MessageText, string $LogText, int $MessageType): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $location = $this->ReadPropertyString('Location');
        // Prepare push notification notification
        $pushTitle = substr($location, 0, 32);
        $pushText = "\n" . $ActionText . "\n" . $MessageText;
        // Prepare E-Mail notification
        $eMailSubject = $location . ', ' . $ActionText;
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
            $eventMessages = IPS_GetObjectIDByIdent('EventMessages', $alarmProtocol);
            $content = array_merge(array_filter(explode("\n", GetValue($eventMessages))));
            $name = IPS_GetName($eventMessages);
            array_unshift($content, $name . ":\n");
            for ($i = 0; $i < 2; $i++) {
                array_unshift($content, "\n");
            }
            $eventProtocol = implode("\n", $content);
            $LogText .= "\n\n" . $eventProtocol;
        }
        $eMailText = $LogText;
        // Prepare SMS notification
        $smsText = $location . "\n" . $ActionText . "\n" . $MessageText;
        // Notification center
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
            // Send notification
            @BENA_SendNotification($notificationCenter, $pushTitle, $pushText, $eMailSubject, $eMailText, $smsText, $MessageType);
        }
        // Notification script
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationScript != 0 && @IPS_ObjectExists($notificationScript)) {
            // Execute script
            IPS_RunScriptEx($notificationScript, ['ActionText' => $ActionText, 'MessageText' => $MessageText, 'LogText' => $LogText, 'MessageType' => $MessageType]);
        }
        // Check configuration of alarm zone control
        if ($this->ReadPropertyBoolean('UseAlarmZoneControlNotificationCenter')) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
                // Notification center
                $notificationCenter = (int) @IPS_GetProperty($alarmZoneControl, 'NotificationCenter');
                if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                    // Send notification
                    @BENA_SendNotification($notificationCenter, $pushTitle, $pushText, $eMailSubject, $eMailText, $smsText, $MessageType);
                }
                // Notification script
                $notificationScript = (int) @IPS_GetProperty($alarmZoneControl, 'NotificationScript');
                if ($notificationScript != 0 && @IPS_ObjectExists($notificationScript)) {
                    // Execute script
                    IPS_RunScriptEx($notificationScript, ['ActionText' => $ActionText, 'MessageText' => $MessageText, 'LogText' => $LogText, 'MessageType' => $MessageType]);
                }
            }
        }
    }

    /**
     * Confirms the alarm message.
     */
    private function ConfirmAlarmNotification(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Notification center
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
            @BENA_ConfirmAlarmNotification($notificationCenter);
        }
        // Check configuration of alarm zone control
        if ($this->ReadPropertyBoolean('UseAlarmZoneControlNotificationCenter')) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
                // Notification center
                $notificationCenter = (int) @IPS_GetProperty($alarmZoneControl, 'NotificationCenter');
                if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
                    @BENA_ConfirmAlarmNotification($notificationCenter);
                }
            }
        }
    }
}