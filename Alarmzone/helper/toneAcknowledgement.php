<?php

// Declare
declare(strict_types=1);

trait AZON_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement.
     */
    public function ExecuteToneAcknowledgement(): void
    {
        // Tone acknowledgement
        $toneAcknowledgement = $this->ReadPropertyInteger('ToneAcknowledgement');
        if ($toneAcknowledgement != 0 && @IPS_ObjectExists($toneAcknowledgement)) {
            // Execute tone acknowledgement
            $scriptText = 'QTON_ExecuteToneAcknowledgement(' . $toneAcknowledgement . ', -1, -1);';
            IPS_RunScriptText($scriptText);
        }

        // Tone acknowledgement script
        $toneAcknowledgementScript = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($toneAcknowledgementScript != 0 && @IPS_ObjectExists($toneAcknowledgementScript)) {
            // Execute script
            IPS_RunScript($toneAcknowledgementScript);
        }

        // Check configuration of alarm zone control
        $use = $this->ReadPropertyBoolean('UseAlarmZoneControlToneAcknowledgement');
        if ($use) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {

                // Tone acknowledgement
                $toneAcknowledgement = (int) @IPS_GetProperty($alarmZoneControl, 'ToneAcknowledgement');
                if ($toneAcknowledgement != 0 && @IPS_ObjectExists($toneAcknowledgement)) {
                    // Execute tone acknowledgement
                    $scriptText = 'QTON_ExecuteToneAcknowledgement(' . $toneAcknowledgement . ', -1, -1);';
                    IPS_RunScriptText($scriptText);
                }

                // Tone acknowledgement script
                $toneAcknowledgementScript = (int) @IPS_GetProperty($alarmZoneControl, 'ToneAcknowledgementScript');
                if ($toneAcknowledgementScript != 0 && @IPS_ObjectExists($toneAcknowledgementScript)) {
                    // Execute script
                    IPS_RunScript($toneAcknowledgementScript);
                }
            }
        }
    }
}