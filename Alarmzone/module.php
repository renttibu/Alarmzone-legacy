<?php

/*
 * @module      Alarmzone
 *
 * @prefix      AZON
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     4.00-15
 * @date        2020-05-15, 18:00, 1589562000
 * @revision    2020-05-15, 18:00
 *
 * @see         https://github.com/ubittner/Alarmzone/
 *
 * @guids       Library
 *              {8464371D-1C4E-B070-9884-82DB73545FFA}
 *
 *              Alarmzone
 *             	{6695A2B2-F5F7-321D-B8FE-CB5846837748}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Alarmzone extends IPSModule
{
    // Helper
    use AZON_controlOptions;
    use AZON_remoteControls;
    use AZON_toneAcknowledgement;
    use AZON_signalLamp;
    use AZON_notificationCenter;
    use AZON_doorWindowSensors;
    use AZON_motionDetectors;
    use AZON_smokeDetectors;
    use AZON_waterSensors;
    use AZON_alarmSiren;
    use AZON_alarmLight;
    use AZON_alarmCall;

    // Constants
    private const ALARMZONECONTROL_MODULE_GUID = '{E70D37CB-7732-9CC5-B29E-EC567D841B0C}';
    private const ALARMZONE_MODULE_GUID = '{6695A2B2-F5F7-321D-B8FE-CB5846837748}';
    private const ALARMPROTOCOL_MODULE_GUID = '{BC752980-2D17-67B6-9B91-0B4113EECD83}';
    private const TONEACKNOWLEDGEMENT_MODULE_GUID = '{DAC7CF88-0A1E-23C2-9DB2-0C249364A831}';
    private const SIGNALLAMP_MODULE_GUID = '{CF6B75A5-C573-7030-0D75-2F50A8A42B73}';
    private const NOTIFICATIONCENTER_MODULE_GUID = '{D184C522-507F-BED6-6731-728CE156D659}';
    private const ALARMSIREN_MODULE_GUID = '{118660A6-0784-4AD9-81D3-218BD03B1FF5}';
    private const ALARMLIGHT_MODULE_GUID = '{9C804D2B-54AF-690E-EC36-31BF41690EBA}';
    private const ALARMCALL_MODULE_GUID = '{8BB803E5-876D-B342-5CAE-A6A9A0928B61}';
    private const HOMEMATIC_MODULE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Create profiles
        $this->CreateProfiles();

        // Register variables
        $this->RegisterVariables();

        // Register timers
        $this->RegisterTimers();

        // Register attributes
        $this->RegisterAttributes();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Set options
        $this->SetOptions();

        // Deactivate timers
        $this->DeactivateTimers();

        // Register Messages
        $this->RegisterMessages();

        // Reset blacklist
        $this->ResetBlackList();

        // Update states
        $this->UpdateStates();

        // Validate configuration
        $this->ValidateConfiguration();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Send debug
        // $Data[0] = actual value
        // $Data[1] = value changed
        // $Data[2] = last value
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                // Remote controls
                $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'), true);
                if (!empty($remoteControls)) {
                    if (array_search($SenderID, array_column($remoteControls, 'ID')) !== false) {
                        $scriptText = 'AZON_TriggerRemoteControlAction(' . $this->InstanceID . ', ' . $SenderID . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }

                // Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        // Only if status has changed
                        if ($Data[1]) {
                            $scriptText = 'AZON_CheckDoorWindowSensorAlerting(' . $this->InstanceID . ', ' . $SenderID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }

                // Motion detectors
                $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'), true);
                if (!empty($motionDetectors)) {
                    if (array_search($SenderID, array_column($motionDetectors, 'ID')) !== false) {
                        // Only if status has changed
                        if ($Data[1]) {
                            $scriptText = 'AZON_CheckMotionDetectorAlerting(' . $this->InstanceID . ', ' . $SenderID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }

                // Smoke detectors
                $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'), true);
                if (!empty($smokeDetectors)) {
                    if (array_search($SenderID, array_column($smokeDetectors, 'ID')) !== false) {
                        // Only if status has changed
                        if ($Data[1]) {
                            $scriptText = 'AZON_ExecuteSmokeDetectorAlerting(' . $this->InstanceID . ', ' . $SenderID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }

                // Water sensors
                $waterSensors = json_decode($this->ReadPropertyString('WaterSensors'), true);
                if (!empty($waterSensors)) {
                    if (array_search($SenderID, array_column($waterSensors, 'ID')) !== false) {
                        // Only if status has changed
                        if ($Data[1]) {
                            $scriptText = 'AZON_ExecuteWaterSensorAlerting(' . $this->InstanceID . ', ' . $SenderID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }

                // Alarm siren
                $parentID = IPS_GetParent($SenderID);
                $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
                if ($parentID == $alarmSiren) {
                    if ($Data[1]) {
                        $this->SetValue('AlarmSiren', $Data[0]);
                    }
                    // Update alarm zone control state
                    $this->UpdateAlarmZoneControlStates();
                }

                // Alarm light
                $parentID = IPS_GetParent($SenderID);
                $alarmLight = $this->ReadPropertyInteger('AlarmLight');
                if ($parentID == $alarmLight) {
                    if ($Data[1]) {
                        $this->SetValue('AlarmLight', $Data[0]);
                    }
                    // Update alarm zone control state
                    $this->UpdateAlarmZoneControlStates();
                }
                break;

        }
    }

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function GetConfigurationForm()
    {
        $formdata = json_decode(file_get_contents(__DIR__ . '/form.json'));
        // Door and window sensors
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $rowColor = '#C0FFC0'; // light green
                $blackListedSensors = json_decode($this->GetBuffer('BlackList'), true);
                $blacklisted = false;
                if (!empty($blackListedSensors)) {
                    foreach ($blackListedSensors as $blackListedSensor) {
                        if ($blackListedSensor == $doorWindowSensor['ID']) {
                            $rowColor = '#FFC0C0'; // light red
                            $blacklisted = true;
                        }
                    }
                }
                if (!$blacklisted) {
                    $alertingValue = $doorWindowSensor['AlertingValue'];
                    if (GetValue($doorWindowSensor['ID']) == $alertingValue) {
                        $rowColor = '#C0C0FF'; // violett
                    }
                }
                $formdata->elements[15]->items[1]->values[] = [
                    'Name'                                          => $doorWindowSensor['Name'],
                    'ID'                                            => $doorWindowSensor['ID'],
                    'AlertingValue'                                 => $doorWindowSensor['AlertingValue'],
                    'FullProtectionModeActive'                      => $doorWindowSensor['FullProtectionModeActive'],
                    'HullProtectionModeActive'                      => $doorWindowSensor['HullProtectionModeActive'],
                    'PartialProtectionModeActive'                   => $doorWindowSensor['PartialProtectionModeActive'],
                    'UseAlertNotification'                          => $doorWindowSensor['UseAlertNotification'],
                    'UseAlarmSiren'                                 => $doorWindowSensor['UseAlarmSiren'],
                    'UseAlarmLight'                                 => $doorWindowSensor['UseAlarmLight'],
                    'UseAlarmCall'                                  => $doorWindowSensor['UseAlarmCall'],
                    'rowColor'                                      => $rowColor];
            }
        }
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            $senderName = IPS_GetName($senderID);
            $parentName = $senderName;
            $parentID = IPS_GetParent($senderID);
            if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                $parentName = IPS_GetName($parentID);
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formdata->elements[23]->items[0]->values[] = [
                'ParentName'                                            => $parentName,
                'SenderID'                                              => $senderID,
                'SenderName'                                            => $senderName,
                'MessageID'                                             => $messageID,
                'MessageDescription'                                    => $messageDescription];
        }
        return json_encode($formdata);
    }

    //#################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'FullProtectionMode':
                $id = $this->GetIDForIdent('FullProtectionMode');
                $this->ToggleFullProtectMode($Value, (string) $id, true, true);
                break;

            case 'HullProtectionMode':
                $id = $this->GetIDForIdent('HullProtectionMode');
                $this->ToggleHullProtectMode($Value, (string) $id, true, true);
                break;

            case 'PartialProtectionMode':
                $id = $this->GetIDForIdent('PartialProtectionMode');
                $this->TogglePartialProtectMode($Value, (string) $id, true, true);
                break;

            case 'AlarmSiren':
                $this->ToggleAlarmSiren($Value);
                break;

            case 'AlarmLight':
                $this->ToggleAlarmLight($Value);
                break;
        }
    }

    //#################### Private

    private function RegisterProperties(): void
    {
        // Descriptions
        $this->RegisterPropertyString('SystemName', 'Alarmzone');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyString('AlarmZoneName', '');
        $this->RegisterPropertyString('FullProtectionName', 'Vollschutz');
        $this->RegisterPropertyString('HullProtectionName', 'Hüllschutz');
        $this->RegisterPropertyString('PartialProtectionName', 'Teilschutz');

        // Alarm zone control
        $this->RegisterPropertyInteger('AlarmZoneControl', 0);

        // Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);

        // Information options
        $this->RegisterPropertyBoolean('EnableLocation', true);
        $this->RegisterPropertyBoolean('EnableAlarmZoneName', true);

        // Control options
        $this->RegisterPropertyBoolean('EnableFullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableHullProtectionMode', false);
        $this->RegisterPropertyBoolean('EnablePartialProtectionMode', false);
        $this->RegisterPropertyBoolean('EnableAlarmSiren', false);
        $this->RegisterPropertyBoolean('EnableAlarmLight', false);

        // State visibility
        $this->RegisterPropertyBoolean('EnableAlarmZoneState', true);
        $this->RegisterPropertyBoolean('EnableAlarmState', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableMotionDetectorState', false);
        $this->RegisterPropertyBoolean('EnableSmokeDetectorState', false);
        $this->RegisterPropertyBoolean('EnableWaterSensorState', false);

        // Activation check
        $this->RegisterPropertyBoolean('CheckFullProtectionModeActivation', false);
        $this->RegisterPropertyBoolean('CheckHullProtectionModeActivation', false);
        $this->RegisterPropertyBoolean('CheckPartialProtectionModeActivation', false);

        // Activation delay
        $this->RegisterPropertyInteger('FullProtectionModeActivationDelay', 0);
        $this->RegisterPropertyInteger('HullProtectionModeActivationDelay', 0);
        $this->RegisterPropertyInteger('PartialProtectionModeActivationDelay', 0);

        // Remote controls
        $this->RegisterPropertyString('RemoteControls', '[]');

        // Tone acknowledgement
        $this->RegisterPropertyInteger('ToneAcknowledgement', 0);
        $this->RegisterPropertyInteger('ToneAcknowledgementScript', 0);
        $this->RegisterPropertyBoolean('UseAlarmZoneControlToneAcknowledgement', false);

        // Signal lamps
        $this->RegisterPropertyInteger('AlarmZoneStateSignalLamp', 0);
        $this->RegisterPropertyInteger('AlarmZoneStateSignalLampScript', 0);
        $this->RegisterPropertyInteger('DoorWindowStateSignalLamp', 0);
        $this->RegisterPropertyInteger('DoorWindowStateSignalLampScript', 0);
        $this->RegisterPropertyInteger('AlarmStateSignalLamp', 0);
        $this->RegisterPropertyInteger('AlarmStateSignalLampScript', 0);

        // Notification center
        $this->RegisterPropertyInteger('NotificationCenter', 0);
        $this->RegisterPropertyInteger('NotificationScript', 0);
        $this->RegisterPropertyBoolean('UseAlarmZoneControlNotificationCenter', false);

        // Alarm sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyString('MotionDetectors', '[]');
        $this->RegisterPropertyString('SmokeDetectors', '[]');
        $this->RegisterPropertyString('WaterSensors', '[]');

        // Alerting delay
        $this->RegisterPropertyInteger('AlertingDelay', 0);

        // Alarm siren
        $this->RegisterPropertyInteger('AlarmSiren', 0);
        $this->RegisterPropertyInteger('AlarmSirenScript', 0);
        $this->RegisterPropertyBoolean('UseAlarmZoneControlAlarmSiren', false);

        // Alarm light
        $this->RegisterPropertyInteger('AlarmLight', 0);
        $this->RegisterPropertyInteger('AlarmLightScript', 0);
        $this->RegisterPropertyBoolean('AutomaticTurnOffAlarmLight', false);
        $this->RegisterPropertyBoolean('UseAlarmZoneControlAlarmLight', false);

        // Alarm call
        $this->RegisterPropertyInteger('AlarmCall', 0);
        $this->RegisterPropertyInteger('AlarmCallScript', 0);
        $this->RegisterPropertyBoolean('UseAlarmZoneControlAlarmCall', false);
    }

    private function CreateProfiles(): void
    {
        // Alarm zone state
        $profile = 'AZON.' . $this->InstanceID . '.AlarmZoneState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'Warning', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);

        // Alarm state
        $profile = 'AZON.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Alert', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Voralarm', 'Clock', 0xFFFF00);

        // Door and window state
        $profile = 'AZON.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);

        // Motion detector state
        $profile = 'AZON.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);

        // Smoke detector state
        $profile = 'AZON.' . $this->InstanceID . '.SmokeDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Flame');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Rauch erkannt', '', 0xFF0000);

        // Water sensor state
        $profile = 'AZON.' . $this->InstanceID . '.WaterSensorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Tap');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Wasser erkannt', '', 0xFF0000);

        // Homematic & Homematic IP devices

        // Door and window sensors
        $profile = 'AZON.DoorWindowSensor.Bool';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $profile = 'AZON.DoorWindowSensor.Bool.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geöffnet', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geschlossen', '', 0x00FF00);
        $profile = 'AZON.DoorWindowSensor.Integer';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $profile = 'AZON.DoorWindowSensor.Integer.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geöffnet', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geschlossen', '', 0x00FF00);

        // Motion detectors
        $profile = 'AZON.MotionDetector.Bool';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Untätig', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', 'Motion', 0xFF0000);

        // Smoke detectors
        $profile = 'AZON.SmokeDetector.Bool';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Rauch erkannt', 'Flame', 0xFF0000);
        $profile = 'AZON.SmokeDetector.Integer';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Rauch erkannt', 'Flame', 0xFF0000);

        // Water sensors
        $profile = 'AZON.WaterSensor.Bool';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Wasser erkannt', 'Tap', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['AlarmZoneState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState', 'SmokeDetectorState', 'WaterSensorState'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'AZON.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterTimers(): void
    {
        // Start activation
        $this->RegisterTimer('StartActivation', 0, 'AZON_StartActivation(' . $this->InstanceID . ');');

        // Alarm state
        $this->RegisterTimer('SetAlarmState', 0, 'AZON_SetAlarmState(' . $this->InstanceID . ');');
    }

    private function DeactivateTimers(): void
    {
        // Start activation
        $this->SetTimerInterval('StartActivation', 0);

        // Alarm state
        $this->SetTimerInterval('SetAlarmState', 0);
    }

    private function RegisterAttributes(): void
    {
        // Pre alarm
        $this->RegisterAttributeBoolean('PreAlarm', false);
    }

    private function RegisterVariables(): void
    {
        // Location
        $this->RegisterVariableString('Location', 'Standortbezeichnung', '', 1);
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        $id = $this->GetIDForIdent('Location');
        IPS_SetIcon($id, 'IPS');

        // Alarm zone name
        $this->RegisterVariableString('AlarmZoneName', 'Alarmzonenbezeichnung', '', 2);
        $this->SetValue('AlarmZoneName', $this->ReadPropertyString('AlarmZoneName'));
        $id = $this->GetIDForIdent('AlarmZoneName');
        IPS_SetIcon($id, 'Information');

        // Full protection mode
        $name = $this->ReadPropertyString('FullProtectionName');
        $this->RegisterVariableBoolean('FullProtectionMode', $name, '~Switch', 3);
        $this->EnableAction('FullProtectionMode');
        $id = $this->GetIDForIdent('FullProtectionMode');
        IPS_SetIcon($id, 'Basement');

        // Hull protection mode
        $name = $this->ReadPropertyString('HullProtectionName');
        $this->RegisterVariableBoolean('HullProtectionMode', $name, '~Switch', 4);
        $this->EnableAction('HullProtectionMode');
        $id = $this->GetIDForIdent('HullProtectionMode');
        IPS_SetIcon($id, 'GroundFloor');

        // Partial protection mode
        $name = $this->ReadPropertyString('PartialProtectionName');
        $this->RegisterVariableBoolean('PartialProtectionMode', $name, '~Switch', 5);
        $this->EnableAction('PartialProtectionMode');
        $id = $this->GetIDForIdent('PartialProtectionMode');
        IPS_SetIcon($id, 'Moon');

        // Alarm siren
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', '~Switch', 6);
        $this->EnableAction('AlarmSiren');
        $id = $this->GetIDForIdent('AlarmSiren');
        IPS_SetIcon($id, 'Alert');

        // Alarm light
        $this->RegisterVariableBoolean('AlarmLight', 'Alarm- / Außenbeleuchtung', '~Switch', 7);
        $this->EnableAction('AlarmLight');
        $id = $this->GetIDForIdent('AlarmLight');
        IPS_SetIcon($id, 'Bulb');

        // Alarm zone state
        $profile = 'AZON.' . $this->InstanceID . '.AlarmZoneState';
        $this->RegisterVariableInteger('AlarmZoneState', 'Alarmzonenstatus', $profile, 8);

        // Alarm state
        $profile = 'AZON.' . $this->InstanceID . '.AlarmState';
        $this->RegisterVariableInteger('AlarmState', 'Alarm', $profile, 9);

        // Door and window state
        $profile = 'AZON.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen und Fenster', $profile, 10);

        // Motion detector state
        $profile = 'AZON.' . $this->InstanceID . '.MotionDetectorState';
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 11);

        // Smoke detector state
        $profile = 'AZON.' . $this->InstanceID . '.SmokeDetectorState';
        $this->RegisterVariableBoolean('SmokeDetectorState', 'Rauchmelder', $profile, 12);

        // Water sensor state
        $profile = 'AZON.' . $this->InstanceID . '.WaterSensorState';
        $this->RegisterVariableBoolean('WaterSensorState', 'Wassersensoren', $profile, 13);
    }

    private function SetOptions(): void
    {
        // Location
        $name = $this->ReadPropertyString('Location');
        $this->SetValue('Location', $name);
        $use = $this->ReadPropertyBoolean('EnableLocation');
        IPS_SetHidden($this->GetIDForIdent('Location'), !$use);

        // Alarm zone name
        $name = $this->ReadPropertyString('AlarmZoneName');
        $this->SetValue('AlarmZoneName', $name);
        $use = $this->ReadPropertyBoolean('EnableAlarmZoneName');
        IPS_SetHidden($this->GetIDForIdent('AlarmZoneName'), !$use);

        // Full protection mode
        $id = $this->GetIDForIdent('FullProtectionMode');
        $name = $this->ReadPropertyString('FullProtectionName');
        IPS_SetName($id, $name);
        $use = $this->ReadPropertyBoolean('EnableFullProtectionMode');
        IPS_SetHidden($id, !$use);

        // Hull protection mode
        $id = $this->GetIDForIdent('HullProtectionMode');
        $name = $this->ReadPropertyString('HullProtectionName');
        IPS_SetName($id, $name);
        $use = $this->ReadPropertyBoolean('EnableHullProtectionMode');
        IPS_SetHidden($id, !$use);

        // Partial protection mode
        $id = $this->GetIDForIdent('PartialProtectionMode');
        $name = $this->ReadPropertyString('PartialProtectionName');
        IPS_SetName($id, $name);
        $use = $this->ReadPropertyBoolean('EnablePartialProtectionMode');
        IPS_SetHidden($id, !$use);

        // Alarm siren
        $id = $this->GetIDForIdent('AlarmSiren');
        $use = $this->ReadPropertyBoolean('EnableAlarmSiren');
        IPS_SetHidden($id, !$use);

        // Alarm light
        $id = $this->GetIDForIdent('AlarmLight');
        $use = $this->ReadPropertyBoolean('EnableAlarmLight');
        IPS_SetHidden($id, !$use);

        // Alarm zone state
        $id = $this->GetIDForIdent('AlarmZoneState');
        $use = $this->ReadPropertyBoolean('EnableAlarmZoneState');
        IPS_SetHidden($id, !$use);

        // Alarm state
        $id = $this->GetIDForIdent('AlarmState');
        $use = $this->ReadPropertyBoolean('EnableAlarmState');
        IPS_SetHidden($id, !$use);

        // Door and window state
        $id = $this->GetIDForIdent('DoorWindowState');
        $use = $this->ReadPropertyBoolean('EnableDoorWindowState');
        IPS_SetHidden($id, !$use);

        // Motion detector state
        $id = $this->GetIDForIdent('MotionDetectorState');
        $use = $this->ReadPropertyBoolean('EnableMotionDetectorState');
        IPS_SetHidden($id, !$use);

        // Smoke detector state
        $id = $this->GetIDForIdent('SmokeDetectorState');
        $use = $this->ReadPropertyBoolean('EnableSmokeDetectorState');
        IPS_SetHidden($id, !$use);

        // Water sensor state
        $id = $this->GetIDForIdent('WaterSensorState');
        $use = $this->ReadPropertyBoolean('EnableWaterSensorState');
        IPS_SetHidden($id, !$use);
    }

    private function RegisterMessages(): void
    {
        // Unregister all variable update messages first
        $registeredMessages = $this->GetMessageList();
        if (!empty($registeredMessages)) {
            foreach ($registeredMessages as $id => $registeredMessage) {
                foreach ($registeredMessage as $messageType) {
                    if ($messageType == VM_UPDATE) {
                        $this->UnregisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
        // Register devices
        // Remote controls
        $variables = json_decode($this->ReadPropertyString('RemoteControls'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
        // Door and window sensors
        $variables = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                    $this->RegisterMessage($variable->ID, VM_UPDATE);
                }
            }
        }
        // Motion Detectors
        $variables = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                    $this->RegisterMessage($variable->ID, VM_UPDATE);
                }
            }
        }
        // Smoke detectors
        $variables = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                    $this->RegisterMessage($variable->ID, VM_UPDATE);
                }
            }
        }
        // Water Sensors
        $variables = json_decode($this->ReadPropertyString('WaterSensors'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                    $this->RegisterMessage($variable->ID, VM_UPDATE);
                }
            }
        }
        // Alarm siren
        $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
        if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
            $alarmSirenSwitch = @IPS_GetObjectIDByIdent('AlarmSiren', $alarmSiren);
            if ($alarmSirenSwitch != 0 && @IPS_ObjectExists($alarmSirenSwitch)) {
                $this->RegisterMessage($alarmSirenSwitch, VM_UPDATE);
            }
        }
        // Alarm light
        $alarmLight = $this->ReadPropertyInteger('AlarmLight');
        if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
            $alarmLightSwitch = @IPS_GetObjectIDByIdent('AlarmLight', $alarmLight);
            if ($alarmLightSwitch != 0 && @IPS_ObjectExists($alarmLightSwitch)) {
                $this->RegisterMessage($alarmLightSwitch, VM_UPDATE);
            }
        }
    }

    private function UpdateStates(): void
    {
        $this->UpdateDoorWindowState(false, false, false);
        $this->UpdateMotionDetectorState(false, false);
        $this->UpdateSmokeDetectorState(false, false);
        $this->UpdateWaterSensorState(false, false);
        $this->SetSignalLamps();
        $this->UpdateAlarmZoneControlStates();
    }

    private function UpdateAlarmZoneControlStates(): void
    {
        $alarmZoneControl = $this->ReadPropertyInteger('AlarmZoneControl');
        if ($alarmZoneControl != 0 && @IPS_ObjectExists($alarmZoneControl)) {
            @AZST_UpdateStates($alarmZoneControl);
        }
    }

    /**
     * Assigns the profile to the sensors.
     */
    public function AssignProfiles(): void
    {
        // Door and window sensors
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $id = $doorWindowSensor->ID;
                if ($id != 0 && IPS_ObjectExists($id)) {
                    // Check object
                    $object = IPS_GetObject($id)['ObjectType'];
                    // 0: Category, 1: Instance, 2: Variable, 3: Script, 4: Event, 5: Media, 6: Link)
                    if ($object == 2) {
                        // Get variable type
                        $variable = IPS_GetVariable($id)['VariableType'];
                        $profile = $doorWindowSensor->AlertingValue;
                        switch ($variable) {
                            // 0: Boolean, 1: Integer, 2: Float, 3: String
                            case 0:
                                switch ($profile) {
                                    // 0: Reversed, 1: Standard
                                    case 0:
                                        $profileName = 'AZON.DoorWindowSensor.Bool.Reversed';
                                        break;

                                    case 1:
                                        $profileName = 'AZON.DoorWindowSensor.Bool';
                                        break;

                                }
                                break;

                            case 1:
                                switch ($profile) {
                                    // 0: Reversed, 1: Standard
                                    case 0:
                                        $profileName = 'AZON.DoorWindowSensor.Integer.Reversed';
                                        break;

                                    case 1:
                                        $profileName = 'AZON.DoorWindowSensor.Integer';
                                        break;

                                }
                                break;

                            default:
                                $profileName = '';
                        }
                        if (!empty($profileName)) {
                            IPS_SetVariableCustomProfile($id, $profileName);
                            IPS_SetVariableCustomAction($id, 1);
                        }
                    }
                }
            }
        }
        // Motion detectors
        $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($motionDetectors)) {
            foreach ($motionDetectors as $motionDetector) {
                $id = $motionDetector->ID;
                if ($id != 0 && IPS_ObjectExists($id)) {
                    $object = IPS_GetObject($id)['ObjectType'];
                    // Check if object is a variable
                    if ($object == 2) {
                        // Get variable type
                        $variable = IPS_GetVariable($id)['VariableType'];
                        $profile = $motionDetector->AlertingValue;
                        switch ($variable) {
                            // 0: Boolean, 1: Integer, 2: Float, 3: String
                            case 0:
                                switch ($profile) {
                                    // 0: Reversed, 1: Standard
                                    case 0:
                                        // not necessary yet
                                        break;

                                    case 1:
                                        $profileName = 'AZON.MotionDetector.Bool';
                                        break;

                                }
                                break;

                            default:
                                $profileName = '';
                        }
                        if (!empty($profileName)) {
                            IPS_SetVariableCustomProfile($id, $profileName);
                            IPS_SetVariableCustomAction($id, 1);
                        }
                    }
                }
            }
        }
        // Smoke detectors
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($smokeDetectors)) {
            foreach ($smokeDetectors as $smokeDetector) {
                $id = $smokeDetector->ID;
                if ($id != 0 && IPS_ObjectExists($id)) {
                    $object = IPS_GetObject($id)['ObjectType'];
                    // Check if object is a variable
                    if ($object == 2) {
                        // Get variable type
                        $variable = IPS_GetVariable($id)['VariableType'];
                        $profile = $smokeDetector->AlertingValue;
                        switch ($variable) {
                            // 0: Boolean, 1: Integer, 2: Float, 3: String
                            case 0:
                                switch ($profile) {
                                    // 0: Reversed, 1: Standard
                                    case 0:
                                        // not necessary yet
                                        break;

                                    case 1:
                                        $profileName = 'AZON.SmokeDetector.Bool';
                                        break;

                                }
                                break;

                            case 1:
                                switch ($profile) {
                                    // 0: Reversed, 1: Standard
                                    case 0:
                                        // not necessary yet
                                        break;

                                    case 1:
                                        $profileName = 'AZON.SmokeDetector.Integer';
                                        break;

                                }
                                break;

                            default:
                                $profileName = '';
                        }
                        if (!empty($profileName)) {
                            IPS_SetVariableCustomProfile($id, $profileName);
                            IPS_SetVariableCustomAction($id, 1);
                        }
                    }
                }
            }
        }
        // Water sensors
        $waterSensors = json_decode($this->ReadPropertyString('WaterSensors'));
        if (!empty($waterSensors)) {
            foreach ($waterSensors as $waterSensor) {
                $id = $waterSensor->ID;
                if ($id != 0 && IPS_ObjectExists($id)) {
                    $object = IPS_GetObject($id)['ObjectType'];
                    // Check if object is a variable
                    if ($object == 2) {
                        // Get variable type
                        $variable = IPS_GetVariable($id)['VariableType'];
                        $profile = $waterSensor->AlertingValue;
                        switch ($variable) {
                            // 0: Boolean, 1: Integer, 2: Float, 3: String
                            case 0:
                                switch ($profile) {
                                    // 0: Reversed, 1: Standard
                                    case 0:
                                        // not necessary yet
                                        break;

                                    case 1:
                                        $profileName = 'AZON.WaterSensor.Bool';
                                        break;

                                }
                                break;

                            default:
                                $profileName = '';
                        }
                        if (!empty($profileName)) {
                            IPS_SetVariableCustomProfile($id, $profileName);
                            IPS_SetVariableCustomAction($id, 1);
                        }
                    }
                }
            }
        }
        echo 'Die Variablenprofile wurden erfolgreich zugewiesen!';
    }

    /**
     * Creates links of sensors.
     */
    public function CreateLinks(): void
    {
        // Door and window sensors
        $categoryID = @IPS_GetObjectIDByIdent('DoorWindowSensorsCategory', $this->InstanceID);
        // Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($variables)) {
            if ($categoryID === false) {
                $categoryID = IPS_CreateCategory();
            }
            IPS_SetIdent($categoryID, 'DoorWindowSensorsCategory');
            IPS_SetName($categoryID, 'Tür- und Fenstersensoren');
            IPS_SetParent($categoryID, $this->InstanceID);
            IPS_SetIcon($categoryID, 'Window');
            IPS_SetPosition($categoryID, 101);
            IPS_SetHidden($categoryID, true);
            // Get variables
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
            // Sort array alphabetically by device name
            sort($targetIDs);
            // Get all existing links
            $existingTargetIDs = [];
            $childrenIDs = IPS_GetChildrenIDs($categoryID);
            $i = 0;
            foreach ($childrenIDs as $childID) {
                // Check if children is a link
                $objectType = IPS_GetObject($childID)['ObjectType'];
                if ($objectType == 6) {
                    // Get target id
                    $existingTargetID = IPS_GetLink($childID)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $childID, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
            // Delete dead links
            $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($deadLinks)) {
                foreach ($deadLinks as $targetID) {
                    $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$position]['linkID'];
                    if (IPS_LinkExists($linkID)) {
                        IPS_DeleteLink($linkID);
                    }
                }
            }
            // Create new links
            $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
            if (!empty($newLinks)) {
                foreach ($newLinks as $targetID) {
                    $linkID = IPS_CreateLink();
                    IPS_SetParent($linkID, $categoryID);
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetLinkTargetID($linkID, $targetID);
                    IPS_SetIcon($linkID, 'Window');
                }
            }
            // Edit existing links
            $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($existingLinks)) {
                foreach ($existingLinks as $targetID) {
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    $targetID = $targetIDs[$position]['targetID'];
                    $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$index]['linkID'];
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetIcon($linkID, 'Window');
                }
            }
        }
        // Motion Detectors
        $categoryID = @IPS_GetObjectIDByIdent('MotionDetectorsCategory', $this->InstanceID);
        // Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($variables)) {
            if ($categoryID === false) {
                $categoryID = IPS_CreateCategory();
            }
            IPS_SetIdent($categoryID, 'MotionDetectorsCategory');
            IPS_SetName($categoryID, 'Bewegungsmelder');
            IPS_SetParent($categoryID, $this->InstanceID);
            IPS_SetIcon($categoryID, 'Motion');
            IPS_SetPosition($categoryID, 102);
            IPS_SetHidden($categoryID, true);
            // Get variables
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
            // Sort array alphabetically by device name
            sort($targetIDs);
            // Get all existing links
            $existingTargetIDs = [];
            $childrenIDs = IPS_GetChildrenIDs($categoryID);
            $i = 0;
            foreach ($childrenIDs as $childID) {
                // Check if children is a link
                $objectType = IPS_GetObject($childID)['ObjectType'];
                if ($objectType == 6) {
                    // Get target id
                    $existingTargetID = IPS_GetLink($childID)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $childID, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
            // Delete dead links
            $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($deadLinks)) {
                foreach ($deadLinks as $targetID) {
                    $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$position]['linkID'];
                    if (IPS_LinkExists($linkID)) {
                        IPS_DeleteLink($linkID);
                    }
                }
            }
            // Create new links
            $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
            if (!empty($newLinks)) {
                foreach ($newLinks as $targetID) {
                    $linkID = IPS_CreateLink();
                    IPS_SetParent($linkID, $categoryID);
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetLinkTargetID($linkID, $targetID);
                    IPS_SetIcon($linkID, 'Motion');
                }
            }
            // Edit existing links
            $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($existingLinks)) {
                foreach ($existingLinks as $targetID) {
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    $targetID = $targetIDs[$position]['targetID'];
                    $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$index]['linkID'];
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetIcon($linkID, 'Motion');
                }
            }
        }
        // Smoke Detectors
        $categoryID = @IPS_GetObjectIDByIdent('SmokeDetectorsCategory', $this->InstanceID);
        // Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($variables)) {
            if ($categoryID === false) {
                $categoryID = IPS_CreateCategory();
            }
            IPS_SetIdent($categoryID, 'SmokeDetectorsCategory');
            IPS_SetName($categoryID, 'Rauchmelder');
            IPS_SetParent($categoryID, $this->InstanceID);
            IPS_SetIcon($categoryID, 'Flame');
            IPS_SetPosition($categoryID, 103);
            IPS_SetHidden($categoryID, true);
            // Get variables
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
            // Sort array alphabetically by device name
            sort($targetIDs);
            // Get all existing links
            $existingTargetIDs = [];
            $childrenIDs = IPS_GetChildrenIDs($categoryID);
            $i = 0;
            foreach ($childrenIDs as $childID) {
                // Check if children is a link
                $objectType = IPS_GetObject($childID)['ObjectType'];
                if ($objectType == 6) {
                    // Get target id
                    $existingTargetID = IPS_GetLink($childID)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $childID, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
            // Delete dead links
            $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($deadLinks)) {
                foreach ($deadLinks as $targetID) {
                    $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$position]['linkID'];
                    if (IPS_LinkExists($linkID)) {
                        IPS_DeleteLink($linkID);
                    }
                }
            }
            // Create new links
            $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
            if (!empty($newLinks)) {
                foreach ($newLinks as $targetID) {
                    $linkID = IPS_CreateLink();
                    IPS_SetParent($linkID, $categoryID);
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetLinkTargetID($linkID, $targetID);
                    IPS_SetIcon($linkID, 'Flame');
                }
            }
            // Edit existing links
            $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($existingLinks)) {
                foreach ($existingLinks as $targetID) {
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    $targetID = $targetIDs[$position]['targetID'];
                    $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$index]['linkID'];
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetIcon($linkID, 'Flame');
                }
            }
        }
        // Water sensors
        $categoryID = @IPS_GetObjectIDByIdent('WaterSensorsCategory', $this->InstanceID);
        // Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('WaterSensors'));
        if (!empty($variables)) {
            if ($categoryID === false) {
                $categoryID = IPS_CreateCategory();
            }
            IPS_SetIdent($categoryID, 'WaterSensorCategory');
            IPS_SetName($categoryID, 'Wassersensoren');
            IPS_SetParent($categoryID, $this->InstanceID);
            IPS_SetIcon($categoryID, 'Tap');
            IPS_SetPosition($categoryID, 102);
            IPS_SetHidden($categoryID, true);
            // Get variables
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
            // Sort array alphabetically by device name
            sort($targetIDs);
            // Get all existing links
            $existingTargetIDs = [];
            $childrenIDs = IPS_GetChildrenIDs($categoryID);
            $i = 0;
            foreach ($childrenIDs as $childID) {
                // Check if children is a link
                $objectType = IPS_GetObject($childID)['ObjectType'];
                if ($objectType == 6) {
                    // Get target id
                    $existingTargetID = IPS_GetLink($childID)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $childID, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
            // Delete dead links
            $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($deadLinks)) {
                foreach ($deadLinks as $targetID) {
                    $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$position]['linkID'];
                    if (IPS_LinkExists($linkID)) {
                        IPS_DeleteLink($linkID);
                    }
                }
            }
            // Create new links
            $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
            if (!empty($newLinks)) {
                foreach ($newLinks as $targetID) {
                    $linkID = IPS_CreateLink();
                    IPS_SetParent($linkID, $categoryID);
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetLinkTargetID($linkID, $targetID);
                    IPS_SetIcon($linkID, 'Tap');
                }
            }
            // Edit existing links
            $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($existingLinks)) {
                foreach ($existingLinks as $targetID) {
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    $targetID = $targetIDs[$position]['targetID'];
                    $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$index]['linkID'];
                    IPS_SetPosition($linkID, $position);
                    $name = $targetIDs[$position]['name'];
                    IPS_SetName($linkID, $name);
                    IPS_SetIcon($linkID, 'Tap');
                }
            }
        }
        echo 'Die Verknüpfungen wurde erfolgreich angelegt!';
    }

    private function ValidateConfiguration(): void
    {
        $state = 102;
        // Alarm zone control
        $id = $this->ReadPropertyInteger('AlarmZoneControl');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmzonensteuerung ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::ALARMZONECONTROL_MODULE_GUID) {
                    $this->LogMessage('Alarmzonensteuerung GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm protocol
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmprotokoll ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::ALARMPROTOCOL_MODULE_GUID) {
                    $this->LogMessage('Alarmprotokoll GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Tone acknowledgement
        $id = $this->ReadPropertyInteger('ToneAcknowledgement');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Quittungston ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::TONEACKNOWLEDGEMENT_MODULE_GUID) {
                    $this->LogMessage('Quittungston GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm zone state signal lamp
        $id = $this->ReadPropertyInteger('AlarmZoneStateSignalLamp');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmzonenstatus Signalleuchte ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::SIGNALLAMP_MODULE_GUID) {
                    $this->LogMessage('Alarmzonenstatus Signalleuchte GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Door and window state signal lamp
        $id = $this->ReadPropertyInteger('DoorWindowStateSignalLamp');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Tür- / Fensterstatus Signalleuchte ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::SIGNALLAMP_MODULE_GUID) {
                    $this->LogMessage('Tür- / Fensterstatus Signalleuchte GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm state signal lamp
        $id = $this->ReadPropertyInteger('AlarmStateSignalLamp');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmstatus Signalleuchte ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::SIGNALLAMP_MODULE_GUID) {
                    $this->LogMessage('Alarmstatus Signalleuchte GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Notification center
        $id = $this->ReadPropertyInteger('NotificationCenter');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Benachrichtigungszentrale ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::NOTIFICATIONCENTER_MODULE_GUID) {
                    $this->LogMessage('Benachrichtigungszentrale GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm siren
        $id = $this->ReadPropertyInteger('AlarmSiren');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmsirene ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::ALARMSIREN_MODULE_GUID) {
                    $this->LogMessage('Alarmsirene GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm light
        $id = $this->ReadPropertyInteger('AlarmLight');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmbeleuchtung ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::ALARMLIGHT_MODULE_GUID) {
                    $this->LogMessage('Alarmbeleuchtung GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm call
        $id = $this->ReadPropertyInteger('AlarmCall');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Alarmanruf ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::ALARMCALL_MODULE_GUID) {
                    $this->LogMessage('Alarmanruf GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Set state
        $this->SetStatus($state);
    }
}
