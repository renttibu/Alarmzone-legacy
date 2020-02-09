<?php

// Declare
declare(strict_types=1);

trait AZST_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement.
     */
    public function TriggerToneAcknowledgement(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Tone acknowledgement
        $id = $this->ReadPropertyInteger('ToneAcknowledgement');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @QTON_TriggerToneAcknowledgement($id, -1, -1);
        }
        // Execute Script
        $id = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScript($id);
        }
    }
}