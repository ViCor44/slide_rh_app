<?php
require_once '../config/db.php';

// Usar um caminho absoluto para o placeholder para ser mais robusto
$placeholder = realpath(__DIR__ . '/../storage/placeholder.png');

try {
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        throw new Exception('ID inválido.');
    }
    $funcionario_id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT foto_path FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['foto_path'])) {
        $caminho_foto = realpath(__DIR__ . '/../storage/fotos_funcionarios') . '/' . $result['foto_path'];
        
        if (file_exists($caminho_foto) && is_readable($caminho_foto)) {
            $mime_type = mime_content_type($caminho_foto);
            header("Content-Type: " . $mime_type);
            readfile($caminho_foto);
            exit;
        }
    }
    
    // Se tudo o resto falhar, mostra o placeholder
    if ($placeholder && is_readable($placeholder)) {
        header("Content-Type: image/png");
        readfile($placeholder);
        exit;
    } else {
        // Fallback final se nem o placeholder existir
        http_response_code(404);
        die('Imagem não encontrada.');
    }

} catch (Exception $e) {
    // Em caso de qualquer erro, tenta mostrar o placeholder
    if ($placeholder && is_readable($placeholder)) {
        header("Content-Type: image/png");
        readfile($placeholder);
        exit;
    } else {
        http_response_code(404);
        die('Imagem não encontrada devido a erro.');
    }
}