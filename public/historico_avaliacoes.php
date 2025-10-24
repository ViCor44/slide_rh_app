<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Validar o ID do funcionário
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: listar_funcionarios.php");
    exit;
}
$funcionario_id = (int)$_GET['id'];

// Lógica de permissões (igual à da página de detalhe)
// ... (copie e cole aqui o seu bloco de segurança para ver o perfil) ...

// Ir buscar as avaliações (a query que usávamos antes na página de detalhe)
try {
    $stmt_func = $pdo->prepare("SELECT nome_completo FROM funcionarios WHERE id = ?");
    $stmt_func->execute([$funcionario_id]);
    $funcionario_nome = $stmt_func->fetchColumn();

    $stmt_avaliacoes = $pdo->prepare("
        SELECT a.*, COALESCE(u.nome, u.email) as avaliador_nome
        FROM avaliacoes a
        LEFT JOIN utilizadores u ON a.avaliador_user_id = u.id
        WHERE a.funcionario_id = ?
        ORDER BY a.data_avaliacao DESC
    ");
    $stmt_avaliacoes->execute([$funcionario_id]);
    $avaliacoes = $stmt_avaliacoes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar o histórico: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <title>Histórico de Avaliações - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>
    <main class="p-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="funcionario_detalhe.php?id=<?= $funcionario_id ?>" class="inline-flex items-center gap-2 text-sm ...">
                    &larr; Voltar ao Perfil
                </a>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-3xl font-bold text-gray-800">Histórico de Avaliações</h1>
                <p class="text-gray-600 mb-6">A ver o histórico de: <strong class="font-medium"><?= htmlspecialchars($funcionario_nome) ?></strong></p>

                <div class="space-y-4">
                    <?php if (empty($avaliacoes)): ?>
                        <p class="text-center text-gray-500 py-12">Nenhum registo de avaliação encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($avaliacoes as $avaliacao): ?>
                            <a href="ver_avaliacao.php?id=<?= $avaliacao['id'] ?>" class="block border rounded-lg p-4 transition-all duration-300 hover:bg-gray-50 hover:shadow-sm">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold text-blue-600"><?= htmlspecialchars($avaliacao['periodo']) ?></p>
                                        <p class="text-sm text-gray-500">
                                            Realizada em <?= htmlspecialchars(date('d/m/Y', strtotime($avaliacao['data_avaliacao']))) ?>
                                            por <?= htmlspecialchars($avaliacao['avaliador_nome'] ?? 'Utilizador Apagado') ?>
                                        </p>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>