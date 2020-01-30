<?php

// Declare
declare(strict_types=1);

trait AZST_updateStates
{
    /**
     * Updates the system state.
     */
    public function UpdateStates(): void
    {
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (empty($alarmZones)) {
            return;
        }
        // Definitions
        $newAbsenceModeState = false;
        $newPresenceModeState = false;
        $newNightModeState = false;
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
            // Absence mode
            $actualAbsenceModeState = GetValue(IPS_GetObjectIDByIdent('AbsenceMode', $id));
            if ($actualAbsenceModeState) {
                $newAbsenceModeState = true;
            }
            // Presence mode
            $actualPresenceModeState = GetValue(IPS_GetObjectIDByIdent('PresenceMode', $id));
            if ($actualPresenceModeState) {
                $newPresenceModeState = true;
            }
            // Night mode
            $actualNightModeState = GetValue(IPS_GetObjectIDByIdent('NightMode', $id));
            if ($actualNightModeState) {
                $newNightModeState = true;
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
        // Absence mode
        $this->SetValue('AbsenceMode', $newAbsenceModeState);
        // Presence  mode
        $this->SetValue('PresenceMode', $newPresenceModeState);
        // Night mode
        $this->SetValue('NightMode', $newNightModeState);
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