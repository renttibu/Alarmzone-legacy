<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AZON_alarmCall
{
    /**
     * Triggers an alarm call.
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
        // Alarm call
        $alarmCall = $this->ReadPropertyInteger('AlarmCall');
        if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
            @AANR_ToggleAlarmCall($alarmCall, true, $SensorName);
        }
        // Alarm call script
        $alarmCallScript = $this->ReadPropertyInteger('AlarmCallScript');
        if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
            IPS_RunScriptEx($alarmCallScript, ['State' => $state, 'SensorName' => $SensorName]);
        }
        // Check configuration of alarm zone control
        if ($this->ReadPropertyBoolean('UseAlarmZoneControlAlarmCall')) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
                // Alarm call
                $alarmCall = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmCall');
                if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
                    @AANR_ToggleAlarmCall($alarmCall, true, $SensorName);
                }
                // Alarm call script
                $alarmCallScript = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmCallScript');
                if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
                    IPS_RunScriptEx($alarmCallScript, ['State' => $state, 'SensorName' => $SensorName]);
                }
            }
        }
    }

    #################### Private

    /**
     * Cancels an alarm call.
     */
    private function CancelAlarmCall(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $id = $this->ReadPropertyInteger('AlarmCall');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @AANR_ToggleAlarmCall($id, false, '');
        }
    }
}