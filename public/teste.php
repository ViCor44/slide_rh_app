<?php
// Forçar a exibição de todos os erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste de Sanidade do PHP</h1>";

// Recriar as variáveis exatamente como no teu diagnóstico
$role_id_from_session = "4";
$funcionario_id_from_session = "4";
$funcionario_id_from_url = "4";
const ROLE_FUNCIONARIO_constant = 4;

echo "<p>Valor de \$role_id_from_session: '$role_id_from_session' (tipo: " . gettype($role_id_from_session) . ")</p>";
echo "<p>Valor de \$funcionario_id_from_session: '$funcionario_id_from_session' (tipo: " . gettype($funcionario_id_from_session) . ")</p>";
echo "<p>Valor de \$funcionario_id_from_url: '$funcionario_id_from_url' (tipo: " . gettype($funcionario_id_from_url) . ")</p>";
echo "<p>Valor da constante ROLE_FUNCIONARIO: " . ROLE_FUNCIONARIO_constant . " (tipo: " . gettype(ROLE_FUNCIONARIO_constant) . ")</p>";

echo "<hr>";

echo "<h2>A executar a condição exata:</h2>";
echo "<pre style='background: #eee; padding: 10px; border: 1px solid #999;'>if ( (int)\$role_id_from_session === ROLE_FUNCIONARIO_constant && (int)\$funcionario_id_from_session !== (int)\$funcionario_id_from_url )</pre>";

if (
    (int)$role_id_from_session === ROLE_FUNCIONARIO_constant &&
    (int)$funcionario_id_from_session !== (int)$funcionario_id_from_url
) {
    echo "<h2 style='color: red;'>RESULTADO: ACESSO NEGADO (Isto indica um problema no PHP!)</h2>";
} else {
    echo "<h2 style='color: green;'>RESULTADO: ACESSO PERMITIDO (Isto é o que deveria acontecer!)</h2>";
}

echo "<hr>";
echo "<h2>Verificação individual da segunda condição:</h2>";
$val1 = (int)$funcionario_id_from_session;
$val2 = (int)$funcionario_id_from_url;
$result = ($val1 !== $val2);

echo "<p>A comparar (int)'$val1' !== (int)'$val2'</p>";
echo "O resultado da comparação (o valor de \$result) é: ";
var_dump($result); // Deveria ser bool(false)

?>