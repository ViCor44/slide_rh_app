<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Apenas Admins e RH podem fazer upload
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN && (int)$utilizador_logado['role_id'] !== ROLE_RH) {
    die('Acesso negado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario_id = $_POST['funcionario_id'] ?? null;
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');

    if (!$funcionario_id || !isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
        // Tratar erro
        header('Location: funcionario_detalhe.php?id=' . $funcionario_id . '&error=upload');
        exit;
    }

    $file_tmp_path = $_FILES['documento']['tmp_name'];
    $file_name = $_FILES['documento']['name'];
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    
    // Gerar um nome de ficheiro único para evitar conflitos
    $novo_nome_ficheiro = uniqid('', true) . '.' . $file_extension;
    $caminho_destino = realpath(__DIR__ . '/../storage/documentos_funcionarios') . '/' . $novo_nome_ficheiro;

    if (move_uploaded_file($file_tmp_path, $caminho_destino)) {
        // Inserir na base de dados
        $stmt = $pdo->prepare(
            "INSERT INTO funcionario_documentos (funcionario_id, nome_ficheiro_original, path_ficheiro_armazenado, tipo_documento, uploaded_by_user_id) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$funcionario_id, $file_name, $novo_nome_ficheiro, $tipo_documento, $utilizador_logado['id']]);
    } else {
        // Tratar erro de mover ficheiro
    }
}

// Redirecionar de volta para a página de detalhe
header('Location: funcionario_detalhe.php?id=' . $funcionario_id);
exit;
?>