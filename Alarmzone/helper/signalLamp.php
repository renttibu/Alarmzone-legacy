<?php

// Declare
declare(strict_types=1);

trait AZON_signalLamp
{
    //#################### Private

    /**
     * Sets the signal lamp for system state, door and window state, alarm state.
     */
    private function SetSignalLamps(): void
    {
        $this->SetAlarmZoneStateSignalLamp();
        $this->SetDoorWindowStateSignalLamp();
        $this->SetAlarmStateSignalLamp();
    }

    /**
     * Sets the signal lamp for system state.
     */
    private function SetAlarmZoneStateSignalLamp(): void
    {
        // Signal lamp
        $id = $this->ReadPropertyInteger('AlarmZoneStateSignalLamp');
        $alarmZoneState = (int) $this->GetValue('AlarmZoneState');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'SIGL_SetSystemStateSignalLamp(' . $id . ', ' . $alarmZoneState . ', 0, 0, ' . $alarmState . ');';
            IPS_RunScriptText($scriptText);
        }

        // Execute script
        $id = $this->ReadPropertyInteger('AlarmZoneStateSignalLampScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['Status' => $alarmZoneState]);
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
        $alarmZoneState = (int) $this->GetValue('AlarmZoneState');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'SIGL_SetDoorWindowStateSignalLamp(' . $id . ', ' . $doorWindowState . ', 0, 0, ' . $alarmZoneState . ', ' . $alarmState . ');';
            IPS_RunScriptText($scriptText);
        }

        // Execute script
        $id = $this->ReadPropertyInteger('DoorWindowStateSignalLampScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['Status' => $doorWindowState]);
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
        if ($id != 0 && IPS_ObjectExists($id)) {
            $scriptText = 'SIGL_SetAlarmStateSignalLamp(' . $id . ', ' . $alarmState . ', 0, 0);';
            IPS_RunScriptText($scriptText);
        }

        // Execute script
        $id = $this->ReadPropertyInteger('AlarmStateSignalLampScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['Status' => $alarmState]);
        }
    }
}