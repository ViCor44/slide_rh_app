<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Apenas Admins e RH podem exportar dados
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN && (int)$utilizador_logado['role_id'] !== ROLE_RH) {
    http_response_code(403);
    die('Acesso Negado.');
}

// 1. Validar o ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die('ID de funcionário inválido.');
}
$funcionario_id = $_GET['id'];

// 2. Ir buscar todos os dados do funcionário (das duas tabelas)
try {
    $stmt = $pdo->prepare("
        SELECT f.*, dp.* FROM funcionarios f
        LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id
        WHERE f.id = ?
    ");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$funcionario) {
        die('Funcionário não encontrado.');
    }
} catch (PDOException $e) {
    die("Erro ao aceder à base de dados: " . $e->getMessage());
}

// 3. Construir o conteúdo do ficheiro de texto
$conteudo = "Relatório de Dados do Funcionário - Gerado em: " . date('d/m/Y H:i:s') . "\n";
$conteudo .= "==========================================================\n\n";

$conteudo .= "INFORMAÇÃO PRINCIPAL\n";
$conteudo .= "----------------------------------------------------------\n";
$conteudo .= "ID Interno: " . $funcionario['id'] . "\n";
$conteudo .= "Nº de Funcionário: " . $funcionario['numero_funcionario'] . "\n";
$conteudo .= "Nome Completo: " . $funcionario['nome_completo'] . "\n";
$conteudo .= "Email Corporativo: " . $funcionario['email_corporativo'] . "\n";
$conteudo .= "Cargo: " . $funcionario['cargo'] . "\n";
$conteudo .= "Departamento: " . $funcionario['departamento'] . "\n";
$conteudo .= "Data de Contratação: " . date('d/m/Y', strtotime($funcionario['data_contratacao'])) . "\n";
$conteudo .= "ID Cartão NFC: " . $funcionario['nfc_card_id'] . "\n\n";

$conteudo .= "DADOS PESSOAIS (CONFIDENCIAL)\n";
$conteudo .= "----------------------------------------------------------\n";
$conteudo .= "Data de Nascimento: " . ($funcionario['data_nascimento'] ? date('d/m/Y', strtotime($funcionario['data_nascimento'])) : 'N/A') . "\n";
$conteudo .= "NIF: " . $funcionario['nif'] . "\n";
$conteudo .= "Nº Segurança Social: " . $funcionario['nss'] . "\n";
$conteudo .= "Nº Cartão de Cidadão: " . $funcionario['cartao_cidadao'] . "\n";
$conteudo .= "Telemóvel: " . $funcionario['telemovel'] . "\n";
$conteudo .= "IBAN: " . $funcionario['iban'] . "\n";
$conteudo .= "Morada: " . str_replace("\r\n", " ", $funcionario['morada_completa']) . "\n";

// 4. Forçar o Download
$nome_ficheiro = "dados_" . strtolower(str_replace(' ', '_', $funcionario['nome_completo'])) . ".txt";

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $nome_ficheiro . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Enviar o conteúdo para o browser
echo $conteudo;
exit;

?>