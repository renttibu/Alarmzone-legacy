<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmzone
 *
 * @prefix      AZ
 *
 * @file        AZ_doorWindowSensors.php
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

trait AZ_doorWindowSensors
{
    /**
     * Determines the door and window state variables automatically.
     */
    public function DetermineDoorWindowVariables(): void
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
                    if ($object['ObjectIdent'] == 'STATE') {
                        $match = true;
                    }
                    if ($match) {
                        //Check for variable
                        if ($object['ObjectType'] == 2) {
                            $name = strstr(@IPS_GetName($instanceID), ':', true);
                            if ($name == false) {
                                $name = @IPS_GetName($instanceID);
                            }
                            array_push($variables, [
                                'Use'                           => true,
                                'Name'                          => $name,
                                'ID'                            => $childrenID,
                                'AlertingValue'                 => 1,
                                'FullProtectionModeActive'      => true,
                                'HullProtectionModeActive'      => true,
                                'PartialProtectionModeActive'   => true,
                                'SilentAlarm'                   => false]);
                        }
                    }
                }
            }
            //Get already listed variables
            $listedVariables = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
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
                            'Use'                           => true,
                            'Name'                          => $name,
                            'ID'                            => $addVariable,
                            'AlertingValue'                 => 1,
                            'FullProtectionModeActive'      => true,
                            'HullProtectionModeActive'      => true,
                            'PartialProtectionModeActive'   => true,
                            'SilentAlarm'                   => false]);
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
        @IPS_SetProperty($this->InstanceID, 'DoorWindowSensors', $value);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Tür- / Fenstersensoren wurden automatisch ermittelt!';
    }

    /**
     * Checks the alerting of a door and window sensor.
     *
     * @param int $SenderID
     *
     * @throws Exception
     */
    public function CheckDoorWindowSensorAlerting(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $timeStamp = date('d.m.Y, H:i:s');
        $location = $this->ReadPropertyString('Location');
        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
        $useNotification = false;
        $preAlarm = false;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
        if (!empty($doorWindowSensors)) {
            //Check if sensor is listed
            $key = array_search($SenderID, array_column($doorWindowSensors, 'ID'));
            if (is_int($key)) {
                if (!$doorWindowSensors[$key]['Use']) {
                    return;
                }
                $sensorName = $doorWindowSensors[$key]['Name'];
                $actualValue = intval(GetValue($SenderID));
                $alertingValue = intval($doorWindowSensors[$key]['AlertingValue']);
                $stateText = 'geschlossen';
                if ($actualValue == $alertingValue) {
                    $stateText = 'geöffnet';
                }
                //Check alarm zone state
                $alarmZoneState = $this->GetValue('AlarmZoneState');
                switch ($alarmZoneState) {
                    case 0: # disarmed
                        //Log
                        $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        $this->UpdateAlarmProtocol($logText, 0);
                        break;

                    case 1: # armed
                        $alerting = false;
                        $silentAlerting = false;
                        $alarmState = 0;
                        $alertingDelayDuration = 0;
                        //Check if variable is black listed
                        $blackListed = false;
                        $blackListedSensors = json_decode($this->GetBuffer('Blacklist'), true);
                        if (in_array($SenderID, $blackListedSensors)) {
                            $blackListed = true;
                        }
                        //Variable is black listed
                        if ($blackListed) {
                            $messageType = 0;
                            if ($actualValue != $alertingValue) {
                                $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                            } else {
                                $text = $sensorName . ' wurde ohne Alarmauslösung ' . $stateText . '. (ID ' . $SenderID . ')';
                            }
                        }
                        //Variable is not black listed
                        else {
                            //Check alerting value
                            if ($actualValue == $alertingValue) {
                                //Check if sensor is activated for full protection mode
                                if ($this->GetValue('FullProtectionMode')) {
                                    if ($doorWindowSensors[$key]['FullProtectionModeActive']) {
                                        if ($doorWindowSensors[$key]['SilentAlarm']) {
                                            $silentAlerting = true;
                                        } else {
                                            $alerting = true;
                                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayFullProtectionMode');
                                        }
                                    }
                                }
                                //Check if sensor is activated for hull protection mode
                                if ($this->GetValue('HullProtectionMode')) {
                                    if ($doorWindowSensors[$key]['HullProtectionModeActive']) {
                                        if ($doorWindowSensors[$key]['SilentAlarm']) {
                                            $silentAlerting = true;
                                        } else {
                                            $alerting = true;
                                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayHullProtectionMode');
                                        }
                                    }
                                }
                                //Check if sensor is activated for partial protection mode
                                if ($this->GetValue('PartialProtectionMode')) {
                                    if ($doorWindowSensors[$key]['PartialProtectionModeActive']) {
                                        if ($doorWindowSensors[$key]['SilentAlarm']) {
                                            $silentAlerting = true;
                                        } else {
                                            $alerting = true;
                                            $alertingDelayDuration = $this->ReadPropertyInteger('AlertingDelayPartialProtectionMode');
                                        }
                                    }
                                }
                            }
                            $alarmName = 'Alarm';
                            if ($alerting) {
                                if ($alertingDelayDuration > 0) {
                                    $preAlarm = true;
                                    $alarmName = 'Voralarm';
                                }
                            }
                            if ($silentAlerting) {
                                $alarmName = 'Stummen Alarm';
                            }
                            if ($actualValue != $alertingValue) {
                                $text = $sensorName . ' wurde ' . $stateText . '. (ID ' . $SenderID . ')';
                                $messageType = 0;
                            } else {
                                $text = $sensorName . ' wurde ' . $stateText . ' und hat einen ' . $alarmName . ' ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
                                $messageType = 2;
                            }
                        }
                        //Log
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        $this->UpdateAlarmProtocol($logText, $messageType);
                        //Check alerting
                        if ($silentAlerting) {
                            $alarmState = 3;
                        }
                        if ($alerting) {
                            $alarmState = 1;
                            if ($alertingDelayDuration > 0) {
                                $alarmState = 2;
                                $this->SetTimerInterval('SetAlarmState', $alertingDelayDuration * 1000);
                            }
                        }
                        if ($alerting || $silentAlerting) {
                            $this->SetValue('AlarmState', $alarmState);
                            $this->SetValue('DoorWindowState', true);
                            //Notification
                            $alarmSymbol = $this->ReadPropertyString('AlarmSymbol');
                            if (!empty($alarmSymbol)) {
                                $actionText = $alarmSymbol . ' ' . $alarmZoneName . ', Alarm ' . $sensorName . '!';
                            } else {
                                $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
                            }
                            $messageText = $timeStamp . ' ' . $sensorName . ' hat einen Alarm ausgelöst.';
                            if ($preAlarm) {
                                $alarmSymbol = $this->ReadPropertyString('PreAlarmSymbol');
                                if (!empty($alarmSymbol)) {
                                    $actionText = $alarmSymbol . ' ' . $alarmZoneName . ', Voralarm ' . $sensorName . '!';
                                } else {
                                    $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
                                }
                                $messageText = $timeStamp . ' ' . $sensorName . ' hat einen Voralarm ausgelöst.';
                            }
                            $this->SendNotification($actionText, $messageText, $logText, 2);
                        }
                        break;

                    case 2: # delayed
                        $useNotification = true;
                        break;

                }
            }
        }
        $this->CheckDoorWindowState($useNotification);
    }

    #################### Blacklist

    /**
     * Resets the blacklist.
     */
    public function ResetBlacklist(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SetBuffer('Blacklist', json_encode([]));
    }

    #################### Private

    /**
     * Checks the state of all door and window sensors.
     *
     * @param bool $UseNotification
     * false    = don't notify
     * true     = notify
     *
     * @return bool
     * false    = closed
     * true     = opened
     *
     * @throws Exception
     */
    private function CheckDoorWindowState(bool $UseNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $timeStamp = date('d.m.Y, H:i:s');
        //Check all door and window sensors
        $doorWindowState = false;
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                if (!$doorWindowSensor->Use) {
                    continue;
                }
                $id = $doorWindowSensor->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    continue;
                }
                //Check actual value and alerting value
                $actualValue = boolval(GetValue($id));
                $alertingValue = boolval($doorWindowSensor->AlertingValue);
                if ($actualValue == $alertingValue) {
                    $doorWindowState = true;
                    //Inform user, create log entry and add to blacklist
                    if ($UseNotification) {
                        //Log
                        $location = $this->ReadPropertyString('Location');
                        $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
                        $sensorName = $doorWindowSensor->Name;
                        $text = $sensorName . ' ist noch geöffnet. Bitte prüfen! (ID ' . $id . ')';
                        $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
                        $this->UpdateAlarmProtocol($logText, 0);
                        //Notification
                        $actionText = $alarmZoneName . ', ' . $sensorName . ' ist noch geöffnet!';
                        $messageText = $timeStamp . ' ' . $sensorName . ' ist noch geöffnet.';
                        $this->SendNotification($actionText, $messageText, $logText, 1);
                        //Update blacklist
                        $blackList = json_decode($this->GetBuffer('Blacklist'), true);
                        array_push($blackList, $id);
                        $this->SetBuffer('Blacklist', json_encode(array_unique($blackList)));
                    }
                }
            }
        }
        $this->SetValue('DoorWindowState', $doorWindowState);
        return $doorWindowState;
    }
}