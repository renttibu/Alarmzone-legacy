<?php

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AZ_notificationCenter
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
     * 0    = notification
     * 1    = acknowledgement
     * 2    = alert
     * 3    = sabotage
     * 4    = battery
     *
     * @throws Exception
     */
    private function SendNotification(string $ActionText, string $MessageText, string $LogText, int $MessageType): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter == 0 || !@IPS_ObjectExists($notificationCenter)) {
            return;
        }
        $location = $this->ReadPropertyString('Location');
        //Push
        $pushTitle = substr($location, 0, 32);
        $pushText = "\n" . $ActionText . "\n" . $MessageText;
        //E-Mail
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
        //SMS
        $smsText = $location . "\n" . $ActionText . "\n" . $MessageText;
        //Send notification
        @BZ_SendNotification($notificationCenter, $pushTitle, $pushText, $eMailSubject, $eMailText, $smsText, $MessageType);
        /*
        $script = 'BZ_SendNotification(' . $notificationCenter . ', "' . $pushTitle . '", "' . $pushText . '", "' . $eMailSubject . '", "' . $eMailText . '", "' . $smsText . '", ' . $MessageType . ');';
        @IPS_RunScriptText($script);
         */
    }

    /**
     * Confirms the alarm message.
     */
    private function ConfirmAlarmNotification(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter == 0 || !@IPS_ObjectExists($notificationCenter)) {
            return;
        }
        @BZ_ConfirmAlarmNotification($notificationCenter);
        /*
        $script = 'BZ_ConfirmAlarmNotification(' . $notificationCenter . ');';
        @IPS_RunScriptText($script);
         */
    }
}