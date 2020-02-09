<?php

// Declare
declare(strict_types=1);

trait AZON_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement.
     */
    public function TriggerToneAcknowledgement(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Tone acknowledgement
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        if ($toneAcknowledgement != 0 && @IPS_ObjectExists($toneAcknowledgement)) {
            // Execute tone acknowledgement
            @QTON_TriggerToneAcknowledgement($toneAcknowledgement, -1, -1);
        }
        // Tone acknowledgement script
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgementScript != 0 && @IPS_ObjectExists($toneAcknowledgementScript)) {
            // Execute script
            IPS_RunScript($toneAcknowledgementScript);
        }
        // Check configuration of alarm zone control
        if ($this->ReadPropertyBoolean('UseAlarmZoneControlToneAcknowledgement')) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
                // Tone acknowledgement
                $toneAcknowledgement = (int) @IPS_GetProperty($alarmZoneControl, 'ToneAcknowledgement');
                if ($toneAcknowledgement != 0 && @IPS_ObjectExists($toneAcknowledgement)) {
                    // Execute tone acknowledgement
                    @QTON_TriggerToneAcknowledgement($toneAcknowledgement, -1, -1);
                }
                // Tone acknowledgement script
                $toneAcknowledgementScript = (int) @IPS_GetProperty($alarmZoneControl, 'ToneAcknowledgementScript');
                if ($toneAcknowledgementScript != 0 && @IPS_ObjectExists($toneAcknowledgementScript)) {
                    // Execute script
                    $this->SendDebug(__FUNCTION__, 'Skript der Alarmzone wird ausgeführt.', 0);
                    IPS_RunScript($toneAcknowledgementScript);
                }
            }
        }
    }
}