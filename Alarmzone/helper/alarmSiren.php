<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AZON_alarmSiren
{
    /**
     * Toggles the alarm siren.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmSiren(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Check action
        switch ($State) {
            case true:
                $action = 1;
                if ($this->ReadPropertyInteger('AlertingDelay') > 0) {
                    $action = 2;
                }
                break;

            default:
                $action = 0;
        }
        // Alarm siren
        $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
        if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
            // Set alarm siren switch
            $this->SetValue('AlarmSiren', $State);
            // Toggle alarm siren
            @ASIR_ToggleAlarmSiren($alarmSiren, $State);
        }
        // Alarm siren script
        $alarmSirenScript = $this->ReadPropertyInteger('AlarmSirenScript');
        if ($alarmSirenScript != 0 && @IPS_ObjectExists($alarmSirenScript)) {
            // Set alarm siren switch
            $this->SetValue('AlarmSiren', $State);
            // Execute script
            IPS_RunScriptEx($alarmSirenScript, ['State' => $action]);
        }
        // Check configuration of alarm zone control
        if ($this->ReadPropertyBoolean('UseAlarmZoneControlAlarmLight')) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
                // Alarm siren
                $alarmSiren = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmSiren');
                if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
                    // Set alarm siren switch
                    $this->SetValue('AlarmSiren', $State);
                    // Toggle alarm siren
                    @ASIR_ToggleAlarmSiren($alarmSiren, $State);
                }
                // Alarm siren script
                $alarmSirenScript = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmSirenScript');
                if ($alarmSirenScript != 0 && @IPS_ObjectExists($alarmSirenScript)) {
                    // Set alarm siren switch
                    $this->SetValue('AlarmSiren', $State);
                    // Execute script
                    IPS_RunScriptEx($alarmSirenScript, ['State' => $action]);
                }
            }
        }
    }
}