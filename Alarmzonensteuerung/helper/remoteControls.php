<?php

// Declare
declare(strict_types=1);

trait AZST_remoteControls
{
    /**
     * Determines the pressed button of a remote control and executes the assigned action.
     *
     * @param int $SenderID
     */
    public function ExecuteRemoteControlCommand(int $SenderID): void
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
                        $this->ToggleAbsenceMode(false, $name);
                        break;

                    // 2: Arm absence mode
                    case 2:
                        $this->ToggleAbsenceMode(true, $name);
                        break;

                    // 3: Disarm presence mode
                    case 3:
                        $this->TogglePresenceMode(false, $name);
                        break;

                    // 4: Arm presence mode
                    case 4:
                        $this->TogglePresenceMode(true, $name);
                        break;

                    // 5: Disarm night mode
                    case 5:
                        $this->ToggleNightMode(false, $name);
                        break;

                    // 6: Arm night mode
                    case 6:
                        $this->ToggleNightMode(true, $name);
                        break;

                    // 7: Turn alarm siren off
                    case 7:
                        $this->ToggleAlarmSiren(false);
                        break;

                    // 8: Turn alarm siren on
                    case 8:
                        $this->ToggleAlarmSiren(true);
                        break;

                    // 9: Turn alarm light off
                    case 9:
                        $this->ToggleAlarmLight(false);
                        break;

                    // 10: Turn alarm light on
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
                            array_push($registeredRemoteControls, ['name' => $name, 'id' => $id, 'action' => $action]);
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