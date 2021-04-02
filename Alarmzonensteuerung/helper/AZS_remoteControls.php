<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzonensteuerung
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait AZS_remoteControls
{
    public function TriggerRemoteControlAction(int $SenderID, bool $ValueChanged): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Sender: ' . $SenderID . ', Wert hat sich geändert: ' . json_encode($ValueChanged), 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('RemoteControls'), true);
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
        $execute = false;
        $type = IPS_GetVariable($SenderID)['VariableType'];
        $value = $vars[$key]['Value'];
        switch ($vars[$key]['Trigger']) {
            case 0: #on change (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                if ($ValueChanged) {
                    $execute = true;
                }
                break;

            case 1: #on update (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                $execute = true;
                break;

            case 2: #on limit drop, once (integer, float)
                switch ($type) {
                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            if (GetValueInteger($SenderID) < intval($value)) {
                                $execute = true;
                            }
                        }
                        break;

                    case 2: #float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                $execute = true;
                            }
                        }
                        break;

                }
                break;

            case 3: #on limit drop, every time (integer, float)
                switch ($type) {
                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        if (GetValueInteger($SenderID) < intval($value)) {
                            $execute = true;
                        }
                        break;

                    case 2: #float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                            $execute = true;
                        }
                        break;

                }
                break;

            case 4: #on limit exceed, once (integer, float)
                switch ($type) {
                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            if (GetValueInteger($SenderID) > intval($value)) {
                                $execute = true;
                            }
                        }
                        break;

                    case 2: #float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                $execute = true;
                            }
                        }
                        break;

                }
                break;

            case 5: #on limit exceed, every time (integer, float)
                switch ($type) {
                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        if (GetValueInteger($SenderID) > intval($value)) {
                            $execute = true;
                        }
                        break;

                    case 2: #float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                            $execute = true;
                        }
                        break;

                }
                break;

            case 6: #on specific value, once (bool, integer, float, string)
                switch ($type) {
                    case 0: #bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                        if ($ValueChanged) {
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if (GetValueBoolean($SenderID) == boolval($value)) {
                                $execute = true;
                            }
                        }
                        break;

                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            if (GetValueInteger($SenderID) == intval($value)) {
                                $execute = true;
                            }
                        }
                        break;

                    case 2: #float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                        if ($ValueChanged) {
                            if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                $execute = true;
                            }
                        }
                        break;

                    case 3: #string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                        if ($ValueChanged) {
                            if (GetValueString($SenderID) == (string) $value) {
                                $execute = true;
                            }
                        }
                        break;

                }
                break;

            case 7: #on specific value, every time (bool, integer, float, string)
                switch ($type) {
                    case 0: #bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if (GetValueBoolean($SenderID) == boolval($value)) {
                            $execute = true;
                        }
                        break;

                    case 1: #integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                        if (GetValueInteger($SenderID) == intval($value)) {
                            $execute = true;
                        }
                        break;

                    case 2: #float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                        if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                            $execute = true;
                        }
                        break;

                    case 3: #string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                        if (GetValueString($SenderID) == (string) $value) {
                            $execute = true;
                        }
                        break;

                }
                break;

        }
        if ($execute) {
            $action = $vars[$key]['Action'];
            $name = $SenderID . ', ' . $vars[$key]['Name'];
            switch ($action) {
                case 1: # disarm
                    $this->DisarmAlarmZones($name);
                    break;

                case 2: # arm full protection mode
                    $this->ToggleFullProtectionMode(true, $name);
                    break;

                case 3: # arm hull protection mode
                    $this->ToggleHullProtectionMode(true, $name);
                    break;

                case 4: # arm partial protection mode
                    $this->TogglePartialProtectionMode(true, $name);
                    break;

                case 5: # use a script
                    $scriptID = $vars[$key]['ScriptID'];
                    if ($scriptID != 0 && @IPS_ObjectExists($scriptID)) {
                        IPS_RunScript($scriptID);
                    }
                    break;

            }
        }
        return $execute;
    }
}