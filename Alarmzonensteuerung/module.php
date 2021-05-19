<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzonensteuerung
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
        // Never delete this line!
        parent::Create();

        // Properties
        // Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableLocation', true);
        $this->RegisterPropertyBoolean('EnableFullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableHullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnablePartialProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableSystemState', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableMotionDetectorState', true);
        $this->RegisterPropertyBoolean('EnableAlarmState', true);
        $this->RegisterPropertyBoolean('EnableAlarmSirenState', true);
        $this->RegisterPropertyBoolean('EnableAlarmLightState', true);
        $this->RegisterPropertyBoolean('EnableAlarmCallState', true);
        $this->RegisterPropertyBoolean('EnableAlertingSensor', true);
        // Description
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyString('FullProtectionName', 'Vollschutz');
        $this->RegisterPropertyString('HullProtectionName', 'Hüllschutz');
        $this->RegisterPropertyString('PartialProtectionName', 'Teilschutz');
        // Alarm zones
        $this->RegisterPropertyString('AlarmZones', '[]');
        // Trigger
        $this->RegisterPropertyString('FullProtectionMode', '[]');
        $this->RegisterPropertyString('HullProtectionMode', '[]');
        $this->RegisterPropertyString('PartialProtectionMode', '[]');
        $this->RegisterPropertyString('SystemState', '[]');
        $this->RegisterPropertyString('AlarmState', '[]');
        $this->RegisterPropertyString('AlertingSensor', '[]');
        $this->RegisterPropertyString('DoorWindowState', '[]');
        $this->RegisterPropertyString('MotionDetectorState', '[]');
        // Remote controls
        $this->RegisterPropertyString('RemoteControls', '[]');
        // Alarm siren
        $this->RegisterPropertyString('AlarmSiren', '[]');
        // Alarm light
        $this->RegisterPropertyString('AlarmLight', '[]');
        // Alarm call
        $this->RegisterPropertyString('AlarmCall', '[]');

        // Variables
        // Location
        $id = @$this->GetIDForIdent('Location');
        $this->RegisterVariableString('Location', 'Standortbezeichnung', '', 10);
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('Location'), 'IPS');
        }
        // Full protection mode
        $id = @$this->GetIDForIdent('FullProtectionMode');
        $name = $this->ReadPropertyString('FullProtectionName');
        $this->RegisterVariableBoolean('FullProtectionMode', $name, '~Switch', 30);
        $this->EnableAction('FullProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('FullProtectionMode'), 'Basement');
        }
        // Hull protection mode
        $id = @$this->GetIDForIdent('HullProtectionMode');
        $name = $this->ReadPropertyString('HullProtectionName');
        $this->RegisterVariableBoolean('HullProtectionMode', $name, '~Switch', 40);
        $this->EnableAction('HullProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('HullProtectionMode'), 'GroundFloor');
        }
        // Partial protection mode
        $id = @$this->GetIDForIdent('PartialProtectionName');
        $name = $this->ReadPropertyString('PartialProtectionName');
        $this->RegisterVariableBoolean('PartialProtectionMode', $name, '~Switch', 50);
        $this->EnableAction('PartialProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('PartialProtectionMode'), 'Moon');
        }
        // System state
        $profile = 'AZS.' . $this->InstanceID . '.SystemState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'IPS', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
        $this->RegisterVariableInteger('SystemState', 'Systemstatus', $profile, 60);
        // Door and window state
        $profile = 'AZS.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen- und Fenster', $profile, 70);
        // Motion detector state
        $profile = 'AZS.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 80);
        // Alarm state
        $profile = 'AZS.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Warning', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Voralarm', 'Clock', 0xFFFF00);
        $this->RegisterVariableInteger('AlarmState', 'Alarmstatus', $profile, 90);
        // Alarm siren
        $profile = 'AZS.' . $this->InstanceID . '.AlarmSirenStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Alert');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', $profile, 100);
        // Alarm light
        $profile = 'AZS.' . $this->InstanceID . '.AlarmLightStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Bulb');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', $profile, 110);
        // Alarm call
        $profile = 'AZS.' . $this->InstanceID . '.AlarmCallStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Mobile');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmCall', 'Alarmanruf', $profile, 120);
        // Alerting sensor
        $id = @$this->GetIDForIdent('AlertingSensor');
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Alarmsensor', '', 130);
        $this->SetValue('AlertingSensor', $this->ReadPropertyString('AlertingSensor'));
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Eyes');
        }

        // Attribute
        $this->RegisterAttributeBoolean('DisableUpdateMode', false);
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

        // Options
        // Location
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        IPS_SetHidden($this->GetIDForIdent('Location'), !$this->ReadPropertyBoolean('EnableLocation'));
        // Full protection mode
        $id = $this->GetIDForIdent('FullProtectionMode');
        IPS_SetName($id, $this->ReadPropertyString('FullProtectionName'));
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableFullProtectionMode'));
        // Hull protection mode
        $id = $this->GetIDForIdent('HullProtectionMode');
        IPS_SetName($id, $this->ReadPropertyString('HullProtectionName'));
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableHullProtectionMode'));
        // Partial protection mode
        $id = $this->GetIDForIdent('PartialProtectionMode');
        IPS_SetName($id, $this->ReadPropertyString('PartialProtectionName'));
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnablePartialProtectionMode'));
        // System state
        IPS_SetHidden($this->GetIDForIdent('SystemState'), !$this->ReadPropertyBoolean('EnableSystemState'));
        // Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));
        // Motion detector state
        IPS_SetHidden($this->GetIDForIdent('MotionDetectorState'), !$this->ReadPropertyBoolean('EnableMotionDetectorState'));
        // Alarm state
        IPS_SetHidden($this->GetIDForIdent('AlarmState'), !$this->ReadPropertyBoolean('EnableAlarmState'));
        // Alarm siren state
        IPS_SetHidden($this->GetIDForIdent('AlarmSiren'), !$this->ReadPropertyBoolean('EnableAlarmSirenState'));
        // Alarm light state
        IPS_SetHidden($this->GetIDForIdent('AlarmLight'), !$this->ReadPropertyBoolean('EnableAlarmLightState'));
        // Alarm call state
        IPS_SetHidden($this->GetIDForIdent('AlarmCall'), !$this->ReadPropertyBoolean('EnableAlarmCallState'));
        // Alerting sensor
        IPS_SetHidden($this->GetIDForIdent('AlertingSensor'), !$this->ReadPropertyBoolean('EnableAlertingSensor'));

        // Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Delete all registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        // Register references and update messages
        $alarmZones = json_decode($this->ReadPropertyString('AlarmZones'));
        foreach ($alarmZones as $alarmZone) {
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
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
        foreach ($properties as $property) {
            $variables = json_decode($this->ReadPropertyString($property));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    if ($variable->Use) {
                        if ($variable->ID != 0 && IPS_ObjectExists($variable->ID)) {
                            $this->RegisterMessage($variable->ID, VM_UPDATE);
                            $this->RegisterReference($variable->ID);
                        }
                    }
                }
            }
        }
        $this->WriteAttributeBoolean('DisableUpdateMode', false);
        $this->UpdateStates();
        $this->ValidateConfiguration();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $profiles = ['SystemState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState', 'AlarmSirenStatus', 'AlarmLightStatus', 'AlarmCallStatus'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'AZS.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
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

                // Check trigger variable
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
                                // Trigger action
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
        // Alarm zones
        $vars = json_decode($this->ReadPropertyString('AlarmZones'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                $rowColor = '';
                $id = $var->ID;
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][2]['items'][0]['values'][] = [
                    'ID'          => $id,
                    'Description' => $var->Description,
                    'rowColor'    => $rowColor];
            }
        }
        // Properties
        $properties = [];
        array_push($properties, ['name' => 'FullProtectionMode', 'position' => 3]);
        array_push($properties, ['name' => 'HullProtectionMode', 'position' => 4]);
        array_push($properties, ['name' => 'PartialProtectionMode', 'position' => 5]);
        array_push($properties, ['name' => 'SystemState', 'position' => 6]);
        array_push($properties, ['name' => 'AlarmState', 'position' => 7]);
        array_push($properties, ['name' => 'AlertingSensor', 'position' => 8]);
        array_push($properties, ['name' => 'DoorWindowState', 'position' => 9]);
        array_push($properties, ['name' => 'MotionDetectorState', 'position' => 10]);
        array_push($properties, ['name' => 'AlarmSiren', 'position' => 12]);
        array_push($properties, ['name' => 'AlarmLight', 'position' => 13]);
        array_push($properties, ['name' => 'AlarmCall', 'position' => 14]);
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
        // Remote controls
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
                    }
                }
                if ($action == 5) { # script
                    if ($scriptID == 0 || !@IPS_ObjectExists($scriptID)) {
                        if ($var->Use) {
                            $rowColor = '#FFC0C0'; # red
                        }
                    }
                }
                $formData['elements'][11]['items'][0]['values'][] = [
                    'Use'      => $var->Use,
                    'Name'     => $var->Name,
                    'ID'       => $id,
                    'Action'   => $var->Action,
                    'ScriptID' => $var->ScriptID,
                    'rowColor' => $rowColor];
            }
        }
        // Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '#C0FFC0'; # light green
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
        // Status
        $formData['status'][0] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => 'Alarmzonensteuerung wird erstellt',
        ];
        $formData['status'][1] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => 'Alarmzonensteuerung ist aktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][2] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => 'Alarmzonensteuerung wird gelöscht (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][3] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => 'Alarmzonensteuerung ist inaktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][4] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][5] = [
            'code'    => 201,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, bitte Konfiguration prüfen! (ID ' . $this->InstanceID . ')',
        ];
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function EnableAlarmZonesConfigurationButton(int $ObjectID): void
    {
        $this->UpdateFormField('AlarmZonesConfigurationButton', 'caption', 'ID ' . $ObjectID . ' konfigurieren');
        $this->UpdateFormField('AlarmZonesConfigurationButton', 'visible', true);
        $this->UpdateFormField('AlarmZonesConfigurationButton', 'enabled', true);
        $this->UpdateFormField('AlarmZonesConfigurationButton', 'objectID', $ObjectID);
    }

    public function EnableRemoteControlsConfigurationButton(int $ObjectID): void
    {
        $this->UpdateFormField('RemoteControlsConfigurationButton', 'caption', 'ID ' . $ObjectID . ' bearbeiten');
        $this->UpdateFormField('RemoteControlsConfigurationButton', 'visible', true);
        $this->UpdateFormField('RemoteControlsConfigurationButton', 'enabled', true);
        $this->UpdateFormField('RemoteControlsConfigurationButton', 'objectID', $ObjectID);
    }

    public function ShowVariableDetails(int $VariableID): void
    {
        if ($VariableID == 0 || !@IPS_ObjectExists($VariableID)) {
            return;
        }
        if ($VariableID != 0) {
            // Variable
            echo 'ID: ' . $VariableID . "\n";
            echo 'Name: ' . IPS_GetName($VariableID) . "\n";
            $variable = IPS_GetVariable($VariableID);
            if (!empty($variable)) {
                $variableType = $variable['VariableType'];
                switch ($variableType) {
                    case 0:
                        $variableTypeName = 'Boolean';
                        break;

                    case 1:
                        $variableTypeName = 'Integer';
                        break;

                    case 2:
                        $variableTypeName = 'Float';
                        break;

                    case 3:
                        $variableTypeName = 'String';
                        break;

                    default:
                        $variableTypeName = 'Unbekannt';
                }
                echo 'Variablentyp: ' . $variableTypeName . "\n";
            }
            // Profile
            $profile = @IPS_GetVariableProfile($variable['VariableProfile']);
            if (empty($profile)) {
                $profile = @IPS_GetVariableProfile($variable['VariableCustomProfile']);
            }
            if (!empty($profile)) {
                $profileType = $variable['VariableType'];
                switch ($profileType) {
                    case 0:
                        $profileTypeName = 'Boolean';
                        break;

                    case 1:
                        $profileTypeName = 'Integer';
                        break;

                    case 2:
                        $profileTypeName = 'Float';
                        break;

                    case 3:
                        $profileTypeName = 'String';
                        break;

                    default:
                        $profileTypeName = 'Unbekannt';
                }
                echo 'Profilname: ' . $profile['ProfileName'] . "\n";
                echo 'Profiltyp: ' . $profileTypeName . "\n\n";
            }
            if (!empty($variable)) {
                echo "\nVariable:\n";
                print_r($variable);
            }
            if (!empty($profile)) {
                echo "\nVariablenprofil:\n";
                print_r($profile);
            }
        }
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

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Maintenance mode
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