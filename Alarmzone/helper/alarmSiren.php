<?php

// Declare
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
        // Check action
        switch ($State) {
            case false:
                $action = 0;
                break;

            case true:
                if ($this->ReadPropertyInteger('AlertingDelayDuration') > 0) {
                    $action = 2;
                } else {
                    $action = 1;
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
            $scriptText = 'ASIR_ToggleAlarmSiren(' . $alarmSiren . ', ' . (int) $State . ');';
            IPS_RunScriptText($scriptText);
        }

        // Alarm siren script
        $alarmSirenScript = $this->ReadPropertyInteger('AlarmSirenScript');
        if ($alarmSirenScript != 0 && @IPS_ObjectExists($alarmSirenScript)) {
            // Set alarm siren switch
            $this->SetValue('AlarmSiren', $State);
            IPS_RunScriptEx($alarmSirenScript, ['State' => $action]);
        }

        // Check configuration of alarm zone control
        $use = $this->ReadPropertyBoolean('UseAlarmZoneControlAlarmLight');
        if ($use) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {

                // Alarm siren
                $alarmSiren = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmSiren');
                if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
                    // Set alarm siren switch
                    $this->SetValue('AlarmSiren', $State);
                    // Toggle alarm siren
                    $scriptText = 'ASIR_ToggleAlarmSiren(' . $alarmSiren . ', ' . (int) $State . ');';
                    IPS_RunScriptText($scriptText);
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

    /**
     * Displays the registered alarm siren.
     */
    public function DisplayRegisteredAlarmSiren(): void
    {
        $registeredAlarmSiren = [];
        $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
        if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
            $alarmSirenSwitch = IPS_GetObjectIDByIdent('AlarmSiren', $alarmSiren);
        }
        if (isset($alarmSirenSwitch)) {
            if ($alarmSirenSwitch != 0 && @IPS_ObjectExists($alarmSirenSwitch)) {
                $registeredVariables = $this->GetMessageList();
                foreach ($registeredVariables as $id => $registeredVariable) {
                    foreach ($registeredVariable as $messageType) {
                        if ($messageType == VM_UPDATE) {
                            if ($id == $alarmSirenSwitch) {
                                $alarmSirenSwitchName = IPS_GetName($alarmSirenSwitch);
                                $alarmSirenID = IPS_GetParent($alarmSirenSwitch);
                                $alarmSirenName = IPS_GetName(IPS_GetParent($alarmSirenSwitch));
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