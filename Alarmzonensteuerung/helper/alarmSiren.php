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
        // State
        switch ($State) {
            case false:
                $status = 0;
                break;

            case true:
                if ($this->ReadPropertyInteger('AlertingDelayDuration') > 0) {
                    $status = 2;
                } else {
                    $status = 1;
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
            $scriptText = 'ASIR_ToggleAlarmSiren(' . $alarmSiren . ', ' . (int) $State . ');';
            IPS_RunScriptText($scriptText);
            //ASIR_ToggleAlarmSiren($alarmSiren, $State);
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
                    $scriptText = 'ASIR_ToggleAlarmSiren(' . $activeAlarmSiren . ', ' . (int) $State . ');';
                    IPS_RunScriptText($scriptText);
                    //ASIR_ToggleAlarmSiren($activeAlarmSiren, $State);
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

    /**
     * Displays the registered alarm siren.
     */
    public function DisplayRegisteredAlarmSiren(): void
    {
        $registeredAlarmSiren = [];
        $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
        if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
            $alarmSirenSwitch = @IPS_GetObjectIDByIdent('AlarmSiren', $alarmSiren);
        }
        if (isset($alarmSirenSwitch)) {
            if ($alarmSirenSwitch != 0 && IPS_ObjectExists($alarmSirenSwitch)) {
                $registeredVariables = $this->GetMessageList();
                foreach ($registeredVariables as $id => $registeredVariable) {
                    foreach ($registeredVariable as $messageType) {
                        if ($messageType == VM_UPDATE) {
                            if ($id == $alarmSirenSwitch) {
                                $alarmSirenSwitchName = @IPS_GetName($alarmSirenSwitch);
                                $alarmSirenID = @IPS_GetParent($alarmSirenSwitch);
                                $alarmSirenName = @IPS_GetName(@IPS_GetParent($alarmSirenSwitch));
                                array_push($registeredAlarmSiren, ['alarmSirenSwitchID' => $alarmSirenSwitch, 'alarmSirenSwitchName' => $alarmSirenSwitchName, 'alarmSirenInstanceID' => $alarmSirenID, 'alarmSirenInstanceName' => $alarmSirenName]);
                            }
                        }
                    }
                }
            }
        }
        echo "\n\nRegistrierte Alarmsirene:\n\n";
        print_r($registeredAlarmSiren);
    }
}