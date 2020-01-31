<?php

// Declare
declare(strict_types=1);

trait AZON_alarmCall
{
    /**
     * Executes an alarm call.
     *
     * @param string $SensorName
     */
    public function ExecuteAlarmCall(string $SensorName): void
    {
        // Check action
        if ($this->ReadPropertyInteger('AlertingDelayDuration') > 0) {
            $action = 2;
        } else {
            $action = 1;
        }

        // Alarm call
        $alarmCall = $this->ReadPropertyInteger('AlarmCall');
        if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
            // Execute alarm call
            $scriptText = 'AANR_ToggleAlarmCall(' . $alarmCall . ', true, ' . $SensorName . ');';
            IPS_RunScriptText($scriptText);
        }

        // Alarm call script
        $alarmCallScript = $this->ReadPropertyInteger('AlarmCallScript');
        if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
            // Execute script
            IPS_RunScriptEx($alarmCallScript, ['State' => $action]);
        }

        // Check configuration of alarm zone control
        $use = $this->ReadPropertyBoolean('UseAlarmZoneControlAlarmCall');
        if ($use) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {

                // Alarm call
                $alarmCall = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmCall');
                if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
                    // Execute alarm call
                    $scriptText = 'AANR_ToggleAlarmCall(' . $alarmCall . ', true, ' . $SensorName . ');';
                    IPS_RunScriptText($scriptText);
                }

                // Alarm call script
                $alarmCallScript = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmCallScript');
                if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
                    // Execute script
                    IPS_RunScriptEx($alarmCallScript, ['State' => $action]);
                }
            }
        }
    }

    //#################### Private

    /**
     * Cancels an alarm call.
     */
    private function CancelAlarmCall(): void
    {
        $id = $this->ReadPropertyInteger('AlarmCall');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $scriptText = 'AANR_ToggleAlarmCall(' . $id . ', false, "");';
            IPS_RunScriptText($scriptText);
        }
    }
}