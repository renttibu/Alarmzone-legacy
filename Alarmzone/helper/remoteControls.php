<?php

// Declare
declare(strict_types=1);

trait AZON_remoteControls
{
    /**
     * Triggers a remote control action.
     *
     * @param int $SenderID
     */
    public function TriggerRemoteControlAction(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Remote controls
        $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'));
        if (empty($remoteControls)) {
            return;
        }
        foreach ($remoteControls as $remoteControl) {
            if ($remoteControl->ID == $SenderID && $remoteControl->Use) {
                $action = $remoteControl->Action;
                $name = $remoteControl->Name;
                switch ($action) {
                    // Disarm alarm zone
                    case 0:
                        $this->DisarmAlarmZone($name, true, true);
                        break;
                    // Arm full protection  mode
                    case 1:
                        $this->ToggleFullProtectMode(true, $name, true, true);
                        break;

                    // Arm hull protection mode
                    case 2:
                        $this->ToggleHullProtectMode(true, $name, true, true);
                        break;

                    // Arm partial protection mode
                    case 3:
                        $this->TogglePartialProtectMode(true, $name, true, true);
                        break;

                    // Alarm siren off
                    case 4:
                        $this->ToggleAlarmSiren(false);
                        break;

                    // Alarm siren on
                    case 5:
                        $this->ToggleAlarmSiren(true);
                        break;

                    // Alarm light off
                    case 6:
                        $this->ToggleAlarmLight(false);
                        break;

                    // Alarm light on
                    case 7:
                        $this->ToggleAlarmLight(true);
                        break;

                    // Script
                    case 8:
                        $scriptID = $remoteControl->ScriptID;
                        if ($scriptID != 0 && @IPS_ObjectExists($scriptID)) {
                            IPS_RunScript($scriptID);
                        }
                        break;

                }
            }
        }
    }
}