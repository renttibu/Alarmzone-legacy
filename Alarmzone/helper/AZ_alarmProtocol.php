<?php

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUndefinedFunctionInspection */

/*
 * @module      Alarmzone
 *
 * @prefix      AZ
 *
 * @file        AZ_alarmProtocol.php
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

trait AZ_alarmProtocol
{
    #################### Private

    private function UpdateAlarmProtocol(string $LogText, int $LogType): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            return;
        }
        @AP_UpdateMessages($id, $LogText, $LogType);
        /*
        $protocol = 'AP_UpdateMessages(' . $id . ', "' . $LogText . '", ' . $LogType . ');';
        @IPS_RunScriptText($protocol);
         */
    }
}