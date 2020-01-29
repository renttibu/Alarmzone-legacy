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

        // Alarm light
        $alarmLight = $this->ReadPropertyInteger('AlarmLight');
        if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
            // Set alarm light switch
            $this->SetValue('AlarmLight', $State);
            // Toggle alarm light
            $scriptText = 'ABEL_ToggleAlarmLight(' . $alarmLight . ', ' . (int) $State . ');';
            IPS_RunScriptText($scriptText);
        }

        // Alarm light script
        $alarmLightScript = $this->ReadPropertyInteger('AlarmLightScript');
        if ($alarmLightScript != 0 && IPS_ObjectExists($alarmLightScript)) {
            // Set alarm light switch
            $this->SetValue('AlarmLight', $State);
            // Execute Script
            IPS_RunScriptEx($alarmLightScript, ['Action' => $action]);
        }

        // Check configuration of alarm zone control
        $use = $this->ReadPropertyBoolean('UseAlarmZoneControlAlarmLight');
        if ($use) {
            $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
            if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {

                // Alarm light
                $alarmLight = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmLight');
                if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
                    // Set alarm light switch
                    $this->SetValue('AlarmLight', $State);
                    // Toggle alarm light
                    $scriptText = 'ABEL_ToggleAlarmLight(' . $alarmLight . ', ' . (int) $State . ');';
                    IPS_RunScriptText($scriptText);
                }

                // Alarm light script
                $alarmLightScript = (int) @IPS_GetProperty($alarmZoneControl, 'AlarmLightScript');
                if ($alarmLightScript != 0 && @IPS_ObjectExists($alarmLightScript)) {
                    // Set alarm light switch
                    $this->SetValue('AlarmLight', $State);
                    // Execute script
                    IPS_RunScriptEx($alarmLightScript, ['Action' => $action]);
                }
            }
        }
    }

    /**
     * Displays the registered alarm light.
     */
    public function DisplayRegisteredAlarmLight(): void
    {
        $registeredAlarmLight = [];
        $alarmLight = $this->ReadPropertyInteger('AlarmLight');
        if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
            $alarmLightSwitch = IPS_GetObjectIDByIdent('AlarmLight', $alarmLight);
        }
        if (isset($alarmLightSwitch)) {
            if ($alarmLightSwitch != 0 && @IPS_ObjectExists($alarmLightSwitch)) {
                $registeredVariables = $this->GetMessageList();
                foreach ($registeredVariables as $id => $registeredVariable) {
                    foreach ($registeredVariable as $messageType) {
                        if ($messageType == VM_UPDATE) {
                            if ($id == $alarmLightSwitch) {
                                $alarmLightSwitchName = IPS_GetName($alarmLightSwitch);
                                $alarmLightID = IPS_GetParent($alarmLightSwitch);
                                $alarmLightName = IPS_GetName(IPS_GetParent($alarmLightSwitch));
                                array_push($registeredAlarmLight, ['alarmLightSwitchID' => $alarmLightSwitch, 'alarmLightSwitchName' => $alarmLightSwitchName, 'alarmLightInstanceID' => $alarmLightID, 'alarmLightInstanceName' => $alarmLightName]);
                            }
                        }
                    }
                }
            }
        }

        echo "\n\nRegistrierte Alarmbeleuchtung:\n\n";
        print_r($registeredAlarmLight);
    }
}