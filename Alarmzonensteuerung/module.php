<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmzonensteuerung
 *
 * @prefix      AZST
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Alarmzone
 *
 * @guids       Library
 *              {8464371D-1C4E-B070-9884-82DB73545FFA}
 *
 *              Alarmzonensteuerung
 *             	{E70D37CB-7732-9CC5-B29E-EC567D841B0C}
 *
 */

declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Alarmzonensteuerung extends IPSModule
{
    // Helper
    use AZST_updateStates;
    use AZST_controlOptions;
    use AZST_remoteControls;
    use AZST_toneAcknowledgement;
    use AZST_signalLamp;
    use AZST_notification;
    use AZST_alarmSiren;
    use AZST_alarmLight;
    use AZST_alarmCall;
    use AZST_backupRestore;

    // Constants
    private const ALARMZONE_LIBRARY_GUID = '{8464371D-1C4E-B070-9884-82DB73545FFA}';
    private const ALARMZONE_MODULE_GUID = '{6695A2B2-F5F7-321D-B8FE-CB5846837748}';
    private const ALARMZONENSTEUERUNG_MODULE_GUID = '{E70D37CB-7732-9CC5-B29E-EC567D841B0C}';
    private const ALARMPROTOKOLL_MODULE_GUID = '{BC752980-2D17-67B6-9B91-0B4113EECD83}';
    private const QUITTUNGSTON_MODULE_GUID = '{DAC7CF88-0A1E-23C2-9DB2-0C249364A831}';
    private const SIGNALLEUCHTE_MODULE_GUID = '{CF6B75A5-C573-7030-0D75-2F50A8A42B73}';
    private const BENACHRICHTIGUNGSZENTRALE_MODULE_GUID = '{D184C522-507F-BED6-6731-728CE156D659}';
    private const ALARMSIRENE_MODULE_GUID = '{118660A6-0784-4AD9-81D3-218BD03B1FF5}';
    private const ALARMBELEUCHTUNG_MODULE_GUID = '{9C804D2B-54AF-690E-EC36-31BF41690EBA}';
    private const ALARMANRUF_MODULE_GUID = '{8BB803E5-876D-B342-5CAE-A6A9A0928B61}';
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
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
        $this->SetOptions();
        $this->RegisterMessages();
        $this->UpdateStates();
        $this->ValidateConfiguration();
        $this->CheckMaintenanceMode();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Send debug
        // $Data[0] = actual value
        // $Data[1] = value changed
        // $Data[2] = last value
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                if ($this->CheckMaintenanceMode()) {
                    return;
                }
                // Remote controls
                $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'), true);
                if (!empty($remoteControls)) {
                    if (array_search($SenderID, array_column($remoteControls, 'ID')) !== false) {
                        $scriptText = 'AZST_TriggerRemoteControlAction(' . $this->InstanceID . ', ' . $SenderID . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                // Alarm siren
                $parentID = IPS_GetParent($SenderID);
                $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
                if ($parentID == $alarmSiren) {
                    if ($Data[1]) {
                        $this->SetValue('AlarmSiren', $Data[0]);
                    }
                    $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
                    if (!empty($alarmZones)) {
                        foreach ($alarmZones as $alarmZone) {
                            $id = $alarmZone->ID;
                            if ($id != 0 && @IPS_ObjectExists($id)) {
                                $alarmSirenSwitch = IPS_GetObjectIDByIdent('AlarmSiren', $id);
                                if ($alarmSirenSwitch != 0 && @IPS_ObjectExists($alarmSirenSwitch)) {
                                    SetValue($alarmSirenSwitch, $Data[0]);
                                }
                            }
                        }
                    }
                    $this->UpdateStates();
                }
                // Alarm light
                $parentID = IPS_GetParent($SenderID);
                $alarmLight = $this->ReadPropertyInteger('AlarmLight');
                if ($parentID == $alarmLight) {
                    if ($Data[1]) {
                        $this->SetValue('AlarmLight', $Data[0]);
                    }
                    $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
                    if (!empty($alarmZones)) {
                        foreach ($alarmZones as $alarmZone) {
                            $id = $alarmZone->ID;
                            if ($id != 0 && @IPS_ObjectExists($id)) {
                                $alarmLightSwitch = IPS_GetObjectIDByIdent('AlarmLight', $id);
                                if ($alarmLightSwitch != 0 && @IPS_ObjectExists($alarmLightSwitch)) {
                                    SetValue($alarmLightSwitch, $Data[0]);
                                }
                            }
                        }
                    }
                    $this->UpdateStates();
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::ALARMZONE_LIBRARY_GUID);
        $module = IPS_GetModule(self::ALARMZONENSTEUERUNG_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][2]['caption'] = "Instanz ID:\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][3]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][4]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][5]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][6]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][7]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][8]['caption'] = "Präfix:\t\t\tAZST";
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            if (!IPS_ObjectExists($senderID)) {
                foreach ($messageID as $messageType) {
                    $this->UnregisterMessage($senderID, $messageType);
                }
                continue;
            } else {
                $senderName = IPS_GetName($senderID);
                $description = $senderName;
                $parentID = IPS_GetParent($senderID);
                if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                    $description = IPS_GetName($parentID);
                }
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                case [10803]:
                    $messageDescription = 'EM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData['actions'][1]['items'][0]['values'][] = [
                'Description'        => $description,
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription];
        }
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'FullProtectionMode':
                $id = $this->GetIDForIdent('FullProtectionMode');
                $this->ToggleFullProtectMode($Value, (string) $id);
                break;

            case 'HullProtectionMode':
                $id = $this->GetIDForIdent('HullProtectionMode');
                $this->ToggleHullProtectMode($Value, (string) $id);
                break;

            case 'PartialProtectionMode':
                $id = $this->GetIDForIdent('PartialProtectionMode');
                $this->TogglePartialProtectMode($Value, (string) $id);
                break;

            case 'AlarmSiren':
                $this->ToggleAlarmSiren($Value);
                break;

            case 'AlarmLight':
                $this->ToggleAlarmLight($Value);
                break;
        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        // Descriptions
        $this->RegisterPropertyString('SystemName', 'Alarmanlage');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyString('ObjectName', 'Haus');
        $this->RegisterPropertyString('FullProtectionName', 'Vollschutz');
        $this->RegisterPropertyString('HullProtectionName', 'Hüllschutz');
        $this->RegisterPropertyString('PartialProtectionName', 'Teilschutz');
        // Alarm zones
        $this->RegisterPropertyString('AlarmZones', '[]');
        // Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
        // Information options
        $this->RegisterPropertyBoolean('EnableLocation', true);
        $this->RegisterPropertyBoolean('EnableObjectName', true);
        // Control options
        $this->RegisterPropertyBoolean('EnableFullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableHullProtectionMode', false);
        $this->RegisterPropertyBoolean('EnablePartialProtectionMode', false);
        $this->RegisterPropertyBoolean('EnableAlarmSiren', false);
        $this->RegisterPropertyBoolean('EnableAlarmLight', false);
        // State visibility
        $this->RegisterPropertyBoolean('EnableSystemState', true);
        $this->RegisterPropertyBoolean('EnableAlarmState', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableMotionDetectorState', false);
        $this->RegisterPropertyBoolean('EnableSmokeDetectorState', false);
        $this->RegisterPropertyBoolean('EnableWaterSensorState', false);
        // Remote controls
        $this->RegisterPropertyString('RemoteControls', '[]');
        // Tone acknowledgement
        $this->RegisterPropertyInteger('ToneAcknowledgement', 0);
        $this->RegisterPropertyInteger('ToneAcknowledgementScript', 0);
        // Signal lamp
        $this->RegisterPropertyInteger('SystemStateSignalLamp', 0);
        $this->RegisterPropertyInteger('SystemStateSignalLampScript', 0);
        $this->RegisterPropertyInteger('DoorWindowStateSignalLamp', 0);
        $this->RegisterPropertyInteger('DoorWindowStateSignalLampScript', 0);
        $this->RegisterPropertyInteger('AlarmStateSignalLamp', 0);
        $this->RegisterPropertyInteger('AlarmStateSignalLampScript', 0);
        // Notification
        $this->RegisterPropertyInteger('NotificationCenter', 0);
        $this->RegisterPropertyString('AlarmZonesDisarmedSymbol', json_decode('"\ud83d\udfe2"'));
        $this->RegisterPropertyString('FullProtectionModeArmedSymbol', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyString('HullProtectionModeArmedSymbol', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyString('PartialProtectionModeArmedSymbol', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyString('AlarmZonesSystemFailure', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyInteger('NotificationScript', 0);
        // Alerting delay
        $this->RegisterPropertyInteger('AlertingDelay', 0);
        // Alarm siren
        $this->RegisterPropertyInteger('AlarmSiren', 0);
        $this->RegisterPropertyInteger('AlarmSirenScript', 0);
        // Alarm light
        $this->RegisterPropertyInteger('AlarmLight', 0);
        $this->RegisterPropertyInteger('AlarmLightScript', 0);
        $this->RegisterPropertyBoolean('AutomaticTurnOffAlarmLight', false);
        // Alarm call
        $this->RegisterPropertyInteger('AlarmCall', 0);
        $this->RegisterPropertyInteger('AlarmCallScript', 0);
    }

    private function CreateProfiles(): void
    {
        // System state
        $profile = 'AZST.' . $this->InstanceID . '.SystemState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'Warning', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
        // Alarm state
        $profile = 'AZST.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Alert', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Voralarm', 'Clock', 0xFFFF00);
        // Door and window state
        $profile = 'AZST.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        // Motion detector state
        $profile = 'AZST.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);
        // Smoke detector state
        $profile = 'AZST.' . $this->InstanceID . '.SmokeDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Flame');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Rauch erkannt', '', 0xFF0000);
        // Water sensor state
        $profile = 'AZST.' . $this->InstanceID . '.WaterSensorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Tap');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Wasser erkannt', '', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['SystemState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState', 'SmokeDetectorState', 'WaterSensorState'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'AZST.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Location
        $this->RegisterVariableString('Location', 'Standortbezeichnung', '', 10);
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        $id = $this->GetIDForIdent('Location');
        IPS_SetIcon($id, 'IPS');
        // Object name
        $this->RegisterVariableString('ObjectName', 'Objektbezeichnung', '', 20);
        $this->SetValue('Location', $this->ReadPropertyString('ObjectName'));
        $id = $this->GetIDForIdent('ObjectName');
        IPS_SetIcon($id, 'Information');
        // Full protection mode
        $name = $this->ReadPropertyString('FullProtectionName');
        $this->RegisterVariableBoolean('FullProtectionMode', $name, '~Switch', 30);
        $this->EnableAction('FullProtectionMode');
        $id = $this->GetIDForIdent('FullProtectionMode');
        IPS_SetIcon($id, 'Basement');
        // Hull protection mode
        $name = $this->ReadPropertyString('HullProtectionName');
        $this->RegisterVariableBoolean('HullProtectionMode', $name, '~Switch', 40);
        $this->EnableAction('HullProtectionMode');
        $id = $this->GetIDForIdent('HullProtectionMode');
        IPS_SetIcon($id, 'GroundFloor');
        // Partial protection mode
        $name = $this->ReadPropertyString('PartialProtectionName');
        $this->RegisterVariableBoolean('PartialProtectionMode', $name, '~Switch', 50);
        $this->EnableAction('PartialProtectionMode');
        $id = $this->GetIDForIdent('PartialProtectionMode');
        IPS_SetIcon($id, 'Moon');
        // Alarm siren
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', '~Switch', 60);
        $this->EnableAction('AlarmSiren');
        $id = $this->GetIDForIdent('AlarmSiren');
        IPS_SetIcon($id, 'Alert');
        // Alarm light
        $this->RegisterVariableBoolean('AlarmLight', 'Alarm- / Außenbeleuchtung', '~Switch', 70);
        $this->EnableAction('AlarmLight');
        $id = $this->GetIDForIdent('AlarmLight');
        IPS_SetIcon($id, 'Bulb');
        // System state
        $profile = 'AZST.' . $this->InstanceID . '.SystemState';
        $this->RegisterVariableInteger('SystemState', 'Systemstatus', $profile, 80);
        // Alarm state
        $profile = 'AZST.' . $this->InstanceID . '.AlarmState';
        $this->RegisterVariableInteger('AlarmState', 'Alarmstatus', $profile, 90);
        // Door and window state
        $profile = 'AZST.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen- und Fenster', $profile, 100);
        // Motion detector state
        $profile = 'AZST.' . $this->InstanceID . '.MotionDetectorState';
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 110);
        // Smoke detector state
        $profile = 'AZST.' . $this->InstanceID . '.SmokeDetectorState';
        $this->RegisterVariableBoolean('SmokeDetectorState', 'Rauchmelder', $profile, 120);
        // Water sensor state
        $profile = 'AZST.' . $this->InstanceID . '.WaterSensorState';
        $this->RegisterVariableBoolean('WaterSensorState', 'Wassersensoren', $profile, 130);
    }

    private function SetOptions(): void
    {
        // Location
        $name = $this->ReadPropertyString('Location');
        $this->SetValue('Location', $name);
        $use = $this->ReadPropertyBoolean('EnableLocation');
        IPS_SetHidden($this->GetIDForIdent('Location'), !$use);
        // Object name
        $name = $this->ReadPropertyString('ObjectName');
        $this->SetValue('ObjectName', $name);
        $use = $this->ReadPropertyBoolean('EnableObjectName');
        IPS_SetHidden($this->GetIDForIdent('ObjectName'), !$use);
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
        // System state
        $id = $this->GetIDForIdent('SystemState');
        $use = $this->ReadPropertyBoolean('EnableSystemState');
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
        // Unregister all variable updates first
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
        // Remote Controls
        $variables = json_decode($this->ReadPropertyString('RemoteControls'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
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

    private function ValidateConfiguration(): void
    {
        $state = 102;
        // Alarm zones
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        if (!empty($alarmZones)) {
            foreach ($alarmZones as $alarmZone) {
                $id = $alarmZone->ID;
                if ($id != 0) {
                    if (!@IPS_ObjectExists($id)) {
                        $this->LogMessage('Alarmzonensteuerung ID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        $instance = IPS_GetInstance($id);
                        $moduleID = $instance['ModuleInfo']['ModuleID'];
                        if ($moduleID !== self::ALARMZONE_MODULE_GUID) {
                            $this->LogMessage('Alarmzonensteuerung GUID ungültig!', KL_ERROR);
                            $state = 200;
                        }
                    }
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
                if ($moduleID !== self::ALARMPROTOKOLL_MODULE_GUID) {
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
                if ($moduleID !== self::QUITTUNGSTON_MODULE_GUID) {
                    $this->LogMessage('Quittungston GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Alarm zone state signal lamp
        $id = $this->ReadPropertyInteger('SystemStateSignalLamp');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Systemstatus Signalleuchte ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::SIGNALLEUCHTE_MODULE_GUID) {
                    $this->LogMessage('Systemstatus Signalleuchte GUID ungültig!', KL_ERROR);
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
                if ($moduleID !== self::SIGNALLEUCHTE_MODULE_GUID) {
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
                if ($moduleID !== self::SIGNALLEUCHTE_MODULE_GUID) {
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
                if ($moduleID !== self::BENACHRICHTIGUNGSZENTRALE_MODULE_GUID) {
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
                if ($moduleID !== self::ALARMSIRENE_MODULE_GUID) {
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
                if ($moduleID !== self::ALARMBELEUCHTUNG_MODULE_GUID) {
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
                if ($moduleID !== self::ALARMANRUF_MODULE_GUID) {
                    $this->LogMessage('Alarmanruf GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Set state
        $this->SetStatus($state);
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}
