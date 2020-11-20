<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmzone
 *
 * @prefix      AZ
 *
 * @file        AZ_motionDetectors.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Alarmzone
 *
 */

declare(strict_types=1);

trait AZ_motionDetectors
{
    /**
     * Determines the door and window state variables automatically.
     */
    public function DetermineMotionDetectorVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $listedVariables = [];
        $instanceIDs = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
        if (!empty($instanceIDs)) {
            $variables = [];
            foreach ($instanceIDs as $instanceID) {
                $childrenIDs = @IPS_GetChildrenIDs($instanceID);
                foreach ($childrenIDs as $childrenID) {
                    $match = false;
                    $object = @IPS_GetObject($childrenID);
                    if ($object['ObjectIdent'] == 'MOTION') {
                        $match = true;
                    }
                    if ($match) {
                        if ($object['ObjectType'] == 2) {
                            $name = strstr(@IPS_GetName($instanceID), ':', true);
                            if ($name == false) {
                                $name = @IPS_GetName($instanceID);
                            }
                            array_push($variables, [
                                'Use'                         => true,
                                'Name'                        => $name,
                                'ID'                          => $childrenID,
                                'AlertingValue'               => 1,
                                'FullProtectionModeActive'    => true,
                                'HullProtectionModeActive'    => false,
                                'PartialProtectionModeActive' => true]);
                        }
                    }
                }
            }
            //Get already listed variables
            $listedVariables = json_decode($this->ReadPropertyString('MotionDetectors'), true);
            //Delete the variables that no longer exist
            if (!empty($listedVariables)) {
                $deleteVariables = array_diff(array_column($listedVariables, 'ID'), array_column($variables, 'ID'));
                if (!empty($deleteVariables)) {
                    foreach ($deleteVariables as $key => $deleteVariable) {
                        unset($listedVariables[$key]);
                    }
                }
            }
            //Add new variables
            if (!empty($listedVariables)) {
                $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
                if (!empty($addVariables)) {
                    foreach ($addVariables as $addVariable) {
                        $name = strstr(@IPS_GetName(@IPS_GetParent($addVariable)), ':', true);
                        array_push($listedVariables, [
                            'Use'                         => true,
                            'Name'                        => $name,
                            'ID'                          => $addVariable,
                            'AlertingValue'               => 1,
                            'FullProtectionModeActive'    => true,
                            'HullProtectionModeActive'    => false,
                            'PartialProtectionModeActive' => true]);
                    }
                }
            } else {
                $listedVariables = $variables;
            }
        }
        //Sort variables by name
        usort($listedVariables, function ($a, $b)
        {
            return $a['Name'] <=> $b['Name'];
        });
        $listedVariables = array_values($listedVariables);
        //Update variable list
        $value = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'MotionDetectors', $value);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Bewegungsmelder wurden automatisch ermittelt!';
    }

    /**
     * Checks the alerting of a motion detector.
     *
     * @param int $SenderID
     *
     * @return bool
     * false    = no alarm
     * true     = alarm
     *
     * @throws Exception
     */
    public function CheckMotionDetectorAlerting(int $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('MotionDetectors'), true);
        if (empty($vars)) {
            return false;
        }
        $key = array_search($SenderID, array_column($vars, 'ID'));
        if (!is_int($key)) {
            return false;
        }
        if (!$vars[$key]['Use']) {
            return false;
        }
        $result = false;
        $alarmZoneState = $this->GetValue('AlarmZoneState');
        switch ($alarmZoneState) {
            case 1: # armed
                $alerting = false;
                $silentAlerting = false;
                $alarmState = 0;
                $alertingDelayDuration = 0;
                //Check alerting value
                if (boolval(GetValue($SenderID)) == boolval($vars[$key]['AlertingValue'])) {
                    //Check if motion detector is activated for absence mode
                    if ($this->GetValue('FullProtectionMode')) {
                        if ($vars[$key]['FullProtectionModeActive']) {
                            if ($vars[$key]['SilentAlarm']) {
                                $silentAlerting = true;
                            } else {
                                $alerting = true;
                                $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayFullProtectionMode');
                            }
                        }
                    }
                    //Check if motion detector is activated for presence mode
                    if ($this->GetValue('HullProtectionMode')) {
                        if ($vars[$key]['HullProtectionModeActive']) {
                            if ($vars[$key]['SilentAlarm']) {
                                $silentAlerting = true;
                            } else {
                                $alerting = true;
                                $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayHullProtectionMode');
                            }
                        }
                    }
                    //Check if motion detector is activated for night mode
                    if ($this->GetValue('PartialProtectionMode')) {
                        if ($vars[$key]['PartialProtectionModeActive']) {
                            if ($vars[$key]['SilentAlarm']) {
                                $silentAlerting = true;
                            } else {
                                $alerting = true;
                                $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayPartialProtectionMode');
                            }
                        }
                    }
                    //Check alerting
                    $alarmName = 'Alarm';
                    if ($alerting) {
                        $alarmState = 1;
                        $alarmName = 'Alarm';
                        if ($alertingDelayDuration > 0) {
                            $alarmState = 2;
                            $alarmName = 'Voralarm';
                            $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                        }
                    }
                    if ($silentAlerting) {
                        $alarmName = 'Stummen Alarm';
                    }
                    if ($alerting || $silentAlerting) {
                        $result = true;
                        //Log
                        $timeStamp = date('d.m.Y, H:i:s');
                        $location = $this->ReadPropertyString('Location');
                        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
                        $sensorName = $vars[$key]['Name'];
                        $text = $sensorName . ' hat eine Bewegung erkannt und einen ' . $alarmName . ' ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        $this->UpdateAlarmProtocol($logText, 2);
                        $this->SetValue('AlarmState', $alarmState);
                        $this->SetValue('MotionDetectorState', true);
                        //Notification
                        $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
                        $messageText = $timeStamp . ' ' . $sensorName . ' hat einen Alarm ausgelöst.';
                        $this->SendNotification($actionText, $messageText, $logText, 2);
                    }
                }
                break;

        }
        $this->CheckMotionDetectorState();
        return $result;
    }

    #################### Private

    /**
     * Checks the state of all motion detectors.
     *
     * @return bool
     * false    = ok
     * true     = motion detected
     *
     * @throws Exception
     */
    private function CheckMotionDetectorState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $state = false;
        $vars = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                if (!$var->Use) {
                    continue;
                }
                $id = $var->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    continue;
                }
                if (boolval(GetValue($id)) == boolval($var->AlertingValue)) {
                    $state = true;
                }
            }
        }
        $this->SetValue('MotionDetectorState', $state);
        return $state;
    }
}