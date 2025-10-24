<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/logger.php'; // Incluir a nossa ferramenta de log

// Apenas Admins e RH podem eliminar funcionários
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN && (int)$utilizador_logado['role_id'] !== ROLE_RH) {
    header('Location: acesso_negado.php');
    exit;
}

// Apenas permitir o método POST por segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar_funcionarios.php');
    exit;
}

$funcionario_id_a_eliminar = $_POST['id'] ?? null;

if ($funcionario_id_a_eliminar) {
    $pdo->beginTransaction();
    try {
        // 1. Primeiro, ir buscar os dados do funcionário antes de o apagar (para o log e para a foto)
        $stmt = $pdo->prepare("SELECT nome_completo, foto_path FROM funcionarios WHERE id = ?");
        $stmt->execute([$funcionario_id_a_eliminar]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($funcionario) {
            // 2. Apagar o funcionário da base de dados
            // A regra "ON DELETE CASCADE" vai tratar da tabela `funcionarios_dados_pessoais`.
            // A regra "ON DELETE SET NULL" vai pôr o `funcionario_id = NULL` na tabela `utilizadores`.
            $delete_stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id = ?");
            $delete_stmt->execute([$funcionario_id_a_eliminar]);

            // 3. Apagar o ficheiro da foto do disco, se existir
            if (!empty($funcionario['foto_path'])) {
                $caminho_foto = realpath(__DIR__ . '/../storage/fotos_funcionarios') . '/' . $funcionario['foto_path'];
                if (file_exists($caminho_foto)) {
                    unlink($caminho_foto); // Função do PHP para apagar ficheiros
                }
            }

            // 4. REGISTAR O EVENTO NO LOG
            log_event(
                $pdo,
                'SECURITY', // Nível de segurança, pois é uma ação destrutiva
                'EMPLOYEE_DELETED',
                "O funcionário '" . $funcionario['nome_completo'] . "' (ID: {$funcionario_id_a_eliminar}) foi eliminado do registo de RH.",
                $utilizador_logado['id'],
                ['deleted_employee_id' => $funcionario_id_a_eliminar]
            );
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        // Opcional: Guardar uma mensagem de erro na sessão para mostrar ao utilizador
        // error_log("Erro ao eliminar funcionário: " . $e->getMessage());
    }
}

// Redirecionar de volta para a lista em qualquer caso
header('Location: listar_funcionarios.php');
exit;
?>