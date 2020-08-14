<?php

declare(strict_types=1);

trait AZST_updateStates
{
    /**
     * Updates the system state.
     */
    public function UpdateStates(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return;
        }
        // Definitions
        $newFullProtectModeState = false;
        $newHullProtectModeState = false;
        $newPartialProtectModeState = false;
        $newAlarmSirenState = false;
        $newAlarmLightState = false;
        $newSystemState = 0;
        $delayed = false;
        $newAlarmState = 0;
        $newDoorWindowState = false;
        $newMotionDetectorState = false;
        $newSmokeDetectorState = false;
        $newWaterSensorState = false;
        // Alarm zones
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            // Full protection mode
            $actualFullProtectModeState = GetValue(IPS_GetObjectIDByIdent('FullProtectionMode', $id));
            if ($actualFullProtectModeState) {
                $newFullProtectModeState = true;
            }
            // Hull protection mode
            $actualHullProtectModeState = GetValue(IPS_GetObjectIDByIdent('HullProtectionMode', $id));
            if ($actualHullProtectModeState) {
                $newHullProtectModeState = true;
            }
            // Partial protection mode
            $actualPartialProtectModeState = GetValue(IPS_GetObjectIDByIdent('PartialProtectionMode', $id));
            if ($actualPartialProtectModeState) {
                $newPartialProtectModeState = true;
            }
            // Alarm siren
            $actualAlarmSirenState = GetValue(IPS_GetObjectIDByIdent('AlarmSiren', $id));
            if ($actualAlarmSirenState) {
                $newAlarmSirenState = true;
            }
            // Alarm light
            $actualAlarmLightState = GetValue(IPS_GetObjectIDByIdent('AlarmLight', $id));
            if ($actualAlarmLightState) {
                $newAlarmLightState = true;
            }
            // Alarm zone state
            $actualAlarmZoneState = GetValue(IPS_GetObjectIDByIdent('AlarmZoneState', $id));
            if ($actualAlarmZoneState == 1) {
                $newSystemState = 1;
            }
            if ($actualAlarmZoneState == 2) {
                $delayed = true;
                $newSystemState = 2;
            }
            // Alarm state
            $actualAlarmState = GetValue(IPS_GetObjectIDByIdent('AlarmState', $id));
            if ($actualAlarmState == 1) {
                $newAlarmState = 1;
            }
            if ($actualAlarmState == 2) {
                $newAlarmState = 2;
            }
            // Door and window state
            $actualDoorWindowState = GetValue(IPS_GetObjectIDByIdent('DoorWindowState', $id));
            if ($actualDoorWindowState) {
                $newDoorWindowState = true;
            }
            // Motion detector state
            $actualMotionDetectorState = GetValue(IPS_GetObjectIDByIdent('MotionDetectorState', $id));
            if ($actualMotionDetectorState) {
                $newMotionDetectorState = true;
            }
            // Smoke detector state
            $actualSmokeDetectorState = GetValue(IPS_GetObjectIDByIdent('SmokeDetectorState', $id));
            if ($actualSmokeDetectorState) {
                $newSmokeDetectorState = true;
            }
            // Waters sensor state
            $actualWaterSensorState = GetValue(IPS_GetObjectIDByIdent('WaterSensorState', $id));
            if ($actualWaterSensorState) {
                $newWaterSensorState = true;
            }
        }
        // Control center
        // Full protection mode
        $this->SetValue('FullProtectionMode', $newFullProtectModeState);
        // Presence  mode
        $this->SetValue('HullProtectionMode', $newHullProtectModeState);
        // Partial protection mode
        $this->SetValue('PartialProtectionMode', $newPartialProtectModeState);
        // Alarm siren
        $this->SetValue('AlarmSiren', $newAlarmSirenState);
        // Alarm light
        $this->SetValue('AlarmLight', $newAlarmLightState);
        // System state
        if ($delayed) {
            $this->SetValue('SystemState', $newSystemState);
        } else {
            $this->SetValue('SystemState', $newSystemState);
        }
        // Alarm state
        $this->SetValue('AlarmState', $newAlarmState);
        // Door and window state
        $this->SetValue('DoorWindowState', $newDoorWindowState);
        // Motion detector state
        $this->SetValue('MotionDetectorState', $newMotionDetectorState);
        // Smoke detector state
        $this->SetValue('SmokeDetectorState', $newSmokeDetectorState);
        // Water sensor state
        $this->SetValue('WaterSensorState', $newWaterSensorState);
        // Set signal lamp
        $this->SetSignalLamp();
    }
}