<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone/tree/master/Alarmzonensteuerung
 */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Alarmzonensteuerung extends IPSModule
{
    // Helper
    use AZS_backupRestore;
    use AZS_controlAlarmZones;
    use AZS_updateStates;

    // Constants
    private const LIBRARY_GUID = '{8464371D-1C4E-B070-9884-82DB73545FFA}';
    private const MODULE_NAME = 'Alarmzonensteuerung';
    private const MODULE_PREFIX = 'UBAZS';
    private const ALARMZONE_MODULE_GUID = '{CA80C88F-1CC4-8865-959B-3215FC5B1320}';
    private const ALARMZONE_MODULE_PREFIX = 'UBAZ';

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
        $this->RegisterPropertyBoolean('EnableAlertingSensor', true);
        $this->RegisterPropertyBoolean('EnableAlarmSirenState', true);
        $this->RegisterPropertyBoolean('EnableAlarmLightState', true);
        $this->RegisterPropertyBoolean('EnableAlarmCallState', true);
        // Designation
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
        $this->RegisterVariableBoolean('FullProtectionMode', $name, '~Switch', 20);
        $this->EnableAction('FullProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('FullProtectionMode'), 'Basement');
        }
        // Hull protection mode
        $id = @$this->GetIDForIdent('HullProtectionMode');
        $name = $this->ReadPropertyString('HullProtectionName');
        $this->RegisterVariableBoolean('HullProtectionMode', $name, '~Switch', 30);
        $this->EnableAction('HullProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('HullProtectionMode'), 'GroundFloor');
        }
        // Partial protection mode
        $id = @$this->GetIDForIdent('PartialProtectionName');
        $name = $this->ReadPropertyString('PartialProtectionName');
        $this->RegisterVariableBoolean('PartialProtectionMode', $name, '~Switch', 40);
        $this->EnableAction('PartialProtectionMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('PartialProtectionMode'), 'Moon');
        }
        // System state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.SystemState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'IPS', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
        $this->RegisterVariableInteger('SystemState', 'Systemstatus', $profile, 50);
        // Door and window state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen- und Fenster', $profile, 60);
        // Motion detector state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 70);
        // Alarm state
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Warning', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        $this->RegisterVariableInteger('AlarmState', 'Alarmstatus', $profile, 80);
        // Alarm siren
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmSirenStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Alert');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', $profile, 90);
        // Alarm light
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmLightStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Bulb');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', $profile, 100);
        // Alarm call
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmCallStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Mobile');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        $this->RegisterVariableBoolean('AlarmCall', 'Alarmanruf', $profile, 110);
        // Alerting sensor
        $id = @$this->GetIDForIdent('AlertingSensor');
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Alarmsensor', '', 120);
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

        $this->WriteAttributeBoolean('DisableUpdateMode', false);

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

        if ($this->ValidateConfiguration()) {
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
        }

        $this->UpdateStates();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $profiles = ['SystemState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState', 'AlarmSirenStatus', 'AlarmLightStatus', 'AlarmCallStatus'];
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
                    'AlarmSiren',
                    'AlarmLight',
                    'AlarmCall'];
                foreach ($properties as $property) {
                    $variables = json_decode($this->ReadPropertyString($property), true);
                    if (!empty($variables)) {
                        if (array_search($SenderID, array_column($variables, 'ID')) !== false) {
                            $scriptText = self::MODULE_PREFIX . '_Update' . $property . '(' . $this->InstanceID . ');';
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
                    'name'    => 'EnableSystemState',
                    'caption' => 'Systemstatus'
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
                    'name'    => 'Location',
                    'caption' => 'Standortbezeichnung (z.B. Musterstraße 1)',
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

        ########## Alarm zones

        $alarmZoneValues = [];
        foreach (json_decode($this->ReadPropertyString('AlarmZones')) as $alarmZone) {
            $rowColor = '#FFC0C0'; # red
            $id = $alarmZone->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
            }
            $alarmZoneValues[] = ['rowColor' => $rowColor];
        }

        ##### Alarm zones panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmzonen',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'AlarmZones',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'columns'  => [
                        [
                            'name'    => 'ID',
                            'caption' => 'Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $AlarmZones["ID"], "AlarmZonesConfigurationButton", 1);',
                            'edit'    => [
                                'type'     => 'SelectModule',
                                'moduleID' => self::ALARMZONE_MODULE_GUID
                            ]
                        ],
                        [
                            'name'    => 'Description',
                            'caption' => 'Bezeichnung',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $alarmZoneValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'AlarmZonesConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Full protection mode

        $fullProtectionModeValues = [];
        foreach (json_decode($this->ReadPropertyString('FullProtectionMode')) as $fullProtectionMode) {
            $rowColor = '#FFC0C0'; # red
            $id = $fullProtectionMode->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($fullProtectionMode->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $fullProtectionModeValues[] = ['rowColor' => $rowColor];
        }

        ##### Full protection mode panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Vollschutz',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'FullProtectionMode',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $FullProtectionMode["ID"], "FullProtectionModeConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $fullProtectionModeValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'FullProtectionModeConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Hull protection mode

        $hullProtectionModeValues = [];
        foreach (json_decode($this->ReadPropertyString('HullProtectionMode')) as $hullProtectionMode) {
            $rowColor = '#FFC0C0'; # red
            $id = $hullProtectionMode->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($hullProtectionMode->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $hullProtectionModeValues[] = ['rowColor' => $rowColor];
        }

        ##### Hull protection mode panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Hüllschutz',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'HullProtectionMode',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $HullProtectionMode["ID"], "HullProtectionModeConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $hullProtectionModeValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'HullProtectionModeConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Partial protection mode

        $partialProtectionModeValues = [];
        foreach (json_decode($this->ReadPropertyString('PartialProtectionMode')) as $partialProtectionMode) {
            $rowColor = '#FFC0C0'; # red
            $id = $partialProtectionMode->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($partialProtectionMode->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $partialProtectionModeValues[] = ['rowColor' => $rowColor];
        }

        ##### Partial protection mode panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Teilschutz',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'PartialProtectionMode',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $PartialProtectionMode["ID"], "PartialProtectionModeConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $partialProtectionModeValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'PartialProtectionModeConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## System state

        $systemStateValues = [];
        foreach (json_decode($this->ReadPropertyString('SystemState')) as $systemState) {
            $rowColor = '#FFC0C0'; # red
            $id = $systemState->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($systemState->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $systemStateValues[] = ['rowColor' => $rowColor];
        }

        ##### System state panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Systemstatus',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'SystemState',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $SystemState["ID"], "SystemStateConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $systemStateValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'SystemStateConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Alarm state

        $alarmStateValues = [];
        foreach (json_decode($this->ReadPropertyString('AlarmState')) as $alarmState) {
            $rowColor = '#FFC0C0'; # red
            $id = $alarmState->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($alarmState->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $alarmStateValues[] = ['rowColor' => $rowColor];
        }

        ##### Alarm state panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmstatus',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'AlarmState',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $AlarmState["ID"], "AlarmStateConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $alarmStateValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'AlarmStateConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Alerting sensors

        $alertingSensorValues = [];
        foreach (json_decode($this->ReadPropertyString('AlertingSensor')) as $alertingSensor) {
            $rowColor = '#FFC0C0'; # red
            $id = $alertingSensor->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($alertingSensor->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $alertingSensorValues[] = ['rowColor' => $rowColor];
        }

        ##### Alerting sensors panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Auslösender Alarmsensor',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'AlertingSensor',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $AlertingSensor["ID"], "AlertingSensorConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $alertingSensorValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'AlertingSensorConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Door and window states

        $doorWindowStateValues = [];
        foreach (json_decode($this->ReadPropertyString('DoorWindowState')) as $doorWindowState) {
            $rowColor = '#FFC0C0'; # red
            $id = $doorWindowState->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($doorWindowState->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $doorWindowStateValues[] = ['rowColor' => $rowColor];
        }

        ##### Door and window states panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Tür- und Fensterstatus',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'DoorWindowState',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $DoorWindowState["ID"], "DoorWindowStateConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $doorWindowStateValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'DoorWindowStateConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Motion detector states

        $motionDetectorStateValues = [];
        foreach (json_decode($this->ReadPropertyString('MotionDetectorState')) as $motionDetectorState) {
            $rowColor = '#FFC0C0'; # red
            $id = $motionDetectorState->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($motionDetectorState->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $motionDetectorStateValues[] = ['rowColor' => $rowColor];
        }

        ##### Motion detector states panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Bewegungsmelderstatus',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'MotionDetectorState',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $MotionDetectorState["ID"], "MotionDetectorStateConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $motionDetectorStateValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'MotionDetectorStateConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Alarm sirens

        $alarmSirenValues = [];
        foreach (json_decode($this->ReadPropertyString('AlarmSiren')) as $alarmSiren) {
            $rowColor = '#FFC0C0'; # red
            $id = $alarmSiren->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($alarmSiren->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $alarmSirenValues[] = ['rowColor' => $rowColor];
        }

        ##### Alarm sirens panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmsirene',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'AlarmSiren',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $AlarmSiren["ID"], "AlarmSirenConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $alarmSirenValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'AlarmSirenConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Alarm lights

        $alarmLightValues = [];
        foreach (json_decode($this->ReadPropertyString('AlarmLight')) as $alarmLight) {
            $rowColor = '#FFC0C0'; # red
            $id = $alarmLight->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($alarmLight->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $alarmLightValues[] = ['rowColor' => $rowColor];
        }

        ##### Alarm lights panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmbeleuchtung',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'AlarmLight',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $AlarmLight["ID"], "AlarmLightConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $alarmLightValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'AlarmLightConfigurationButton',
                    'enabled'  => false,
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        ########## Alarm calls

        $alarmCallValues = [];
        foreach (json_decode($this->ReadPropertyString('AlarmCall')) as $alarmCall) {
            $rowColor = '#FFC0C0'; # red
            $id = $alarmCall->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#DFDFDF'; # grey
                if ($alarmCall->Use) {
                    $rowColor = '#C0FFC0'; # light green
                }
            }
            $alarmCallValues[] = ['rowColor' => $rowColor];
        }

        ##### Alarm calls panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmanruf',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'AlarmCall',
                    'rowCount' => 5,
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
                            ]
                        ],
                        [
                            'name'    => 'ID',
                            'caption' => 'Variable der Alarmzone',
                            'width'   => '450px',
                            'add'     => 0,
                            'onClick' => self::MODULE_PREFIX . '_EnableConfigurationButton($id, $AlarmCall["ID"], "AlarmCallConfigurationButton", 0);',
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Description',
                            'width'   => 'auto',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $alarmCallValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Bearbeiten',
                    'name'     => 'AlarmCallConfigurationButton',
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

        ##### Alarm zone

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmzonen',
            'items'   => [
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Variablen ermitteln',
                    'popup'   => [
                        'caption' => 'Variablen wirklich automatisch ermitteln?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Variablen ermitteln',
                                'onClick' => self::MODULE_PREFIX . '_DetermineAlarmZoneVariables($id);'
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