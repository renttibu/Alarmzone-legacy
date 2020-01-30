<?php

// Declare
declare(strict_types=1);

trait AZST_signalLamp
{
    /**
     * Sets the signal lamp for system state, door and window state, alarm state.
     */
    private function SetSignalLamp(): void
    {
        $this->SetSystemStateSignalLamp();
        $this->SetDoorWindowStateSignalLamp();
        $this->SetAlarmStateSignalLamp();
    }

    /**
     * Sets the signal lamp for system state.
     */
    private function SetSystemStateSignalLamp(): void
    {
        // Signal lamp
        $id = $this->ReadPropertyInteger('SystemStateSignalLamp');
        $systemState = (int) $this->GetValue('SystemState');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'SIGL_SetSystemStateSignalLamp(' . $id . ', ' . $systemState . ', 0, 0, ' . $alarmState . ');';
            IPS_RunScriptText($scriptText);
            //SIGL_SetSystemStateSignalLamp($id, $systemState, 0, 0, $alarmState);
        }
        // Execute script
        $id = $this->ReadPropertyInteger('SystemStateSignalLampScript');
        if ($id != 0 && IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['State' => $systemState]);
        }
    }

    /**
     * Sets the signal lamp for door and window state.
     */
    private function SetDoorWindowStateSignalLamp(): void
    {
        // Signal lamp
        $id = $this->ReadPropertyInteger('DoorWindowStateSignalLamp');
        $doorWindowState = (int) $this->GetValue('DoorWindowState');
        $systemState = (int) $this->GetValue('SystemState');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'SIGL_SetDoorWindowStateSignalLamp(' . $id . ', ' . $doorWindowState . ', 0, 0, ' . $systemState . ', ' . $alarmState . ');';
            IPS_RunScriptText($scriptText);
            //SIGL_SetDoorWindowStateSignalLamp($id, $doorWindowState, 0, 0, $systemState, $alarmState);
        }
        // Execute script
        $id = $this->ReadPropertyInteger('DoorWindowStateSignalLampScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['State' => $doorWindowState]);
        }
    }

    /**
     * Sets the signal lamp for alarm state.
     */
    private function SetAlarmStateSignalLamp(): void
    {
        // Signal lamp
        $id = $this->ReadPropertyInteger('AlarmStateSignalLamp');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'SIGL_SetAlarmStateSignalLamp(' . $id . ', ' . $alarmState . ', 0, 0);';
            IPS_RunScriptText($scriptText);
            //SIGL_SetAlarmStateSignalLamp($id, $alarmState, 0, 0);
        }
        // Execute script
        $id = $this->ReadPropertyInteger('AlarmStateSignalLampScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['State' => $alarmState]);
        }
    }
}