<?php
// gerador_hash.php
$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Copia e cola este hash na tua base de dados:<br><br>";
echo "<input type='text' value='" . htmlspecialchars($hash) . "' size='80' readonly style='padding: 5px; font-family: monospace;' onclick='this.select()'>";
?>