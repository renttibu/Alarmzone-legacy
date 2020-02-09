<?php

// Declare
declare(strict_types=1);

trait AZST_alarmLight
{
    /**
     * Toggles the alarm light.
     * If no alarm light and no alarm light script is defined for the control center,
     * we will use the alarm light and alarm light script of the alarm zones.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmLight(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // State
        switch ($State) {
            case true:
                $status = 1;
                if ($this->ReadPropertyInteger('AlertingDelay') > 0) {
                    $status = 2;
                }
                break;

            default:
                $status = 0;
        }
        // Alarm light of control center
        $alarmLight = $this->ReadPropertyInteger('AlarmLight');
        if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
            // Set alarm light switch
            $this->SetValue('AlarmLight', $State);
            // Toggle alarm light
            @ABEL_ToggleAlarmLight($alarmLight, $State);
        }
        // Alarm light script of control center
        $alarmLightScript = $this->ReadPropertyInteger('AlarmLightScript');
        if ($alarmLightScript != 0 && @IPS_ObjectExists($alarmLightScript)) {
            // Set alarm light switch
            $this->SetValue('AlarmLight', $State);
            // Execute script
            IPS_RunScriptEx($alarmLightScript, ['State' => $status]);
        }
        // Alarm light and alarm light script of alarm zones
        if ($alarmLight == 0 && $alarmLightScript == 0) {
            // Alarm light of alarm zones
            $activeAlarmLights = [];
            $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
            if (!empty($alarmZones)) {
                foreach ($alarmZones as $alarmZone) {
                    $id = $alarmZone->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $alarmLight = (int) IPS_GetProperty($id, 'AlarmLight');
                        if (!empty($alarmLight)) {
                            if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
                                array_push($activeAlarmLights, $alarmLight);
                            }
                        }
                    }
                }
            }
            // Remove duplicates
            $activeAlarmLights = array_unique($activeAlarmLights);
            // Toggle alarm lights
            if (!empty($activeAlarmLights)) {
                // Set alarm light switch
                $this->SetValue('AlarmLight', $State);
                $count = count($activeAlarmLights);
                $i = 0;
                foreach ($activeAlarmLights as $activeAlarmLight) {
                    $i++;
                    // Toggle alarm light
                    if ($activeAlarmLight != 0 && @IPS_ObjectExists($activeAlarmLight)) {
                        @ABEL_ToggleAlarmLight($activeAlarmLight, $State);
                        // Execution delay for next instance
                        if ($count > 1 && $i < $count) {
                            @IPS_Sleep(500);
                        }
                    }
                }
            }
            // Alarm light script of alarm zones
            $activeAlarmLightScripts = [];
            if (!empty($alarmZones)) {
                foreach ($alarmZones as $alarmZone) {
                    $id = $alarmZone->ID;
                    $alarmLightScript = (int) IPS_GetProperty($id, 'AlarmLightScript');
                    if ($alarmLightScript != 0 && @IPS_ObjectExists($alarmLightScript)) {
                        array_push($activeAlarmLightScripts, $alarmLightScript);
                    }
                }
            }
            // Remove duplicates
            $activeAlarmLightScripts = array_unique($activeAlarmLightScripts);
            if (!empty($activeAlarmLightScripts)) {
                // Set alarm light switch
                $this->SetValue('AlarmLight', $State);
                foreach ($activeAlarmLightScripts as $activeAlarmLightScript) {
                    // Execute script
                    IPS_RunScriptEx($activeAlarmLightScript, ['State' => $status]);
                }
            }
        }
        // Update system state
        $this->UpdateStates();
    }
}