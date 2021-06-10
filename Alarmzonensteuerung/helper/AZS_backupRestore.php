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

trait AZS_backupRestore
{
    public function CreateBackup(int $BackupCategory): void
    {
        if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] == 102) {
            $name = 'Konfiguration (' . IPS_GetName($this->InstanceID) . ' #' . $this->InstanceID . ') ' . date('d.m.Y H:i:s');
            $config = json_decode(IPS_GetConfiguration($this->InstanceID), true);
            $config['AlarmZones'] = json_decode($config['AlarmZones'], true);
            $config['FullProtectionMode'] = json_decode($config['FullProtectionMode'], true);
            $config['HullProtectionMode'] = json_decode($config['HullProtectionMode'], true);
            $config['PartialProtectionMode'] = json_decode($config['PartialProtectionMode'], true);
            $config['SystemState'] = json_decode($config['SystemState'], true);
            $config['AlarmState'] = json_decode($config['AlarmState'], true);
            $config['AlertingSensor'] = json_decode($config['AlertingSensor'], true);
            $config['DoorWindowState'] = json_decode($config['DoorWindowState'], true);
            $config['MotionDetectorState'] = json_decode($config['MotionDetectorState'], true);
            $config['AlarmSiren'] = json_decode($config['AlarmSiren'], true);
            $config['AlarmLight'] = json_decode($config['AlarmLight'], true);
            $config['AlarmCall'] = json_decode($config['AlarmCall'], true);
            $json_string = json_encode($config, JSON_HEX_APOS | JSON_PRETTY_PRINT);
            $content = "<?php\n// Backup " . date('d.m.Y, H:i:s') . "\n// ID " . $this->InstanceID . "\n$" . "config = '" . $json_string . "';";
            $backupScript = IPS_CreateScript(0);
            IPS_SetParent($backupScript, $BackupCategory);
            IPS_SetName($backupScript, $name);
            IPS_SetHidden($backupScript, true);
            IPS_SetScriptContent($backupScript, $content);
            echo 'Die Konfiguration wurde erfolgreich gesichert!';
        }
    }

    public function RestoreConfiguration(int $ConfigurationScript): void
    {
        if ($ConfigurationScript != 0 && IPS_ObjectExists($ConfigurationScript)) {
            $object = IPS_GetObject($ConfigurationScript);
            if ($object['ObjectType'] == 3) {
                $content = IPS_GetScriptContent($ConfigurationScript);
                preg_match_all('/\'([^;]+)\'/', $content, $matches);
                $config = json_decode($matches[1][0], true);
                $config['AlarmZones'] = json_encode($config['AlarmZones']);
                $config['FullProtectionMode'] = json_encode($config['FullProtectionMode']);
                $config['HullProtectionMode'] = json_encode($config['HullProtectionMode']);
                $config['PartialProtectionMode'] = json_encode($config['PartialProtectionMode']);
                $config['SystemState'] = json_encode($config['SystemState']);
                $config['AlarmState'] = json_encode($config['AlarmState']);
                $config['AlertingSensor'] = json_encode($config['AlertingSensor']);
                $config['DoorWindowState'] = json_encode($config['DoorWindowState']);
                $config['MotionDetectorState'] = json_encode($config['MotionDetectorState']);
                $config['AlarmSiren'] = json_encode($config['AlarmSiren']);
                $config['AlarmLight'] = json_encode($config['AlarmLight']);
                $config['AlarmCall'] = json_encode($config['AlarmCall']);
                IPS_SetConfiguration($this->InstanceID, json_encode($config));
                if (IPS_HasChanges($this->InstanceID)) {
                    IPS_ApplyChanges($this->InstanceID);
                }
            }
            echo 'Die Konfiguration wurde erfolgreich wiederhergestellt!';
        }
    }
}