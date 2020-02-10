<?php

// Declare
declare(strict_types=1);

trait AZON_alarmLight
{
    /**
     * Toggles the alarm light.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmLight(bool $State): void
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
        // Alarm light
        $alarmLight = $this->ReadPropertyInteger('AlarmLight');
        if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
            // Set alarm light switch
            $this->SetValue('AlarmLight', $State);
            // Toggle alarm light
            @ABEL_ToggleAlarmLight($alarmLight, $State);
        }
        // Alarm light script
        $alarmLightScript = $this->ReadPropertyInteger('AlarmLightScript');
        if ($alarmLightScript != 0 && IPS_ObjectExists($alarmLightScript)) {
            // Set alarm light switch
            $this->SetValue('AlarmLight', $State);
            // Execute script
            IPS_RunScriptEx($alarmLightScript, ['State' => $action]);
        }
        // Check configuration of alarm zone control
        if ($this->ReadPropertyBoolean('UseAlarmZoneControlAlarmLight')) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
                // Alarm light
                $alarmLight = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmLight');
                if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
                    // Set alarm light switch
                    $this->SetValue('AlarmLight', $State);
                    // Toggle alarm light
                    @ABEL_ToggleAlarmLight($alarmLight, $State);
                }
                // Alarm light script
                $alarmLightScript = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmLightScript');
                if ($alarmLightScript != 0 && @IPS_ObjectExists($alarmLightScript)) {
                    // Set alarm light switch
                    $this->SetValue('AlarmLight', $State);
                    // Execute script
                    IPS_RunScriptEx($alarmLightScript, ['State' => $action]);
                }
            }
        }
    }
}