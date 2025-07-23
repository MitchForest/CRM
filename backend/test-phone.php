<?php
require_once '/var/www/html/custom/api/dto/Base/BaseDTO.php';
require_once '/var/www/html/custom/api/dto/ContactDTO.php';

use Api\DTO\ContactDTO;

$dto = new ContactDTO();
$dto->setFirstName('John')->setLastName('Doe');
$dto->setPhoneMobile('123');

echo "Validating phone '123':\n";
$isValid = $dto->validate();
echo "Is valid: " . ($isValid ? 'true' : 'false') . "\n";
echo "Errors: " . json_encode($dto->getErrors()) . "\n";

// Test digit count
$phone = '123';
$digitCount = preg_match_all('/\d/', $phone, $matches);
echo "Digit count in '123': $digitCount\n";