<?php

// Declare
declare(strict_types=1);

trait AZST_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement.
     */
    public function ExecuteToneAcknowledgement(): void
    {
        // Tone acknowledgement
        $id = $this->ReadPropertyInteger('ToneAcknowledgement');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'QTON_ExecuteToneAcknowledgement(' . $id . ', -1, -1);';
            IPS_RunScriptText($scriptText);
            //QTON_ExecuteToneAcknowledgement($id, -1, -1);
        }
        // Execute Script
        $id = $this->ReadPropertyInteger('ToneAcknowledgementScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScript($id);
        }
    }
}