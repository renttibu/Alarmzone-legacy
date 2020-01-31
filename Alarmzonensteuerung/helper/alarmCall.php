<?php

// Declare
declare(strict_types=1);

trait AZST_alarmCall
{
    /**
     * Executes an alarm call.
     * If no alarm call and no alarm call script is defined for the control center,
     * we will use the alarm call and alarm call script of the alarm zones.
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

        // Alarm call of control center
        $alarmCall = $this->ReadPropertyInteger('AlarmCall');
        if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
            $scriptText = 'AANR_ToggleAlarmCall(' . $alarmCall . ', true, ' . $SensorName . ');';
            IPS_RunScriptText($scriptText);
        }

        // Alarm call script of control center
        $alarmCallScript = $this->ReadPropertyInteger('AlarmCallScript');
        if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
            IPS_RunScriptEx($alarmCallScript, ['State' => $action]);
        }

        // Alarm call and alarm call script of alarm zones
        if ($alarmCall == 0 && $alarmCallScript == 0) {
            // Alarm call of alarm zones
            $activeAlarmCalls = [];
            $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
            if (!empty($alarmZones)) {
                foreach ($alarmZones as $alarmZone) {
                    $id = $alarmZone->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $alarmCall = (int) IPS_GetProperty($id, 'AlarmCall');
                        if (!empty($alarmCall)) {
                            if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
                                array_push($activeAlarmCalls, $alarmCall);
                            }
                        }
                    }
                }
            }
            // Remove duplicates
            $activeAlarmCalls = array_unique($activeAlarmCalls);
            // Execute alarm calls
            if (!empty($activeAlarmCalls)) {
                $count = count($activeAlarmCalls);
                $i = 0;
                foreach ($activeAlarmCalls as $activeAlarmCall) {
                    $i++;
                    // Execute alarm call
                    $scriptText = 'AANR_ToggleAlarmCall(' . $activeAlarmCall . ', true, ' . $SensorName . ');';
                    IPS_RunScriptText($scriptText);
                    // Execution delay for next instance
                    if ($count > 1 && $i < $count) {
                        @IPS_Sleep(500);
                    }
                }
            }
            // Alarm call script of alarm zones
            $activeAlarmCallScripts = [];
            if (!empty($alarmZones)) {
                foreach ($alarmZones as $alarmZone) {
                    $alarmCallScript = (int) IPS_GetProperty($alarmZone, 'AlarmCallScript');
                    if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
                        array_push($activeAlarmCallScripts, $alarmCallScript);
                    }
                }
            }
            // Remove duplicates
            $activeAlarmCallScripts = array_unique($activeAlarmCallScripts);
            if (!empty($activeAlarmCallScripts)) {
                foreach ($activeAlarmCallScripts as $activeAlarmCallScript) {
                    // Execute script
                    IPS_RunScriptEx($activeAlarmCallScript, ['State' => $action]);
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