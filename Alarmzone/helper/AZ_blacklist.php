<?php

declare(strict_types=1);

trait AZ_blacklist
{
    public function CheckSensorBlacklist(int $SensorID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $blackListedSensors = json_decode($this->GetBuffer('Blacklist'), true);
        if (in_array($SensorID, $blackListedSensors)) {
            return true;
        }
        return false;
    }

    public function AddSensorBlacklist(int $SensorID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $blackList = json_decode($this->GetBuffer('Blacklist'), true);
        array_push($blackList, $SensorID);
        $this->SetBuffer('Blacklist', json_encode(array_unique($blackList)));
    }

    public function ResetBlacklist(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SetBuffer('Blacklist', json_encode([]));
    }
}