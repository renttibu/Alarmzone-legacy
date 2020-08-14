<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class AlarmzoneValidationTest extends TestCaseSymconValidation
{
    public function testValidateAlarmzone(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateAlarmzoneModule(): void
    {
        $this->validateModule(__DIR__ . '/../Alarmzone');
    }
}