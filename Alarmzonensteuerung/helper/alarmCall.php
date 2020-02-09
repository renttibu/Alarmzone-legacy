<?php

// Declare
declare(strict_types=1);

trait AZST_alarmCall
{
    /**
     * Triggers an alarm call.
     * If no alarm call and no alarm call script is defined for the control center,
     * we will use the alarm call and alarm call script of the alarm zones.
     *
     * @param string $SensorName
     */
    public function TriggerAlarmCall(string $SensorName): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Check action
        $state = 1;
        if ($this->ReadPropertyInteger('AlertingDelay') > 0) {
            $state = 2;
        }
        // Alarm call of control center
        $alarmCall = $this->ReadPropertyInteger('AlarmCall');
        if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
            @AANR_ToggleAlarmCall($alarmCall, true, $SensorName);
        }
        // Alarm call script of control center
        $alarmCallScript = $this->ReadPropertyInteger('AlarmCallScript');
        if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
            IPS_RunScriptEx($alarmCallScript, ['State' => $state]);
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
                    @AANR_ToggleAlarmCall($alarmCall, true, $SensorName);
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
                    IPS_RunScriptEx($activeAlarmCallScript, ['State' => $state]);
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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $id = $this->ReadPropertyInteger('AlarmCall');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @AANR_ToggleAlarmCall($id, true, '');
        }
    }
}