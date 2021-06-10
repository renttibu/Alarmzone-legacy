<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzone
 */

declare(strict_types=1);

trait AZ_blacklist
{
    public function AddSensorBlacklist(int $SensorID): void
    {
        $blackList = json_decode($this->GetBuffer('Blacklist'), true);
        array_push($blackList, $SensorID);
        $this->SetBuffer('Blacklist', json_encode(array_unique($blackList)));
        $this->SendDebug(__FUNCTION__, 'Der Sensor mit der ID ' . $SensorID . ' wurde zur Sperrliste hinzugefügt.', 0);
    }

    public function CheckSensorBlacklist(int $SensorID): bool
    {
        if (in_array($SensorID, json_decode($this->GetBuffer('Blacklist'), true))) {
            return true;
        }
        return false;
    }

    public function ResetBlacklist(): void
    {
        $this->SetBuffer('Blacklist', json_encode([]));
        $this->SendDebug(__FUNCTION__, 'Die Sperrliste wurde erfolgreich zurückgesetzt.', 0);
    }
}