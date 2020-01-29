<?php

// Declare
declare(strict_types=1);

trait AZON_remoteControls
{
    /**
     * Executes the action of a remote control.
     *
     * @param int $SenderID
     */
    public function ExecuteRemoteControlAction(int $SenderID): void
    {
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
                    // 1: Disarm absence mode
                    case 1:
                        $this->ToggleAbsenceMode(false, $name, true, true);
                        break;

                    // 2: Arm absence mode
                    case 2:
                        $this->ToggleAbsenceMode(true, $name, true, true);
                        break;

                    // 3: Disarm presence mode
                    case 3:
                        $this->TogglePresenceMode(false, $name, true, true);
                        break;

                    // 4: Arm presence mode
                    case 4:
                        $this->TogglePresenceMode(true, $name, true, true);
                        break;

                    // 5: Disarm night mode
                    case 5:
                        $this->ToggleNightMode(false, $name, true, true);
                        break;

                    // 6: Arm night mode
                    case 6:
                        $this->ToggleNightMode(true, $name, true, true);
                        break;

                    // 7: Alarm siren off
                    case 7:
                        $this->ToggleAlarmSiren(false);
                        break;

                    // 8: Alarm siren on
                    case 8:
                        $this->ToggleAlarmSiren(true);
                        break;

                    // 9: Alarm light off
                    case 9:
                        $this->ToggleAlarmLight(false);
                        break;

                    // 10: Alarm light on
                    case 10:
                        $this->ToggleAlarmLight(true);
                        break;

                    // 11: Script
                    case 11:
                        $scriptID = $remoteControl->ScriptID;
                        if ($scriptID != 0 && @IPS_ObjectExists($scriptID)) {
                            IPS_RunScript($scriptID);
                        }
                        break;
                }
            }
        }
    }

    /**
     * Displays the registered remote controls.
     */
    public function DisplayRegisteredRemoteControls(): void
    {
        $registeredRemoteControls = [];
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $id => $registeredVariable) {
            foreach ($registeredVariable as $messageType) {
                if ($messageType == VM_UPDATE) {
                    // Remote Controls
                    $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'), true);
                    if (!empty($remoteControls)) {
                        $key = array_search($id, array_column($remoteControls, 'ID'));
                        if (is_int($key)) {
                            $name = $remoteControls[$key]['Name'];
                            $action = $remoteControls[$key]['Action'];
                            array_push($registeredRemoteControls, ['id' => $id, 'name' => $name, 'action' => $action]);
                        }
                    }
                }
            }
        }
        sort($registeredRemoteControls);

        echo "\n\nRegistrierte Handsender:\n\n";
        print_r($registeredRemoteControls);
    }
}