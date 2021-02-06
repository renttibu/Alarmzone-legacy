<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone
 */

/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Alarmzonensteuerung extends IPSModule
{
    // Helper
    use AZS_backupRestore;
    use AZS_controlAlarmZones;
    use AZS_remoteControls;
    use AZS_updateStates;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterAttributeBoolean('DisableUpdateMode', false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetOptions();
        $this->RegisterMessages();
        $this->WriteAttributeBoolean('DisableUpdateMode', false);
        $this->UpdateStates();
        $this->ValidateConfiguration();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        $this->SendDebug(__FUNCTION__, 'Microtime:' . microtime(true), 0);
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
                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value
                if ($this->CheckMaintenanceMode()) {
                    return;
                }
                $properties = [
                    'FullProtectionMode',
                    'HullProtectionMode',
                    'PartialProtectionMode',
                    'SystemState',
                    'AlarmState',
                    'AlertingSensor',
                    'DoorWindowState',
                    'MotionDetectorState',
                    'RemoteControls',
                    'AlarmSiren',
                    'AlarmLight',
                    'AlarmCall'];
                foreach ($properties as $property) {
                    $variables = json_decode($this->ReadPropertyString($property), true);
                    if (!empty($variables)) {
                        if (array_search($SenderID, array_column($variables, 'ID')) !== false) {
                            if ($property == 'RemoteControls') {
                                //Trigger action
                                $valueChanged = 'false';
                                if ($Data[1]) {
                                    $valueChanged = 'true';
                                }
                                $scriptText = 'AZS_TriggerRemoteControlAction(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                            } else {
                                $scriptText = 'AZS_Update' . $property . '(' . $this->InstanceID . ');';
                            }
                            $this->SendDebug(__FUNCTION__, 'Methode: ' . $scriptText, 0);
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $result = true;
        //Alarm zones
        $vars = json_decode($this->ReadPropertyString('AlarmZones'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                $rowColor = '';
                $id = $var->ID;
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                    $result = false;
                }
                $formData['elements'][2]['items'][0]['values'][] = [
                    'ID'          => $id,
                    'Description' => $var->Description,
                    'rowColor'    => $rowColor];
            }
        }
        //Properties
        $properties = [];
        array_push($properties, ['name' => 'FullProtectionMode', 'position' => 3]);
        array_push($properties, ['name' => 'HullProtectionMode', 'position' => 4]);
        array_push($properties, ['name' => 'PartialProtectionMode', 'position' => 5]);
        array_push($properties, ['name' => 'SystemState', 'position' => 6]);
        array_push($properties, ['name' => 'AlarmState', 'position' => 7]);
        array_push($properties, ['name' => 'AlertingSensor', 'position' => 8]);
        array_push($properties, ['name' => 'DoorWindowState', 'position' => 9]);
        array_push($properties, ['name' => 'MotionDetectorState', 'position' => 10]);
        array_push($properties, ['name' => 'AlarmSiren', 'position' => 11]);
        array_push($properties, ['name' => 'AlarmLight', 'position' => 12]);
        array_push($properties, ['name' => 'AlarmCall', 'position' => 13]);
        if (!empty($properties)) {
            foreach ($properties as $property) {
                $propertyName = $property['name'];
                $propertyPosition = $property['position'];
                $vars = json_decode($this->ReadPropertyString($propertyName));
                if (!empty($vars)) {
                    foreach ($vars as $var) {
                        $rowColor = '';
                        $id = $var->ID;
                        if ($id == 0 || !@IPS_ObjectExists($id)) {
                            if ($var->Use) {
                                $rowColor = '#FFC0C0'; # red
                                $result = false;
                            }
                        }
                        $formData['elements'][$propertyPosition]['items'][0]['values'][] = [
                            'Use'         => $var->Use,
                            'ID'          => $id,
                            'Description' => $var->Description,
                            'rowColor'    => $rowColor];
                    }
                }
            }
        }
        //Remote controls
        $vars = json_decode($this->ReadPropertyString('RemoteControls'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                $id = $var->ID;
                $action = $var->Action;
                $scriptID = $var->ScriptID;
                $rowColor = '';
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    if ($var->Use) {
                        $rowColor = '#FFC0C0'; # red
                        $result = false;
                    }
                }
                if ($action == 5) { # script
                    if ($scriptID == 0 || !@IPS_ObjectExists($scriptID)) {
                        if ($var->Use) {
                            $rowColor = '#FFC0C0'; # red
                            $result = false;
                        }
                    }
                }
                $formData['elements'][13]['items'][0]['values'][] = [
                    'Use'      => $var->Use,
                    'Name'     => $var->Name,
                    'ID'       => $id,
                    'Action'   => $var->Action,
                    'ScriptID' => $var->ScriptID,
                    'rowColor' => $rowColor];
            }
        }
        //Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '';
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
            $formData['actions'][1]['items'][0]['values'][] = [
                'SenderID'                                              => $senderID,
                'SenderName'                                            => $senderName,
                'MessageID'                                             => $messageID,
                'MessageDescription'                                    => $messageDescription,
                'rowColor'                                              => $rowColor];
        }
        $status = $this->GetStatus();
        if (!$result && $status == 102) {
            $status = 201;
        }
        $this->SetStatus($status);
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
                $this->ToggleFullProtectionMode($Value, (string) $id);
                break;

            case 'HullProtectionMode':
                $id = $this->GetIDForIdent('HullProtectionMode');
                $this->ToggleHullProtectionMode($Value, (string) $id);
                break;

            case 'PartialProtectionMode':
                $id = $this->GetIDForIdent('PartialProtectionMode');
                $this->TogglePartialProtectionMode($Value, (string) $id);
                break;

        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        //Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableLocation', true);
        $this->RegisterPropertyBoolean('EnableFullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableHullProtectionMode', false);
        $this->RegisterPropertyBoolean('EnablePartialProtectionMode', false);
        $this->RegisterPropertyBoolean('EnableSystemState', true);
        $this->RegisterPropertyBoolean('EnableAlarmState', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableMotionDetectorState', false);
        $this->RegisterPropertyBoolean('EnableAlarmSirenState', true);
        $this->RegisterPropertyBoolean('EnableAlarmLightState', true);
        $this->RegisterPropertyBoolean('EnableAlarmCallState', true);
        //Description
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyString('FullProtectionName', 'Vollschutz');
        $this->RegisterPropertyString('HullProtectionName', 'Hüllschutz');
        $this->RegisterPropertyString('PartialProtectionName', 'Teilschutz');
        //Alarm zones
        $this->RegisterPropertyString('AlarmZones', '[]');
        //Trigger
        $this->RegisterPropertyString('FullProtectionMode', '[]');
        $this->RegisterPropertyString('HullProtectionMode', '[]');
        $this->RegisterPropertyString('PartialProtectionMode', '[]');
        $this->RegisterPropertyString('SystemState', '[]');
        $this->RegisterPropertyString('AlarmState', '[]');
        $this->RegisterPropertyString('AlertingSensor', '[]');
        $this->RegisterPropertyString('DoorWindowState', '[]');
        $this->RegisterPropertyString('MotionDetectorState', '[]');
        //Remote controls
        $this->RegisterPropertyString('RemoteControls', '[]');
        //Alarm siren
        $this->RegisterPropertyString('AlarmSiren', '[]');
        //Alarm light
        $this->RegisterPropertyString('AlarmLight', '[]');
        //Alarm call
        $this->RegisterPropertyString('AlarmCall', '[]');
    }

    private function CreateProfiles(): void
    {
        //System state
        $profile = 'AZS.' . $this->InstanceID . '.SystemState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
        //Alarm state
        $profile = 'AZS.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Voralarm', 'Clock', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 3, 'Stummer Alarm', 'Warning', 0xFF9300);
        //Door and window state
        $profile = 'AZS.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        //Motion detector state
        $profile = 'AZS.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['SystemState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'AZS.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        //Location
        $this->RegisterVariableString('Location', 'Standortbezeichnung', '', 10);
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        IPS_SetIcon($this->GetIDForIdent('Location'), 'IPS');
        //Full protection mode
        $name = $this->ReadPropertyString('FullProtectionName');
        $this->RegisterVariableBoolean('FullProtectionMode', $name, '~Switch', 30);
        $this->EnableAction('FullProtectionMode');
        IPS_SetIcon($this->GetIDForIdent('FullProtectionMode'), 'Basement');
        //Hull protection mode
        $name = $this->ReadPropertyString('HullProtectionName');
        $this->RegisterVariableBoolean('HullProtectionMode', $name, '~Switch', 40);
        $this->EnableAction('HullProtectionMode');
        IPS_SetIcon($this->GetIDForIdent('HullProtectionMode'), 'GroundFloor');
        //Partial protection mode
        $name = $this->ReadPropertyString('PartialProtectionName');
        $this->RegisterVariableBoolean('PartialProtectionMode', $name, '~Switch', 50);
        $this->EnableAction('PartialProtectionMode');
        IPS_SetIcon($this->GetIDForIdent('PartialProtectionMode'), 'Moon');
        //System state
        $profile = 'AZS.' . $this->InstanceID . '.SystemState';
        $this->RegisterVariableInteger('SystemState', 'Systemstatus', $profile, 60);
        //Door and window state
        $profile = 'AZS.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen- und Fenster', $profile, 70);
        //Motion detector state
        $profile = 'AZS.' . $this->InstanceID . '.MotionDetectorState';
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 80);
        //Alarm state
        $profile = 'AZS.' . $this->InstanceID . '.AlarmState';
        $this->RegisterVariableInteger('AlarmState', 'Alarmstatus', $profile, 90);
        //Alerting sensor
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Alarmsensor', '', 100);
        $this->SetValue('AlertingSensor', $this->ReadPropertyString('AlertingSensor'));
        IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Eyes');
        //Alarm siren
        $id = @$this->GetIDForIdent('AlarmSiren');
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', 'Switch', 110);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlarmSiren'), 'Alert');
        }
        //Alarm light
        $id = @$this->GetIDForIdent('AlarmLight');
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', 'Switch', 120);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlarmLight'), 'Bulb');
        }
        //Alarm call
        $id = @$this->GetIDForIdent('AlarmCall');
        $this->RegisterVariableBoolean('AlarmCall', 'Alarmanruf', 'Switch', 130);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlarmCall'), 'Mobile');
        }
    }

    private function SetOptions(): void
    {
        //Location
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        IPS_SetHidden($this->GetIDForIdent('Location'), !$this->ReadPropertyBoolean('EnableLocation'));
        //Full protection mode
        $id = $this->GetIDForIdent('FullProtectionMode');
        IPS_SetName($id, $this->ReadPropertyString('FullProtectionName'));
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableFullProtectionMode'));
        //Hull protection mode
        $id = $this->GetIDForIdent('HullProtectionMode');
        IPS_SetName($id, $this->ReadPropertyString('HullProtectionName'));
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableHullProtectionMode'));
        //Partial protection mode
        $id = $this->GetIDForIdent('PartialProtectionMode');
        IPS_SetName($id, $this->ReadPropertyString('PartialProtectionName'));
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnablePartialProtectionMode'));
        //System state
        IPS_SetHidden($this->GetIDForIdent('SystemState'), !$this->ReadPropertyBoolean('EnableSystemState'));
        //Alarm state
        IPS_SetHidden($this->GetIDForIdent('AlarmState'), !$this->ReadPropertyBoolean('EnableAlarmState'));
        //Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));
        //Motion detector state
        IPS_SetHidden($this->GetIDForIdent('MotionDetectorState'), !$this->ReadPropertyBoolean('EnableMotionDetectorState'));
        //Alarm siren state
        IPS_SetHidden($this->GetIDForIdent('AlarmSiren'), !$this->ReadPropertyBoolean('EnableAlarmSirenState'));
        //Alarm light state
        IPS_SetHidden($this->GetIDForIdent('AlarmLight'), !$this->ReadPropertyBoolean('EnableAlarmLightState'));
        //Alarm call state
        IPS_SetHidden($this->GetIDForIdent('AlarmCall'), !$this->ReadPropertyBoolean('EnableAlarmCallState'));
    }

    private function RegisterMessages(): void
    {
        //Unregister
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
        $properties = [
            'FullProtectionMode',
            'HullProtectionMode',
            'PartialProtectionMode',
            'SystemState',
            'AlarmState',
            'AlertingSensor',
            'DoorWindowState',
            'MotionDetectorState',
            'RemoteControls',
            'AlarmSiren',
            'AlarmLight',
            'AlarmCall'];
        //Register
        foreach ($properties as $property) {
            $variables = json_decode($this->ReadPropertyString($property));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    if ($variable->Use) {
                        if ($variable->ID != 0 && IPS_ObjectExists($variable->ID)) {
                            $this->RegisterMessage($variable->ID, VM_UPDATE);
                        }
                    }
                }
            }
        }
    }

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        //Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $result = false;
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        return $result;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}