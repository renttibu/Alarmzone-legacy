<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmzone
 *
 * @prefix      AZ
 *
 * @file        AZ_smokeDetectors.php
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

trait AZ_smokeDetectors
{
    /**
     * Checks the alerting of a smoke detector.
     *
     * @param int $SenderID
     *
     * @return bool
     * false    = no alarm
     * true     = alarm
     *
     * @throws Exception
     */
    public function CheckSmokeDetectorAlerting(int $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('SmokeDetectors'), true);
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
        if (boolval(GetValue($SenderID)) == boolval($vars[$key]['AlertingValue'])) {
            $result = true;
            $alarmState = 1;
            $alarmName = 'Alarm';
            if ($vars[$key]['SilentAlarm']) {
                $alarmState = 3;
                $alarmName = 'stummen Alarm';
            }
            $this->SetValue('AlarmState', $alarmState);
            //Log
            $timeStamp = date('d.m.Y, H:i:s');
            $location = $this->ReadPropertyString('Location');
            $alarmZoneName = $this->ReadPropertyString('AlarmZoneName');
            $sensorName = $vars[$key]['Name'];
            $text = $sensorName . ' hat Rauch erkannt und einen ' . $alarmName . ' ausgelöst. Bitte prüfen! (ID ' . $SenderID . ')';
            $logText = $timeStamp . ', ' . $location . ', ' . $alarmZoneName . ', ' . $text;
            $this->UpdateAlarmProtocol($logText, 2);
            //Notification
            $actionText = $alarmZoneName . ', Alarm ' . $sensorName . '!';
            $messageText = $timeStamp . ' ' . $sensorName . ' hat einen ' . $alarmName . ' ausgelöst.';
            $this->SendNotification($actionText, $messageText, $logText, 2);
        }
        $this->CheckSmokeDetectorState();
        return $result;
    }

    #################### Private

    /**
     * Checks the state of all smoke detectors.
     *
     * @return bool
     * false    = ok
     * true     = smoke detected
     *
     * @throws Exception
     */
    private function CheckSmokeDetectorState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $state = false;
        $vars = json_decode($this->ReadPropertyString('SmokeDetectors'));
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
        $this->SetValue('SmokeDetectorState', $state);
        return $state;
    }
}