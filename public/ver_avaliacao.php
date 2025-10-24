<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// 1. Validar o ID da avaliação
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: listar_funcionarios.php");
    exit;
}
$avaliacao_id = (int)$_GET['id'];

try {
    // 2. Ir buscar os dados principais da avaliação e os nomes do funcionário/avaliador
    $stmt_aval = $pdo->prepare("
        SELECT 
            a.*, 
            f.nome_completo as funcionario_nome,
            COALESCE(u.nome, u.email) as avaliador_nome
        FROM avaliacoes a
        JOIN funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN utilizadores u ON a.avaliador_user_id = u.id
        WHERE a.id = ?
    ");
    $stmt_aval->execute([$avaliacao_id]);
    $avaliacao = $stmt_aval->fetch(PDO::FETCH_ASSOC);

    if (!$avaliacao) {
        // Se a avaliação não for encontrada, volta para a lista
        header("Location: listar_funcionarios.php");
        exit;
    }

    // 3. Ir buscar todas as respostas detalhadas para esta avaliação
    $stmt_respostas = $pdo->prepare("
        SELECT r.pontuacao, r.comentarios, m.nome_metrica, m.descricao
        FROM avaliacao_respostas r
        JOIN avaliacao_metricas m ON r.metrica_id = m.id
        WHERE r.avaliacao_id = ?
        ORDER BY m.id
    ");
    $stmt_respostas->execute([$avaliacao_id]);
    $respostas = $stmt_respostas->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Lógica de Permissões: Quem pode ver esta avaliação?
    $funcionario_id_alvo = (int)$avaliacao['funcionario_id'];
    $pode_ver_esta_avaliacao = (
        (int)$utilizador_logado['role_id'] === ROLE_ADMIN ||
        (int)$utilizador_logado['role_id'] === ROLE_MANAGER || // (Regra a refinar no futuro)
        (int)$utilizador_logado['funcionario_id'] === $funcionario_id_alvo
    );
    if (!$pode_ver_esta_avaliacao) {
        header('Location: acesso_negado.php');
        exit;
    }

} catch (PDOException $e) {
    die("Erro ao carregar a avaliação: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Detalhe da Avaliação - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="funcionario_detalhe.php?id=<?= $avaliacao['funcionario_id'] ?>" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                    &larr; Voltar ao Perfil de <?= htmlspecialchars($avaliacao['funcionario_nome']) ?>
                </a>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <div class="border-b pb-4 mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Relatório de Avaliação</h1>
                    <p class="text-gray-600">Período: <strong class="font-medium"><?= htmlspecialchars($avaliacao['periodo']) ?></strong></p>
                    <p class="text-sm text-gray-500">
                        Funcionário: <?= htmlspecialchars($avaliacao['funcionario_nome']) ?> | 
                        Data: <?= htmlspecialchars(date('d/m/Y', strtotime($avaliacao['data_avaliacao']))) ?> | 
                        Avaliador: <?= htmlspecialchars($avaliacao['avaliador_nome'] ?? 'N/A') ?>
                    </p>
                </div>

                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-700">Avaliação por Métricas</h2>
                    <?php foreach ($respostas as $resposta): ?>
                        <div>
                            <div class="flex justify-between items-baseline">
                                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($resposta['nome_metrica']) ?></h3>
                                <span class="text-lg font-bold text-blue-600"><?= htmlspecialchars($resposta['pontuacao']) ?> / 5</span>
                            </div>
                            <?php if (!empty($resposta['comentarios'])): ?>
                                <blockquote class="mt-2 pl-4 border-l-4 border-gray-200 bg-gray-50 p-3 rounded-r-lg">
                                    <p class="text-sm text-gray-700 italic">"<?= nl2br(htmlspecialchars($resposta['comentarios'])) ?>"</p>
                                </blockquote>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t mt-8 pt-6 space-y-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-700">Comentários Finais do Avaliador</h2>
                        <p class="mt-2 text-gray-700"><?= !empty($avaliacao['comentarios_finais']) ? nl2br(htmlspecialchars($avaliacao['comentarios_finais'])) : '<i class="text-gray-500">Nenhum comentário final foi adicionado.</i>' ?></p>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-700">Objetivos para o Próximo Período</h2>
                        <p class="mt-2 text-gray-700"><?= !empty($avaliacao['objetivos_futuros']) ? nl2br(htmlspecialchars($avaliacao['objetivos_futuros'])) : '<i class="text-gray-500">Nenhum objetivo foi definido.</i>' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>