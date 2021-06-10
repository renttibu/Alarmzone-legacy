<?php

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzone
 */

declare(strict_types=1);

trait AZ_alarmProtocol
{
    private function UpdateAlarmProtocol(string $LogText, int $LogType): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @AP_UpdateMessages($id, $LogText, $LogType);
            $this->SendDebug(__FUNCTION__, 'Das Alarmprotokoll wurde aktualisiert.', 0);
        }
    }
}