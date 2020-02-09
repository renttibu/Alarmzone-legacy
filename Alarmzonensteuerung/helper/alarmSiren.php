<?php

// Declare
declare(strict_types=1);

trait AZST_alarmSiren
{
    /**
     * Toggles the alarm siren.
     * If no alarm siren and no alarm siren script is defined for the control center,
     * we will use the alarm siren and alarm siren script of the alarm zones.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmSiren(bool $State): void
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
        // Alarm siren of control center
        $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
        if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
            // Set alarm siren switch
            $this->SetValue('AlarmSiren', $State);
            // Toggle alarm siren
            @ASIR_ToggleAlarmSiren($alarmSiren, $State);
        }
        // Alarm siren script of control center
        $alarmSirenScript = $this->ReadPropertyInteger('AlarmSirenScript');
        if ($alarmSirenScript != 0 && @IPS_ObjectExists($alarmSirenScript)) {
            // Set alarm siren switch
            $this->SetValue('AlarmSiren', $State);
            // Execute script
            IPS_RunScriptEx($alarmSirenScript, ['State' => $status]);
        }
        // Alarm siren and alarm siren script of alarm zones
        if ($alarmSiren == 0 && $alarmSirenScript == 0) {
            // Alarm siren of alarm zones
            $activeAlarmSirens = [];
            $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
            if (!empty($alarmZones)) {
                foreach ($alarmZones as $alarmZone) {
                    $id = $alarmZone->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $alarmSiren = (int) @IPS_GetProperty($id, 'AlarmSiren');
                        if (!empty($alarmSiren)) {
                            if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
                                array_push($activeAlarmSirens, $alarmSiren);
                            }
                        }
                    }
                }
            }
            // Remove duplicates
            $activeAlarmSirens = array_unique($activeAlarmSirens);
            // Toggle alarm sirens
            if (!empty($activeAlarmSirens)) {
                // Set alarm siren switch
                $this->SetValue('AlarmSiren', $State);
                $count = count($activeAlarmSirens);
                $i = 0;
                foreach ($activeAlarmSirens as $activeAlarmSiren) {
                    $i++;
                    // Toggle alarm siren
                    @ASIR_ToggleAlarmSiren($activeAlarmSiren, $State);
                    // Execution delay for next instance
                    if ($count > 1 && $i < $count) {
                        IPS_Sleep(500);
                    }
                }
            }
            // Alarm siren script of alarm zones
            $activeAlarmSirenScripts = [];
            if (!empty($alarmZones)) {
                foreach ($alarmZones as $alarmZone) {
                    $id = $alarmZone->ID;
                    $alarmSirenScript = (int) IPS_GetProperty($id, 'AlarmSirenScript');
                    if ($alarmSirenScript != 0 && @IPS_ObjectExists($alarmSirenScript)) {
                        array_push($activeAlarmSirenScripts, $alarmSirenScript);
                    }
                }
            }
            // Remove duplicates
            $activeAlarmSirenScripts = array_unique($activeAlarmSirenScripts);
            if (!empty($activeAlarmSirenScripts)) {
                // Set alarm siren switch
                $this->SetValue('AlarmSiren', $State);
                foreach ($activeAlarmSirenScripts as $activeAlarmSirenScript) {
                    // Execute script
                    IPS_RunScriptEx($activeAlarmSirenScript, ['State' => $status]);
                }
            }
        }
        // Update system state
        $this->UpdateStates();
    }
}