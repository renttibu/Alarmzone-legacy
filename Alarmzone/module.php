<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzone
 */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Alarmzone extends IPSModule
{
    // Helper
    use AZ_alarmProtocol;
    use AZ_backupRestore;
    use AZ_blacklist;
    use AZ_controlAlarmZone;
    use AZ_doorWindowSensors;
    use AZ_motionDetectors;

    // Constants
    private const LIBRARY_GUID = '{8464371D-1C4E-B070-9884-82DB73545FFA}';
    private const MODULE_NAME = 'Alarmzone';
    private const MODULE_PREFIX = 'UBAZ';
    private const ALARMPROTOCOL_MODULE_GUID = '{33EF9DF1-C8D7-01E7-F168-0A1927F1C61F}';
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        // Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableLocation', true);
        $this->RegisterPropertyBoolean('EnableAlarmZoneName', true);
        $this->RegisterPropertyBoolean('EnableFullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableHullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnablePartialProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableAlarmZoneState', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableMotionDetectorState', true);
        $this->RegisterPropertyBoolean('EnableAlarmState', true);
        $this->RegisterPropertyBoolean('EnableAlertingSensor', true);
        $this->RegisterPropertyBoolean('EnableAlarmSirenState', true);
        $this->RegisterPropertyBoolean('EnableAlarmLightState', true);
        $this->RegisterPropertyBoolean('EnableAlarmCallState', true);
        // Designations
        $this->RegisterPropertyString('SystemName', 'Alarmzone');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyString('AlarmZoneName', '');
        $this->RegisterPropertyString('FullProtectionName', 'Vollschutz');
        $this->RegisterPropertyString('HullProtectionName', 'Hüllschutz');
        $this->RegisterPropertyString('PartialProtectionName', 'Teilschutz');
        // Activation check
        $this->RegisterPropertyBoolean('CheckFullProtectionModeActivation', false);
        $this->RegisterPropertyBoolean('CheckHullProtectionModeActivation', false);
        $this->RegisterPropertyBoolean('CheckPartialProtectionModeActivation', false);
        // Activation delay
        $this->RegisterPropertyInteger('FullProtectionModeActivationDelay', 0);
        $this->RegisterPropertyInteger('HullProtectionModeActivationDelay', 0);
        $this->RegisterPropertyInteger('PartialProtectionModeActivationDelay', 0);
        // Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
        // Alarm sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyString('MotionDetectors', '[]');

        // Variables
        // Location
        $id = @$this->GetIDForIdent('Location');
        $this->RegisterVariableString('Location', 'Standortbezeichnung', '', 10);
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('Location'), 'IPS');
        }
        // Alarm zone name
        $id = @$this->GetIDForIdent('AlarmZoneName');
        $this->RegisterVariableString('AlarmZoneName', 'Alarmzonenbezeichnung', '', 20);
        $this->SetValue('AlarmZoneName', $this->ReadPropertyString('AlarmZoneName'));
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlarmZoneName'), 'IPS');
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
        $id = @$this->GetIDForIdent('HullProtectionName');
        $name = $this->ReadPropertyString('HullProtectionName');
        $this->RegisterVariableBoolean('HullProtectionMode', $name, '~Switch', 40);
        $this->EnableAction('HullProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('HullProtectionMode'), 'GroundFloor');
        }
        // Partial protection mode
        $id = @$this->GetIDForIdent('PartialProtectionMode');
        $name = $this->ReadPropertyString('PartialProtectionName');
        $this->RegisterVariableBoolean('PartialProtectionMode', $name, '~Switch', 50);
        $this->EnableAction('PartialProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('PartialProtectionMode'), 'Moon');
        }
        // Alarm zone state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmZoneState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'IPS', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
        $this->RegisterVariableInteger('AlarmZoneState', 'Alarmzone', $profile, 60);
        // Door and window state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen und Fenster', $profile, 70);
        // Motion detector state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 80);
        // Alarm state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Warning', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        $this->RegisterVariableInteger('AlarmState', 'Alarm', $profile, 90);
        // Alerting sensor
        $id = @$this->GetIDForIdent('AlertingSensor');
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Alarmsensor', '', 100);
        $this->SetValue('AlertingSensor', 'OK');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Eyes');
        }
        // Alarm siren
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmSirenStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Alert');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', $profile, 110);
        // Alarm light
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmLightStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Bulb');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', $profile, 120);
        // Alarm call
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmCallStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Mobile');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmCall', 'Alarmanruf', $profile, 130);

        // Attribute
        $this->RegisterAttributeBoolean('PreAlarm', false);

        // Timers
        $this->RegisterTimer('StartActivation', 0, self::MODULE_PREFIX . '_StartActivation(' . $this->InstanceID . ');');
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
        // Alarm zone name
        $this->SetValue('AlarmZoneName', $this->ReadPropertyString('AlarmZoneName'));
        IPS_SetHidden($this->GetIDForIdent('AlarmZoneName'), !$this->ReadPropertyBoolean('EnableAlarmZoneName'));
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
        // Alarm zone state
        IPS_SetHidden($this->GetIDForIdent('AlarmZoneState'), !$this->ReadPropertyBoolean('EnableAlarmZoneState'));
        // Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));
        // Motion detector state
        IPS_SetHidden($this->GetIDForIdent('MotionDetectorState'), !$this->ReadPropertyBoolean('EnableMotionDetectorState'));
        // Alarm state
        IPS_SetHidden($this->GetIDForIdent('AlarmState'), !$this->ReadPropertyBoolean('EnableAlarmState'));
        // Alerting sensor
        IPS_SetHidden($this->GetIDForIdent('AlertingSensor'), !$this->ReadPropertyBoolean('EnableAlertingSensor'));
        // Alarm siren state
        IPS_SetHidden($this->GetIDForIdent('AlarmSiren'), !$this->ReadPropertyBoolean('EnableAlarmSirenState'));
        // Alarm light state
        IPS_SetHidden($this->GetIDForIdent('AlarmLight'), !$this->ReadPropertyBoolean('EnableAlarmLightState'));
        // Alarm call state
        IPS_SetHidden($this->GetIDForIdent('AlarmCall'), !$this->ReadPropertyBoolean('EnableAlarmCallState'));

        $this->SetTimerInterval('StartActivation', 0);
        $this->ResetBlacklist();

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

        // Validation
        if ($this->ValidateConfiguration()) {
            // Register references and update messages
            $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
            $properties = ['AlarmProtocol'];
            foreach ($properties as $property) {
                $id = $this->ReadPropertyInteger($property);
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $this->RegisterReference($id);
                }
            }
            $propertyNames = ['DoorWindowSensors', 'MotionDetectors'];
            foreach ($propertyNames as $propertyName) {
                foreach (json_decode($this->ReadPropertyString($propertyName)) as $variable) {
                    if ($variable->Use) {
                        $id = $variable->ID;
                        if ($id != 0 && @IPS_ObjectExists($id)) {
                            $this->RegisterReference($id);
                            $this->RegisterMessage($id, VM_UPDATE);
                        }
                    }
                }
            }

            $this->UpdateStates();
        }
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $profiles = ['AlarmZoneState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState', 'AlarmSirenStatus', 'AlarmLightStatus', 'AlarmCallStatus'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
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

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                // Check door and window variable
                if (array_search($SenderID, array_column(json_decode($this->ReadPropertyString('DoorWindowSensors'), true), 'ID')) !== false) {
                    $valueChanged = 'false';
                    if ($Data[1]) {
                        $valueChanged = 'true';
                    }
                    $scriptText = self::MODULE_PREFIX . '_CheckDoorWindowSensorAlerting(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                    @IPS_RunScriptText($scriptText);
                }

                // Check motion detector variable
                if (array_search($SenderID, array_column(json_decode($this->ReadPropertyString('MotionDetectors'), true), 'ID')) !== false) {
                    $valueChanged = 'false';
                    if ($Data[1]) {
                        $valueChanged = 'true';
                    }
                    $scriptText = self::MODULE_PREFIX . '_CheckMotionDetectorAlerting(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                    @IPS_RunScriptText($scriptText);
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $form = [];

        #################### Elements

        ########## Functions

        ##### Functions panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Funktionen',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'MaintenanceMode',
                    'caption' => 'Wartungsmodus'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableLocation',
                    'caption' => 'Standortbezeichnung'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmZoneName',
                    'caption' => 'Alarmzonenbezeichnung'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableFullProtectionMode',
                    'caption' => 'Vollschutz'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableHullProtectionMode',
                    'caption' => 'Hüllschutz'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnablePartialProtectionMode',
                    'caption' => 'Teilschutz'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmZoneState',
                    'caption' => 'Alarmzonenstatus'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableDoorWindowState',
                    'caption' => 'Tür- und Fensterstatus'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableMotionDetectorState',
                    'caption' => 'Bewegungsmelderstatus'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmState',
                    'caption' => 'Alarmstatus'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlertingSensor',
                    'caption' => 'Auslösender Alarmsensor'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmSirenState',
                    'caption' => 'Alarmsirene'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmLightState',
                    'caption' => 'Alarmbeleuchtung'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmCallState',
                    'caption' => 'Alarmanruf'
                ]
            ]
        ];

        ########## Designations

        ##### Designations panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Bezeichnungen',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'SystemName',
                    'caption' => 'Systembezeichnung (z.B. Alarmzone, Alarmanlage, Einbruchmeldeanlage)',
                    'width'   => '600px'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'Location',
                    'caption' => 'Standortbezeichnung (z.B. Musterstraße 1)',
                    'width'   => '600px'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'AlarmZoneName',
                    'caption' => 'Alarmzonenbezeichnung (z.B. Haus, Wohnung, Erdgeschoss, Obergeschoss)',
                    'width'   => '600px'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'FullProtectionName',
                    'caption' => 'Vollschutz',
                    'width'   => '600px'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'HullProtectionName',
                    'caption' => 'Hüllschutz',
                    'width'   => '600px'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'PartialProtectionName',
                    'caption' => 'Teilschutz',
                    'width'   => '600px'
                ]
            ]
        ];

        ########## Activation check

        ##### Activation check panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Aktivierungsprüfung',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'CheckFullProtectionModeActivation',
                    'caption' => 'Vollschutz'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'CheckHullProtectionModeActivation',
                    'caption' => 'Hüllschutz'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'CheckPartialProtectionModeActivation',
                    'caption' => 'Teilschutz'
                ]
            ]
        ];

        ########## Activation delay

        ##### Activation delay panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Einschaltverzögerung',
            'items'   => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'FullProtectionModeActivationDelay',
                    'caption' => 'Vollschutz',
                    'suffix'  => 'Sekunden',
                    'minimum' => 0,
                    'maximum' => 60
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'HullProtectionModeActivationDelay',
                    'caption' => 'Hüllschutz',
                    'suffix'  => 'Sekunden',
                    'minimum' => 0,
                    'maximum' => 60
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'PartialProtectionModeActivationDelay',
                    'caption' => 'Teilschutz',
                    'suffix'  => 'Sekunden',
                    'minimum' => 0,
                    'maximum' => 60
                ]
            ]
        ];

        ########## Alarm protocol

        $alarmProtocolID = $this->ReadPropertyInteger('AlarmProtocol');
        $enableButton = false;
        if ($alarmProtocolID != 0 && @IPS_ObjectExists($alarmProtocolID)) {
            $enableButton = true;
        }

        ##### Alarm protocol panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmprotokoll',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'AlarmProtocol',
                            'caption'  => 'Alarmprotokoll',
                            'moduleID' => self::ALARMPROTOCOL_MODULE_GUID,
                            'width'    => '600px',
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ',
                            'visible' => $enableButton
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $alarmProtocolID . ' konfigurieren',
                            'visible'  => $enableButton,
                            'objectID' => $alarmProtocolID
                        ]
                    ]
                ]
            ]
        ];

        ########## Door window sensors

        $doorWindowSensorValues = [];
        foreach (json_decode($this->ReadPropertyString('DoorWindowSensors')) as $doorWindowSensor) {
            $rowColor = '#FFC0C0'; # red
            $stateName = 'unbekannt';
            $id = $doorWindowSensor->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                $stateName = 'geschlossen';
                $blacklisted = false;
                foreach (json_decode($this->GetBuffer('Blacklist'), true) as $blackListedSensor) {
                    if ($blackListedSensor == $id) {
                        $rowColor = '#DFDFDF'; # grey
                        $blacklisted = true;
                        $stateName = 'gesperrt';
                    }
                }
                if (!$blacklisted) {
                    $type = IPS_GetVariable($id)['VariableType'];
                    $value = $doorWindowSensor->TriggerValue;
                    switch ($doorWindowSensor->TriggerType) {
                        case 2: #on limit drop, once (integer, float)
                        case 3: #on limit drop, every time (integer, float)
                            switch ($type) {
                                case 1: #integer
                                    if (GetValueInteger($id) < intval($value)) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                                case 2: #float
                                    if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                            }
                            break;

                        case 4: #on limit exceed, once (integer, float)
                        case 5: #on limit exceed, every time (integer, float)
                            switch ($type) {
                                case 1: #integer
                                    if (GetValueInteger($id) > intval($value)) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                                case 2: #float
                                    if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                            }
                            break;

                        case 6: #on specific value, once (bool, integer, float, string)
                        case 7: #on specific value, every time (bool, integer, float, string)
                            switch ($type) {
                                case 0: #bool
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if (GetValueBoolean($id) == boolval($value)) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                                case 1: #integer
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($id) == intval($value)) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                                case 2: #float
                                    if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                                case 3: #string
                                    if (GetValueString($id) == (string) $value) {
                                        $rowColor = '#C0C0FF'; # violett
                                        $stateName = 'geöffnet';
                                    }
                                    break;

                            }
                            break;

                    }
                    if (!$doorWindowSensor->Use) {
                        $rowColor = '#DFDFDF'; # grey
                        $stateName = 'deaktiviert';
                    }
                }
            }
            $doorWindowSensorValues[] = ['ActualState' => $stateName, 'rowColor' => $rowColor];
        }

        ##### Door window panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Tür- / Fenstersensoren',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'DoorWindowSensors',
                    'rowCount' => 15,
                    'add'      => true,
                    'delete'   => true,
                    'columns'  => [
                        [
                            'name'    => 'Use',
                            'caption' => 'Aktiviert',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ],
                        ],
                        [
                            'name'    => 'ActualState',
                            'caption' => 'Aktueller Status',
                            'width'   => '150px',
                            'add'     => ''
                        ],
                        [
                            'name'    => 'Name',
                            'caption' => 'Bezeichnung',
                            'width'   => '350px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Auslösende Variable',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $DoorWindowSensors["ID"], "DoorWindowSensorsConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name'    => 'Info',
                            'caption' => 'Info',
                            'width'   => '160px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'    => 'Button',
                                'onClick' => self::MODULE_PREFIX . '_ShowVariableDetails($id, $ID);'
                            ]
                        ],
                        [
                            'name'    => 'TriggerType',
                            'caption' => 'Auslöseart',
                            'width'   => '300px',
                            'add'     => 6,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Bei Änderung',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => 'Bei Aktualisierung',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (einmalig)',
                                        'value'   => 2
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (mehrmalig)',
                                        'value'   => 3
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (einmalig)',
                                        'value'   => 4
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (mehrmalig)',
                                        'value'   => 5
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (einmalig)',
                                        'value'   => 6
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (mehrmalig)',
                                        'value'   => 7
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name'    => 'TriggerValue',
                            'caption' => 'Auslösewert',
                            'width'   => '170px',
                            'add'     => 'true',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name'    => 'FullProtectionModeActive',
                            'caption' => 'Vollschutz',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'HullProtectionModeActive',
                            'caption' => 'Hüllschutz',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'PartialProtectionModeActive',
                            'caption' => 'Teilschutz',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'UseAlarmSiren',
                            'caption' => 'Alarmsirene',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'UseAlarmLight',
                            'caption' => 'Alarmbeleuchtung',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'UseAlarmCall',
                            'caption' => 'Alarmanruf',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ]
                    ],
                    'values' => $doorWindowSensorValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'DoorWindowSensorsConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Motion detectors

        $motionDetectorValues = [];
        foreach (json_decode($this->ReadPropertyString('MotionDetectors')) as $motionDetector) {
            $rowColor = '#FFC0C0'; # red
            $id = $motionDetector->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($motionDetector->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $motionDetectorValues[] = ['rowColor' => $rowColor];
        }

        ##### Motion detectors panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Bewegungsmelder',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'MotionDetectors',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'columns'  => [
                        [
                            'caption' => 'Aktiviert',
                            'name'    => 'Use',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Name',
                            'width'   => '350px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Auslösende Variable',
                            'name'    => 'ID',
                            'width'   => '350px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $MotionDetectors["ID"], "MotionDetectorsConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Info',
                            'name'    => 'Info',
                            'width'   => '160px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'    => 'Button',
                                'onClick' => self::MODULE_PREFIX . '_ShowVariableDetails($id, $ID);'
                            ]
                        ],
                        [
                            'caption' => 'Auslöseart',
                            'name'    => 'TriggerType',
                            'width'   => '300px',
                            'add'     => 6,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Bei Änderung',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => 'Bei Aktualisierung',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (einmalig)',
                                        'value'   => 2
                                    ],
                                    [
                                        'caption' => 'Bei Grenzunterschreitung (mehrmalig)',
                                        'value'   => 3
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (einmalig)',
                                        'value'   => 4
                                    ],
                                    [
                                        'caption' => 'Bei Grenzüberschreitung (mehrmalig)',
                                        'value'   => 5
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (einmalig)',
                                        'value'   => 6
                                    ],
                                    [
                                        'caption' => 'Bei bestimmtem Wert (mehrmalig)',
                                        'value'   => 7
                                    ]
                                ]
                            ]
                        ],
                        [
                            'caption' => 'Auslösewert',
                            'name'    => 'TriggerValue',
                            'width'   => '170px',
                            'add'     => 'true',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Vollschutz',
                            'name'    => 'FullProtectionModeActive',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Hüllschutz',
                            'name'    => 'HullProtectionModeActive',
                            'width'   => '170px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Teilschutz',
                            'name'    => 'PartialProtectionModeActive',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Alarmsirene',
                            'name'    => 'UseAlarmSiren',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Alarmbeleuchtung',
                            'name'    => 'UseAlarmLight',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Alarmanruf',
                            'name'    => 'UseAlarmCall',
                            'width'   => '170px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ]
                    ],
                    'values' => $motionDetectorValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'MotionDetectorsConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        #################### Actions

        ##### Configuration panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Konfiguration',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Neu einlesen',
                    'onClick' => self::MODULE_PREFIX . '_ReloadConfiguration($id);'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectCategory',
                            'name'    => 'BackupCategory',
                            'caption' => 'Kategorie',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Sichern',
                            'onClick' => self::MODULE_PREFIX . '_CreateBackup($id, $BackupCategory);'
                        ]
                    ]
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectScript',
                            'name'    => 'ConfigurationScript',
                            'caption' => 'Konfiguration',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'PopupButton',
                            'caption' => 'Wiederherstellen',
                            'popup'   => [
                                'caption' => 'Konfiguration wirklich wiederherstellen?',
                                'items'   => [
                                    [
                                        'type'    => 'Button',
                                        'caption' => 'Wiederherstellen',
                                        'onClick' => self::MODULE_PREFIX . '_RestoreConfiguration($id, $ConfigurationScript);'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        ##### Sensor detection panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmsensoren',
            'items'   => [
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Tür- und Fenstersensoren ermitteln',
                    'popup'   => [
                        'caption' => 'HomeMatic und Homematic IP Tür- und Fenstersensoren wirklich automatisch ermitteln?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Alarmsensoren ermitteln',
                                'onClick' => self::MODULE_PREFIX . '_DetermineDoorWindowVariables($id);'
                            ]
                        ]
                    ]
                ],
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Bewegungsmelder ermitteln',
                    'popup'   => [
                        'caption' => 'HomeMatic und Homematic IP Bewegungsmelder wirklich automatisch ermitteln?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Bewegungsmelder ermitteln',
                                'onClick' => self::MODULE_PREFIX . '_DetermineMotionDetectorVariables($id);'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        ##### Blacklist panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Sperrliste',
            'items'   => [
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Sperrliste zurücksetzen',
                    'popup'   => [
                        'caption' => 'Sperrliste wirklich zurücksetzen?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Sperrliste zurücksetzen',
                                'onClick' => self::MODULE_PREFIX . '_ResetBlackList($id); echo "Die Sperrliste wurde erfolgreich zurückgesetzt!";'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        ##### Test center panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Schaltfunktionen',
            'items'   => [
                [
                    'type' => 'TestCenter',
                ]
            ]
        ];

        #################### Status

        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $version = '[Version ' . $library['Version'] . '-' . $library['Build'] . ' vom ' . date('d.m.Y', $library['Date']) . ']';

        $form['status'] = [
            [
                'code'    => 101,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird erstellt',
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' ist aktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 103,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird gelöscht (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => self::MODULE_NAME . ' ist inaktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 200,
                'icon'    => 'inactive',
                'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ') ' . $version
            ]
        ];

        return json_encode($form);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
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

    public function EnableConfigurationButton(int $ObjectID, string $ButtonName, int $Type): void
    {
        // Variable
        $description = 'ID ' . $ObjectID . ' bearbeiten';
        // Instance
        if ($Type == 1) {
            $description = 'ID ' . $ObjectID . ' konfigurieren';
        }
        $this->UpdateFormField($ButtonName, 'caption', $description);
        $this->UpdateFormField($ButtonName, 'visible', true);
        $this->UpdateFormField($ButtonName, 'enabled', true);
        $this->UpdateFormField($ButtonName, 'objectID', $ObjectID);
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

    private function UpdateStates(): void
    {
        $this->CheckDoorWindowState(false);
        $this->CheckMotionDetectorState();
    }

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Alarm protocol
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if (@!IPS_ObjectExists($id)) {
            $result = false;
            $status = 200;
            $text = 'Bitte das ausgewählte Alarmprotokoll überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
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