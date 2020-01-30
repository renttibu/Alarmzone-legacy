<?php

// Declare
declare(strict_types=1);

trait AZST_notification
{
    /**
     * Sends a notification.
     *
     * @param string $ActionText
     * @param string $MessageText
     * @param int $MessageType
     * 0    = Notification
     * 1    = Acknowledgement
     * 2    = Alert
     * 3    = Sabotage
     * 4    = Battery
     */
    private function SendNotification(string $ActionText, string $MessageText, int $MessageType): void
    {
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
            $this->SendDebug(__FUNCTION__, $MessageText . ', ' . $MessageType, 0);
            //Push notification
            $title = substr($this->ReadPropertyString('SystemName'), 0, 32);
            $messageText = $ActionText . "\n\n" . $MessageText;
            $scriptText = 'BENA_SendPushNotification(' . $notificationCenter . ', "' . $title . '", "' . $messageText . '", ' . $MessageType . ');';
            IPS_RunScriptText($scriptText);
            // E-Mail notification
            $subject = $ActionText;
            $scriptText = 'BENA_SendEMailNotification(' . $notificationCenter . ', "' . $subject . '", "' . $MessageText . '", ' . $MessageType . ');';
            IPS_RunScriptText($scriptText);
            // SMS notification
            $scriptText = 'BENA_SendSMSNotification(' . $notificationCenter . ', "' . $title . '", "' . $messageText . '", ' . $MessageType . ');';
            IPS_RunScriptText($scriptText);
        }
        // Execute script
        $notificationScript = $this->ReadPropertyInteger('NotificationScript');
        if ($notificationScript != 0 && IPS_ObjectExists($notificationScript)) {
            IPS_RunScriptEx($notificationScript, ['ActionText' => $ActionText, 'MessageText' => $MessageText, 'MessageType' => $MessageType]);
        }
    }
}