<?php

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

trait AZST_signalLamp
{
    #################### Private

    /**
     * Sets the signal lamp for system state, door and window state, alarm state.
     */
    private function SetSignalLamp(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $this->SetSystemStateSignalLamp();
        $this->SetDoorWindowStateSignalLamp();
        $this->SetAlarmStateSignalLamp();
    }

    /**
     * Sets the signal lamp for system state.
     */
    private function SetSystemStateSignalLamp(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Signal lamp
        $id = $this->ReadPropertyInteger('SystemStateSignalLamp');
        $systemState = (int) $this->GetValue('SystemState');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @SIGL_SetSystemStateSignalLamp($id, $systemState, 0, 0, $alarmState);
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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Signal lamp
        $id = $this->ReadPropertyInteger('DoorWindowStateSignalLamp');
        $doorWindowState = (int) $this->GetValue('DoorWindowState');
        $systemState = (int) $this->GetValue('SystemState');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @SIGL_SetDoorWindowStateSignalLamp($id, $doorWindowState, 0, 0, $systemState, $alarmState);
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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Signal lamp
        $id = $this->ReadPropertyInteger('AlarmStateSignalLamp');
        $alarmState = (int) $this->GetValue('AlarmState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @SIGL_SetAlarmStateSignalLamp($id, $alarmState, 0, 0);
        }
        // Execute script
        $id = $this->ReadPropertyInteger('AlarmStateSignalLampScript');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['State' => $alarmState]);
        }
    }
}