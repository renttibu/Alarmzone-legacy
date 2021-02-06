<?php

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait AZS_controlAlarmZones
{
    /**
     * Determines the variables of the alarm zones automatically.
     */
    public function DetermineAlarmZoneVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return;
        }
        $fullProtectionMode = [];
        $hullProtectionMode = [];
        $partialProtectionMode = [];
        $systemState = [];
        $alarmState = [];
        $alertingSensor = [];
        $doorWindowState = [];
        $motionDetectorState = [];
        $alarmSiren = [];
        $alarmLight = [];
        $alarmCall = [];
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            $description = $alarmZone->Description;
            if ($id == 0 || !@IPS_ObjectExists($id)) {
                continue;
            }
            $children = IPS_GetChildrenIDs($id);
            if (empty($children)) {
                continue;
            }
            foreach ($children as $child) {
                if ($child == 0 || !@IPS_ObjectExists($child)) {
                    continue;
                }
                $ident = IPS_GetObject($child)['ObjectIdent'];
                switch ($ident) {
                    case 'FullProtectionMode':
                        array_push($fullProtectionMode, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'HullProtectionMode':
                        array_push($hullProtectionMode, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'PartialProtectionMode':
                        array_push($partialProtectionMode, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'AlarmZoneState':
                        array_push($systemState, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'AlarmState':
                        array_push($alarmState, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'AlertingSensor':
                        array_push($alertingSensor, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'DoorWindowState':
                        array_push($doorWindowState, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'MotionDetectorState':
                        array_push($motionDetectorState, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'AlarmSiren':
                        array_push($alarmSiren, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'AlarmLight':
                        array_push($alarmLight, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                    case 'AlarmCall':
                        array_push($alarmCall, ['Use' => true, 'ID' => $child, 'Description' => $description]);
                        break;

                }
            }
        }
        @IPS_SetProperty($this->InstanceID, 'FullProtectionMode', json_encode($fullProtectionMode));
        @IPS_SetProperty($this->InstanceID, 'HullProtectionMode', json_encode($hullProtectionMode));
        @IPS_SetProperty($this->InstanceID, 'PartialProtectionMode', json_encode($partialProtectionMode));
        @IPS_SetProperty($this->InstanceID, 'SystemState', json_encode($systemState));
        @IPS_SetProperty($this->InstanceID, 'AlarmState', json_encode($alarmState));
        @IPS_SetProperty($this->InstanceID, 'AlertingSensor', json_encode($alertingSensor));
        @IPS_SetProperty($this->InstanceID, 'DoorWindowState', json_encode($doorWindowState));
        @IPS_SetProperty($this->InstanceID, 'MotionDetectorState', json_encode($motionDetectorState));
        @IPS_SetProperty($this->InstanceID, 'AlarmSiren', json_encode($alarmSiren));
        @IPS_SetProperty($this->InstanceID, 'AlarmLight', json_encode($alarmLight));
        @IPS_SetProperty($this->InstanceID, 'AlarmCall', json_encode($alarmCall));
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Variablen wurden automatisch ermittelt!';
    }

    /**
     * Disarms the alarm zones.
     *
     * @param string $Sender
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function DisarmAlarmZones(string $Sender): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return false;
        }
        $result = true;
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                return false;
            }
            /*
            $scriptText = 'AZ_DisarmAlarmZone(' . $id . ', "' . $Sender . '");';
            $response = @IPS_RunScriptText($scriptText);
             */
            $response = @AZ_DisarmAlarmZone($id, $Sender);
            if (!$response) {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Toggles the full protection mode.
     *
     * @param bool $State
     * false    = disarm
     * true     = arm
     *
     * @param string $Sender
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function ToggleFullProtectionMode(bool $State, string $Sender): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $vars = json_decode($this->ReadPropertyString('FullProtectionMode'), true);
        if (empty($vars)) {
            return false;
        }
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
        }
        $result = true;
        $this->SetValue('FullProtectionMode', $State);
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $parentID = IPS_GetParent($id);
                    if (is_int($parentID)) {
                        if ($parentID != 0 && @IPS_ObjectExists($parentID)) {
                            $this->WriteAttributeBoolean('DisableUpdateMode', true);
                            $response = @AZ_ToggleFullProtectionMode($parentID, $State, $Sender);
                            /*
                            $scriptText = 'AZ_ToggleFullProtectionMode(' . $parentID . ', ' . $State . ', "' . $Sender . '");';
                            $response = @IPS_RunScriptText($scriptText);
                             */
                            if (!$response) {
                                $result = false;
                            }
                        }
                    }
                }
            }
        }
        $this->WriteAttributeBoolean('DisableUpdateMode', false);
        $this->UpdateFullProtectionMode();
        return $result;
    }

    /**
     * Toggles the hull protection mode.
     *
     * @param bool $State
     * false    = disarm
     * true     = arm
     *
     * @param string $Sender
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function ToggleHullProtectionMode(bool $State, string $Sender): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $vars = json_decode($this->ReadPropertyString('HullProtectionMode'), true);
        if (empty($vars)) {
            return false;
        }
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
        }
        $result = true;
        $this->SetValue('HullProtectionMode', $State);
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $parentID = IPS_GetParent($id);
                    if (is_int($parentID)) {
                        if ($parentID != 0 && @IPS_ObjectExists($parentID)) {
                            $this->WriteAttributeBoolean('DisableUpdateMode', true);
                            $response = @AZ_ToggleHullProtectionMode($parentID, $State, $Sender);
                            /*
                            $scriptText = 'AZ_ToggleHullProtectionMode(' . $parentID . ', ' . $State . ', "' . $Sender . '");';
                            $response = @IPS_RunScriptText($scriptText);
                             */
                            if (!$response) {
                                $result = false;
                            }
                        }
                    }
                }
            }
        }
        $this->WriteAttributeBoolean('DisableUpdateMode', false);
        $this->UpdateHullProtectionMode();
        return $result;
    }

    /**
     * Toggles the partial protection mode.
     *
     * @param bool $State
     * false    = disarm
     * true     = arm
     *
     * @param string $Sender
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function TogglePartialProtectionMode(bool $State, string $Sender): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $vars = json_decode($this->ReadPropertyString('PartialProtectionMode'), true);
        if (empty($vars)) {
            return false;
        }
        if ($State) {
            if ($this->CheckMaintenanceMode()) {
                return false;
            }
        }
        $result = true;
        $this->SetValue('PartialProtectionMode', $State);
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $parentID = IPS_GetParent($id);
                    if (is_int($parentID)) {
                        if ($parentID != 0 && @IPS_ObjectExists($parentID)) {
                            $this->WriteAttributeBoolean('DisableUpdateMode', true);
                            $response = @AZ_TogglePartialProtectionMode($parentID, $State, $Sender);
                            /*
                            $scriptText = 'AZ_TogglePartialProtectionMode(' . $parentID . ', ' . $State . ', "' . $Sender . '");';
                            $response = @IPS_RunScriptText($scriptText);
                             */
                            if (!$response) {
                                $result = false;
                            }
                        }
                    }
                }
            }
        }
        $this->WriteAttributeBoolean('DisableUpdateMode', false);
        $this->UpdatePartialProtectionMode();
        return $result;
    }
}