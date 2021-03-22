<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmzone
 */

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Alarmzone extends IPSModule
{
    //Helper
    use AZ_alarmProtocol;
    use AZ_backupRestore;
    use AZ_blacklist;
    use AZ_controlAlarmZone;
    use AZ_doorWindowSensors;
    use AZ_motionDetectors;
    use AZ_notificationCenter;
    use AZ_remoteControls;

    //Constants
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterTimers();
        $this->RegisterAttributes();
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
        $this->DeactivateTimers();
        $this->SetBuffer('LastAlertingSensor', '[]');
        $this->RegisterMessages();
        $this->ResetBlacklist();
        $this->UpdateStates();
        $this->ValidateConfiguration();
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
                //Remote controls
                $remoteControls = json_decode($this->ReadPropertyString('RemoteControls'), true);
                if (!empty($remoteControls)) {
                    if (array_search($SenderID, array_column($remoteControls, 'ID')) !== false) {
                        //Trigger action
                        $valueChanged = 'false';
                        if ($Data[1]) {
                            $valueChanged = 'true';
                        }
                        $scriptText = 'AZ_TriggerRemoteControlAction(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                //Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        $valueChanged = 'false';
                        if ($Data[1]) {
                            $valueChanged = 'true';
                        }
                        $scriptText = 'AZ_CheckDoorWindowSensorAlerting(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                //Motion detectors
                $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'), true);
                if (!empty($motionDetectors)) {
                    if (array_search($SenderID, array_column($motionDetectors, 'ID')) !== false) {
                        $valueChanged = 'false';
                        if ($Data[1]) {
                            $valueChanged = 'true';
                        }
                        $scriptText = 'AZ_CheckMotionDetectorAlerting(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $result = true;
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
                } else {
                    if (!$var->Use) {
                        $rowColor = '#DFDFDF'; # grey
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
                $formData['elements'][7]['items'][0]['values'][] = [
                    'Use'       => $var->Use,
                    'Name'      => $var->Name,
                    'ID'        => $id,
                    'Trigger'   => $var->Trigger,
                    'Value'     => $var->Value,
                    'Action'    => $var->Action,
                    'ScriptID'  => $var->ScriptID,
                    'rowColor'  => $rowColor];
            }
        }
        //Door and window sensors
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $id = $doorWindowSensor['ID'];
                $rowColor = '';
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $rowColor = '#C0FFC0'; // light green
                    $blackListedSensors = json_decode($this->GetBuffer('Blacklist'), true);
                    $blacklisted = false;
                    if (!empty($blackListedSensors)) {
                        foreach ($blackListedSensors as $blackListedSensor) {
                            if ($blackListedSensor == $doorWindowSensor['ID']) {
                                $rowColor = '#DFDFDF'; # grey
                                $blacklisted = true;
                            }
                        }
                    }
                    if (!$blacklisted) {
                        $type = IPS_GetVariable($id)['VariableType'];
                        $value = $doorWindowSensor['Value'];
                        switch ($doorWindowSensor['Trigger']) {
                            case 2: #on limit drop, once (integer, float)
                            case 3: #on limit drop, every time (integer, float)
                                switch ($type) {
                                    case 1: #integer
                                        if (GetValueInteger($id) < intval($value)) {
                                            $rowColor = '#C0C0FF'; # violett
                                        }
                                        break;

                                    case 2: #float
                                        if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                            $rowColor = '#C0C0FF'; # violett
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
                                        }
                                        break;

                                    case 2: #float
                                        if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                            $rowColor = '#C0C0FF'; # violett
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
                                        }
                                        break;

                                    case 1: #integer
                                        if (GetValueInteger($id) == intval($value)) {
                                            $rowColor = '#C0C0FF'; # violett
                                        }
                                        break;

                                    case 2: #float
                                        if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                            $rowColor = '#C0C0FF'; # violett
                                        }
                                        break;

                                    case 3: #string
                                        if (GetValueString($id) == (string) $value) {
                                            $rowColor = '#C0C0FF'; # violett
                                        }
                                        break;

                                }
                                break;

                        }
                        if (!$doorWindowSensor['Use']) {
                            $rowColor = '#DFDFDF'; # grey
                        }
                    }
                } else {
                    if ($doorWindowSensor['Use']) {
                        $rowColor = '#FFC0C0'; # red
                        $result = false;
                    }
                }
                $formData['elements'][8]['items'][0]['values'][] = [
                    'Use'                           => $doorWindowSensor['Use'],
                    'Name'                          => $doorWindowSensor['Name'],
                    'ID'                            => $doorWindowSensor['ID'],
                    'Trigger'                       => $doorWindowSensor['Trigger'],
                    'Value'                         => $doorWindowSensor['Value'],
                    'FullProtectionModeActive'      => $doorWindowSensor['FullProtectionModeActive'],
                    'HullProtectionModeActive'      => $doorWindowSensor['HullProtectionModeActive'],
                    'PartialProtectionModeActive'   => $doorWindowSensor['PartialProtectionModeActive'],
                    'UseNotification'               => $doorWindowSensor['UseNotification'],
                    'UseAlarmSiren'                 => $doorWindowSensor['UseAlarmSiren'],
                    'UseAlarmLight'                 => $doorWindowSensor['UseAlarmLight'],
                    'UseAlarmCall'                  => $doorWindowSensor['UseAlarmCall'],
                    'rowColor'                      => $rowColor];
            }
        }
        //Properties
        $properties = [];
        array_push($properties, ['name' => 'MotionDetectors', 'position' => 9]);
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
                            $rowColor = '#FFC0C0'; # red
                            $result = false;
                        } else {
                            if (!$var->Use) {
                                $rowColor = '#DFDFDF'; # grey
                            }
                        }
                        # motion detectors
                        $formData['elements'][$propertyPosition]['items'][0]['values'][] = [
                            'Use'                           => $var->Use,
                            'Name'                          => $var->Name,
                            'ID'                            => $id,
                            'Trigger'                       => $var->Trigger,
                            'Value'                         => $var->Value,
                            'FullProtectionModeActive'      => $var->FullProtectionModeActive,
                            'HullProtectionModeActive'      => $var->HullProtectionModeActive,
                            'PartialProtectionModeActive'   => $var->PartialProtectionModeActive,
                            'UseNotification'               => $var->UseNotification,
                            'UseAlarmSiren'                 => $var->UseAlarmSiren,
                            'UseAlarmLight'                 => $var->UseAlarmLight,
                            'UseAlarmCall'                  => $var->UseAlarmCall,
                            'rowColor'                      => $rowColor];
                    }
                }
            }
        }
        //Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = ''; # '#C0FFC0' light green
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

    /**
     * Assigns the profile to the sensors.
     */
    public function AssignProfiles(): void
    {
        //Door and window sensors
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $id = $doorWindowSensor->ID;
                if ($id != 0 && IPS_ObjectExists($id)) {
                    //Check object
                    $object = IPS_GetObject($id)['ObjectType'];
                    //0: Category, 1: Instance, 2: Variable, 3: Script, 4: Event, 5: Media, 6: Link)
                    if ($object == 2) {
                        //Get variable type
                        $variable = IPS_GetVariable($id)['VariableType'];
                        $profile = $doorWindowSensor->Value;
                        switch ($variable) {
                            //0: Boolean, 1: Integer, 2: Float, 3: String
                            case 0:
                                switch ($profile) {
                                    //0: Reversed, 1: Standard
                                    case 0:
                                        $profileName = 'AZ.DoorWindowSensor.Bool.Reversed';
                                        break;

                                    case 1:
                                        $profileName = 'AZ.DoorWindowSensor.Bool';
                                        break;

                                }
                                break;

                            case 1:
                                switch ($profile) {
                                    //0: Reversed, 1: Standard
                                    case 0:
                                        $profileName = 'AZ.DoorWindowSensor.Integer.Reversed';
                                        break;

                                    case 1:
                                        $profileName = 'AZ.DoorWindowSensor.Integer';
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
        //Motion detectors
        $motionDetectors = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($motionDetectors)) {
            foreach ($motionDetectors as $motionDetector) {
                $id = $motionDetector->ID;
                if ($id != 0 && IPS_ObjectExists($id)) {
                    $object = IPS_GetObject($id)['ObjectType'];
                    //Check if object is a variable
                    if ($object == 2) {
                        //Get variable type
                        $variable = IPS_GetVariable($id)['VariableType'];
                        $profile = $motionDetector->Value;
                        switch ($variable) {
                            //0: Boolean, 1: Integer, 2: Float, 3: String
                            case 0:
                                switch ($profile) {
                                    //0: Reversed, 1: Standard
                                    case 0:
                                        //not necessary yet
                                        break;

                                    case 1:
                                        $profileName = 'AZ.MotionDetector.Bool';
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
        //Door and window sensors
        $categoryID = @IPS_GetObjectIDByIdent('DoorWindowSensorsCategory', $this->InstanceID);
        //Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($variables)) {
            if ($categoryID === false) {
                $categoryID = IPS_CreateCategory();
            }
            IPS_SetIdent($categoryID, 'DoorWindowSensorsCategory');
            IPS_SetName($categoryID, 'Tür- und Fenstersensoren');
            IPS_SetParent($categoryID, $this->InstanceID);
            IPS_SetIcon($categoryID, 'Window');
            IPS_SetPosition($categoryID, 200);
            IPS_SetHidden($categoryID, true);
            //Get variables
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
            //Sort array alphabetically by device name
            sort($targetIDs);
            //Get all existing links
            $existingTargetIDs = [];
            $childrenIDs = IPS_GetChildrenIDs($categoryID);
            $i = 0;
            foreach ($childrenIDs as $childID) {
                //Check if children is a link
                $objectType = IPS_GetObject($childID)['ObjectType'];
                if ($objectType == 6) {
                    //Get target id
                    $existingTargetID = IPS_GetLink($childID)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $childID, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
            //Delete dead links
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
            //Create new links
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
            //Edit existing links
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
        //Motion Detectors
        $categoryID = @IPS_GetObjectIDByIdent('MotionDetectorsCategory', $this->InstanceID);
        //Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($variables)) {
            if ($categoryID === false) {
                $categoryID = IPS_CreateCategory();
            }
            IPS_SetIdent($categoryID, 'MotionDetectorsCategory');
            IPS_SetName($categoryID, 'Bewegungsmelder');
            IPS_SetParent($categoryID, $this->InstanceID);
            IPS_SetIcon($categoryID, 'Motion');
            IPS_SetPosition($categoryID, 210);
            IPS_SetHidden($categoryID, true);
            //Get variables
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
            //Sort array alphabetically by device name
            sort($targetIDs);
            // Get all existing links
            $existingTargetIDs = [];
            $childrenIDs = IPS_GetChildrenIDs($categoryID);
            $i = 0;
            foreach ($childrenIDs as $childID) {
                //Check if children is a link
                $objectType = IPS_GetObject($childID)['ObjectType'];
                if ($objectType == 6) {
                    //Get target id
                    $existingTargetID = IPS_GetLink($childID)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $childID, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
            //Delete dead links
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
            //Create new links
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
            //Edit existing links
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
        echo 'Die Verknüpfungen wurde erfolgreich angelegt!';
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
        $this->RegisterPropertyBoolean('EnableAlarmZoneName', true);
        $this->RegisterPropertyBoolean('EnableFullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableHullProtectionMode', true);
        $this->RegisterPropertyBoolean('EnablePartialProtectionMode', true);
        $this->RegisterPropertyBoolean('EnableAlarmZoneState', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableMotionDetectorState', true);
        $this->RegisterPropertyBoolean('EnableAlarmState', true);
        $this->RegisterPropertyBoolean('EnableAlarmSirenState', true);
        $this->RegisterPropertyBoolean('EnableAlarmLightState', true);
        $this->RegisterPropertyBoolean('EnableAlarmCallState', true);
        $this->RegisterPropertyBoolean('EnableAlertingSensor', true);
        //Descriptions
        $this->RegisterPropertyString('SystemName', 'Alarmzone');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyString('AlarmZoneName', '');
        $this->RegisterPropertyString('FullProtectionName', 'Vollschutz');
        $this->RegisterPropertyString('HullProtectionName', 'Hüllschutz');
        $this->RegisterPropertyString('PartialProtectionName', 'Teilschutz');
        //Activation check
        $this->RegisterPropertyBoolean('CheckFullProtectionModeActivation', false);
        $this->RegisterPropertyBoolean('CheckHullProtectionModeActivation', false);
        $this->RegisterPropertyBoolean('CheckPartialProtectionModeActivation', false);
        //Activation delay
        $this->RegisterPropertyInteger('FullProtectionModeActivationDelay', 0);
        $this->RegisterPropertyInteger('HullProtectionModeActivationDelay', 0);
        $this->RegisterPropertyInteger('PartialProtectionModeActivationDelay', 0);
        //Alerting delay
        $this->RegisterPropertyInteger('AlertingDelayFullProtectionMode', 0);
        $this->RegisterPropertyInteger('AlertingDelayHullProtectionMode', 0);
        $this->RegisterPropertyInteger('AlertingDelayPartialProtectionMode', 0);
        //Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
        //Notification center
        $this->RegisterPropertyInteger('NotificationCenter', 0);
        $this->RegisterPropertyString('AlarmZoneDisarmedSymbol', json_decode('"\ud83d\udfe2"'));
        $this->RegisterPropertyString('AlarmZoneDelayedArmedSymbol', json_decode('"\ud83d\udd57"'));
        $this->RegisterPropertyString('FullProtectionModeArmedSymbol', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyString('HullProtectionModeArmedSymbol', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyString('PartialProtectionModeArmedSymbol', json_decode('"\ud83d\udd34"'));
        $this->RegisterPropertyString('PreAlarmSymbol', json_decode('"\u26a0\ufe0f"'));
        $this->RegisterPropertyString('AlarmSymbol', json_decode('"\u2757"'));
        $this->RegisterPropertyString('AlarmZoneSystemFailure', json_decode('"\u26a0\ufe0f"'));
        //Remote controls
        $this->RegisterPropertyString('RemoteControls', '[]');
        //Alarm sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyString('MotionDetectors', '[]');
    }

    private function CreateProfiles(): void
    {
        //Alarm zone state
        $profile = 'AZ.' . $this->InstanceID . '.AlarmZoneState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Unscharf', 'IPS', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Scharf', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
        //Alarm state
        $profile = 'AZ.' . $this->InstanceID . '.AlarmState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Warning', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Alert', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Voralarm', 'Clock', 0xFFFF00);
        //Door and window state
        $profile = 'AZ.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        //Motion detector state
        $profile = 'AZ.' . $this->InstanceID . '.MotionDetectorState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Motion');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', '', 0xFF0000);
        // Alarm siren
        $profile = 'AZ.' . $this->InstanceID . '.AlarmSirenStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Alert');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        // Alarm light
        $profile = 'AZ.' . $this->InstanceID . '.AlarmLightStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Bulb');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);
        // Alarm call
        $profile = 'AZ.' . $this->InstanceID . '.AlarmCallStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Mobile');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', '', 0xFF0000);

        ########## HomeMatic & Homematic IP devices

        //Door and window sensors
        $profile = 'AZ.DoorWindowSensor.Bool';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $profile = 'AZ.DoorWindowSensor.Bool.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geöffnet', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geschlossen', '', 0x00FF00);
        $profile = 'AZ.DoorWindowSensor.Integer';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', '', 0xFF0000);
        $profile = 'AZ.DoorWindowSensor.Integer.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileAssociation($profile, 0, 'Geöffnet', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geschlossen', '', 0x00FF00);
        //Motion detectors
        $profile = 'AZ.MotionDetector.Bool';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Untätig', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Bewegung erkannt', 'Motion', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['AlarmZoneState', 'AlarmState', 'DoorWindowState', 'MotionDetectorState', 'AlarmSirenStatus', 'AlarmLightStatus', 'AlarmCallStatus'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'AZ.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('StartActivation', 0, 'AZ_StartActivation(' . $this->InstanceID . ');');
        $this->RegisterTimer('SetAlarmState', 0, 'AZ_SetAlarmState(' . $this->InstanceID . ');');
    }

    private function DeactivateTimers(): void
    {
        $this->SetTimerInterval('StartActivation', 0);
        $this->SetTimerInterval('SetAlarmState', 0);
    }

    private function RegisterAttributes(): void
    {
        $this->RegisterAttributeBoolean('PreAlarm', false);
    }

    private function RegisterVariables(): void
    {
        //Location
        $this->RegisterVariableString('Location', 'Standortbezeichnung', '', 10);
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        IPS_SetIcon($this->GetIDForIdent('Location'), 'IPS');
        //Alarm zone name
        $this->RegisterVariableString('AlarmZoneName', 'Alarmzonenbezeichnung', '', 20);
        $this->SetValue('AlarmZoneName', $this->ReadPropertyString('AlarmZoneName'));
        IPS_SetIcon($this->GetIDForIdent('AlarmZoneName'), 'IPS');
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
        //Alarm zone state
        $profile = 'AZ.' . $this->InstanceID . '.AlarmZoneState';
        $this->RegisterVariableInteger('AlarmZoneState', 'Alarmzone', $profile, 60);
        //Door and window state
        $profile = 'AZ.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Türen und Fenster', $profile, 70);
        //Motion detector state
        $profile = 'AZ.' . $this->InstanceID . '.MotionDetectorState';
        $this->RegisterVariableBoolean('MotionDetectorState', 'Bewegungsmelder', $profile, 80);
        //Alarm state
        $profile = 'AZ.' . $this->InstanceID . '.AlarmState';
        $this->RegisterVariableInteger('AlarmState', 'Alarm', $profile, 90);
        //Alarm siren
        $profile = 'AZ.' . $this->InstanceID . '.AlarmSirenStatus';
        $this->RegisterVariableBoolean('AlarmSiren', 'Alarmsirene', $profile, 100);
        //Alarm light
        $profile = 'AZ.' . $this->InstanceID . '.AlarmLightStatus';
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', $profile, 110);
        //Alarm call
        $profile = 'AZ.' . $this->InstanceID . '.AlarmCallStatus';
        $this->RegisterVariableBoolean('AlarmCall', 'Alarmanruf', $profile, 120);
        //Alerting sensor
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Alarmsensor', '', 130);
        $this->SetValue('AlertingSensor', 'OK');
        IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Eyes');
    }

    private function SetOptions(): void
    {
        //Location
        $this->SetValue('Location', $this->ReadPropertyString('Location'));
        IPS_SetHidden($this->GetIDForIdent('Location'), !$this->ReadPropertyBoolean('EnableLocation'));
        //Alarm zone name
        $this->SetValue('AlarmZoneName', $this->ReadPropertyString('AlarmZoneName'));
        IPS_SetHidden($this->GetIDForIdent('AlarmZoneName'), !$this->ReadPropertyBoolean('EnableAlarmZoneName'));
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
        //Alarm zone state
        IPS_SetHidden($this->GetIDForIdent('AlarmZoneState'), !$this->ReadPropertyBoolean('EnableAlarmZoneState'));
        //Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));
        //Motion detector state
        IPS_SetHidden($this->GetIDForIdent('MotionDetectorState'), !$this->ReadPropertyBoolean('EnableMotionDetectorState'));
        //Alarm state
        IPS_SetHidden($this->GetIDForIdent('AlarmState'), !$this->ReadPropertyBoolean('EnableAlarmState'));
        //Alarm siren state
        IPS_SetHidden($this->GetIDForIdent('AlarmSiren'), !$this->ReadPropertyBoolean('EnableAlarmSirenState'));
        //Alarm light state
        IPS_SetHidden($this->GetIDForIdent('AlarmLight'), !$this->ReadPropertyBoolean('EnableAlarmLightState'));
        //Alarm call state
        IPS_SetHidden($this->GetIDForIdent('AlarmCall'), !$this->ReadPropertyBoolean('EnableAlarmCallState'));
        //Alerting sensor
        IPS_SetHidden($this->GetIDForIdent('AlertingSensor'), !$this->ReadPropertyBoolean('EnableAlertingSensor'));
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
        //Register
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
        //Door and window sensors
        $variables = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
        //Motion Detectors
        $variables = json_decode($this->ReadPropertyString('MotionDetectors'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
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
        //Notification center
        $id = $this->ReadPropertyInteger('NotificationCenter');
        if (@!IPS_ObjectExists($id)) {
            $status = 200;
            $text = 'Bitte die ausgewählte Benachrichtigungszentrale überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        //Alarm protocol
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if (@!IPS_ObjectExists($id)) {
            $status = 200;
            $text = 'Bitte das ausgewählte Alarmprotokoll überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
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