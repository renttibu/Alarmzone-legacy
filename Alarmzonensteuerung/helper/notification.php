<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

trait AZST_notification
{
    #################### Private

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
     * @throws Exception
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
    }
}