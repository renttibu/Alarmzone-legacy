<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzonensteuerung
 */

/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait AZS_updateStates
{
    public function UpdateStates(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $result = true;
        $update1 = $this->UpdateFullProtectionMode();
        $update2 = $this->UpdateHullProtectionMode();
        $update3 = $this->UpdatePartialProtectionMode();
        $update4 = $this->UpdateSystemState();
        $update5 = $this->UpdateAlarmState();
        $update6 = $this->UpdateAlertingSensor();
        $update7 = $this->UpdateDoorWindowState();
        $update8 = $this->UpdateMotionDetectorState();
        $update9 = $this->UpdateAlarmSiren();
        $update10 = $this->UpdateAlarmLight();
        $update11 = $this->UpdateAlarmCall();
        if (!$update1 ||
            !$update2 ||
            !$update3 ||
            !$update4 ||
            !$update5 ||
            !$update6 ||
            !$update7 ||
            !$update8 ||
            !$update9 ||
            !$update10 ||
            !$update11) {
            $result = false;
        }
        return $result;
    }

    public function UpdateFullProtectionMode(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->ReadAttributeBoolean('DisableUpdateMode')) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('FullProtectionMode'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = false;
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) {
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('FullProtectionMode', $state);
        return $result;
    }

    public function UpdateHullProtectionMode(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->ReadAttributeBoolean('DisableUpdateMode')) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('HullProtectionMode'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = false;
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) {
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('HullProtectionMode', $state);
        return $result;
    }

    public function UpdatePartialProtectionMode(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->ReadAttributeBoolean('DisableUpdateMode')) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('PartialProtectionMode'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = false;
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) {
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('PartialProtectionMode', $state);
        return $result;
    }

    public function UpdateSystemState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('SystemState'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = 0; # disarmed
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueInteger($var['ID']);
                    if ($actualValue == 1) { # armed
                        $state = 1;
                        break;
                    }
                    if ($actualValue == 2) { # delayed
                        $state = 2;
                    }
                }
            }
        }
        $this->SetValue('SystemState', $state);
        return $result;
    }

    public function UpdateAlarmState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('AlarmState'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = 0; # no alarm
        $alarm = false;
        $preAlarm = false;
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueInteger($var['ID']);
                    if ($actualValue == 1) { # alarm
                        $alarm = true;
                    }
                    if ($actualValue == 2) { # pre alarm
                        $preAlarm = true;
                    }
                }
            }
        }
        if ($preAlarm) {
            $state = 2;
        }
        if ($alarm) {
            $state = 1;
        }
        $this->SetValue('AlarmState', $state);
        return $result;
    }

    public function UpdateAlertingSensor(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = false;
        $sensorName = 'OK';
        $vars = json_decode($this->ReadPropertyString('AlertingSensor'), true);
        if (!empty($vars)) {
            foreach ($vars as $var) {
                if ($var['Use']) {
                    $id = $var['ID'];
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $result = true;
                        $actualValue = GetValueString($id);
                        if ($actualValue != 'OK') {
                            $sensorName = $actualValue;
                        }
                    }
                }
            }
        }
        $this->SetValue('AlertingSensor', $sensorName);
        return $result;
    }

    public function UpdateDoorWindowState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('DoorWindowState'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = false;
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) {
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('DoorWindowState', $state);
        return $result;
    }

    public function UpdateMotionDetectorState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('MotionDetectorState'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = false;
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) {
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('MotionDetectorState', $state);
        return $result;
    }

    public function UpdateAlarmSiren(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('AlarmSiren'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = 0; # off
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) { # on
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('AlarmSiren', $state);
        return $result;
    }

    public function UpdateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('AlarmLight'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = 0; # off
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) { # on
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('AlarmLight', $state);
        return $result;
    }

    public function UpdateAlarmCall(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('AlarmCall'), true);
        if (empty($vars)) {
            return false;
        }
        $result = false;
        $state = 0; # off
        foreach ($vars as $var) {
            if ($var['Use']) {
                $id = $var['ID'];
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $result = true;
                    $actualValue = GetValueBoolean($var['ID']);
                    if ($actualValue) { # on
                        $state = true;
                    }
                }
            }
        }
        $this->SetValue('AlarmCall', $state);
        return $result;
    }
}