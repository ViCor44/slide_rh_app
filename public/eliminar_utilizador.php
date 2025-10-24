<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/logger.php';

// Apenas Admins podem eliminar
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN) {
    header('Location: acesso_negado.php');
    exit;
}

// Apenas permitir o método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gerir_utilizadores.php');
    exit;
}

$user_id_a_eliminar = $_POST['id'] ?? null;

// Segurança extra: um admin não se pode eliminar a si mesmo
if ($user_id_a_eliminar && (int)$user_id_a_eliminar !== (int)$utilizador_logado['id']) {
    try {
        // Apagar apenas o utilizador. O funcionário associado não é afetado.
        $stmt = $pdo->prepare("DELETE FROM utilizadores WHERE id = ?");
        $stmt->execute([$user_id_a_eliminar]);
        // REGISTAR O EVENTO DE ELIMINAÇÃO
        log_event(
            $pdo,
            'SECURITY', // Nível de segurança, pois é uma ação destrutiva
            'USER_DELETED',
            "O utilizador ID {$user_id_a_eliminar} foi eliminado.",
            $utilizador_logado['id'],
            ['deleted_user_id' => $user_id_a_eliminar]
        );
    } catch (PDOException $e) {
        // Tratar erro (ex: guardar numa sessão)
    }
}

header('Location: gerir_utilizadores.php');
exit;
?>