<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Apenas Admins e RH podem descarregar
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN && (int)$utilizador_logado['role_id'] !== ROLE_RH) {
    die('Acesso negado.');
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die('ID de documento inválido.');
}
$documento_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM funcionario_documentos WHERE id = ?");
$stmt->execute([$documento_id]);
$documento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$documento) {
    die('Documento não encontrado.');
}

$caminho_ficheiro = realpath(__DIR__ . '/../storage/documentos_funcionarios') . '/' . $documento['path_ficheiro_armazenado'];

if (file_exists($caminho_ficheiro)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($documento['nome_ficheiro_original']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($caminho_ficheiro));
    readfile($caminho_ficheiro);
    exit;
} else {
    die('Ficheiro não encontrado no servidor.');
}
?>