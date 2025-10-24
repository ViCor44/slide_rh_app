<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/logger.php'; // Vamos usar o nosso logger

// Permissões: Apenas Admins, RH, Managers e Supervisores podem criar avaliações
$pode_avaliar = (
    (int)$utilizador_logado['role_id'] === ROLE_ADMIN ||
    (int)$utilizador_logado['role_id'] === ROLE_RH ||
    (int)$utilizador_logado['role_id'] === ROLE_MANAGER ||
    (int)$utilizador_logado['role_id'] === ROLE_SUPERVISOR
);
if (!$pode_avaliar) {
    header('Location: acesso_negado.php');
    exit;
}

// Ir buscar o funcionário a ser avaliado (necessário para GET e POST)
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: listar_funcionarios.php");
    exit;
}
$funcionario_id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT id, nome_completo FROM funcionarios WHERE id = ?");
$stmt->execute([$funcionario_id]);
$funcionario = $stmt->fetch();
if (!$funcionario) {
    header("Location: listar_funcionarios.php");
    exit;
}

// ==========================================================
// == INÍCIO DA NOVA LÓGICA PARA GUARDAR A AVALIAÇÃO (POST) ==
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodo = trim($_POST['periodo'] ?? '');
    $data_avaliacao = trim($_POST['data_avaliacao'] ?? '');
    $comentarios_finais = trim($_POST['comentarios_finais'] ?? '');
    $objetivos_futuros = trim($_POST['objetivos_futuros'] ?? '');
    $metricas_respostas = $_POST['metricas'] ?? [];

    // Validação simples
    if (empty($periodo) || empty($data_avaliacao) || empty($metricas_respostas)) {
        $error = "O período, a data e as pontuações das métricas são obrigatórios.";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Inserir o registo principal na tabela `avaliacoes`
            $sql_aval = "INSERT INTO avaliacoes (funcionario_id, avaliador_user_id, periodo, data_avaliacao, comentarios_finais, objetivos_futuros) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_aval = $pdo->prepare($sql_aval);
            $stmt_aval->execute([
                $funcionario_id,
                $utilizador_logado['id'],
                $periodo,
                $data_avaliacao,
                $comentarios_finais,
                $objetivos_futuros
            ]);
            $avaliacao_id = $pdo->lastInsertId();

            // 2. Inserir cada resposta de métrica na tabela `avaliacao_respostas`
            $sql_resp = "INSERT INTO avaliacao_respostas (avaliacao_id, metrica_id, pontuacao, comentarios) VALUES (?, ?, ?, ?)";
            $stmt_resp = $pdo->prepare($sql_resp);

            foreach ($metricas_respostas as $metrica_id => $resposta) {
                $stmt_resp->execute([
                    $avaliacao_id,
                    $metrica_id,
                    $resposta['pontuacao'],
                    trim($resposta['comentarios'] ?? '')
                ]);
            }
            
            $pdo->commit();
            
            // Registar o evento no log
            log_event($pdo, 'INFO', 'EVALUATION_CREATED', "Nova avaliação para o funcionário '{$funcionario['nome_completo']}' (ID: {$funcionario_id}) foi submetida.", $utilizador_logado['id']);

            // Redirecionar para a página de detalhe com uma mensagem de sucesso
            header('Location: funcionario_detalhe.php?id=' . $funcionario_id . '&status=avaliacao_success');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Ocorreu um erro ao guardar a avaliação: " . $e->getMessage();
        }
    }
}
// ==========================================================
// == FIM DA NOVA LÓGICA                                   ==
// ==========================================================

// Ir buscar as métricas ativas da base de dados para construir o formulário
$metricas = $pdo->query("SELECT * FROM avaliacao_metricas WHERE ativa = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <title>Nova Avaliação - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>
    <main class="p-8">
        <div class="max-w-4xl mx-auto">
            <a href="funcionario_detalhe.php?id=<?= $funcionario_id ?>" class="inline-flex items-center gap-2 text-sm ...">
                &larr; Voltar ao Perfil
            </a>

            <form action="adicionar_avaliacao.php?id=<?= $funcionario_id ?>" method="POST" class="bg-white p-8 rounded-2xl shadow-lg mt-6">
                <h1 class="text-3xl font-bold text-gray-800">Nova Avaliação de Desempenho</h1>
                <p class="text-gray-600 mb-8">A avaliar: <strong class="font-medium"><?= htmlspecialchars($funcionario['nome_completo']) ?></strong></p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label for="periodo" class="block text-sm font-medium text-gray-700">Período de Avaliação</label>
                        <input type="text" name="periodo" id="periodo" class="mt-1 block w-full border-gray-300 rounded-md" placeholder="Ex: Q4 2025" required>
                    </div>
                    <div>
                        <label for="data_avaliacao" class="block text-sm font-medium text-gray-700">Data da Avaliação</label>
                        <input type="date" name="data_avaliacao" id="data_avaliacao" value="<?= date('Y-m-d') ?>" class="mt-1 block w-full border-gray-300 rounded-md" required>
                    </div>
                </div>

                <div class="space-y-8">
                    <?php foreach ($metricas as $metrica): ?>
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($metrica['nome_metrica']) ?></h3>
                            <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($metrica['descricao']) ?></p>
                            
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pontuação (1-5)</label>
                            <div class="flex items-center gap-4">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="metricas[<?= $metrica['id'] ?>][pontuacao]" value="<?= $i ?>" class="h-4 w-4 text-blue-600" required>
                                        <span class="text-sm"><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            
                            <label class="block text-sm font-medium text-gray-700 mt-4">Comentários Específicos</label>
                            <textarea name="metricas[<?= $metrica['id'] ?>][comentarios]" rows="2" class="mt-1 block w-full border-gray-300 rounded-md"></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t mt-8 pt-6 space-y-6">
                    <div>
                        <label for="comentarios_finais" class="block text-lg font-semibold text-gray-800">Comentários Finais</label>
                        <textarea name="comentarios_finais" rows="4" class="mt-2 block w-full border-gray-300 rounded-md"></textarea>
                    </div>
                    <div>
                        <label for="objetivos_futuros" class="block text-lg font-semibold text-gray-800">Objetivos para o Próximo Período</label>
                        <textarea name="objetivos_futuros" rows="4" class="mt-2 block w-full border-gray-300 rounded-md"></textarea>
                    </div>
                </div>

                <div class="mt-8 text-right">
                    <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-blue-700">Submeter Avaliação</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>