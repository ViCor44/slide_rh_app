<?php
require_once '../vendor/autoload.php';
use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
echo "A tua chave secreta é: <strong>" . $google2fa->generateSecretKey() . "</strong>";