<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// 1. Validar o ID recebido via GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: listar_funcionarios.php");
    exit;
}
$funcionario_id = (int)$_GET['id'];

// ==========================================================
// == BLOCO DE SEGURANÇA FINAL (LÓGICA "LISTA BRANCA")     ==
// ==========================================================

// --- Recolha de Dados Essenciais ---
$logged_in_role_id = (int)($utilizador_logado['role_id'] ?? 0);
$logged_in_funcionario_id = (int)($utilizador_logado['funcionario_id'] ?? 0);

// Vai buscar o departamento do funcionário que está a ser visto
$stmt_check = $pdo->prepare("SELECT departamento FROM funcionarios WHERE id = ?");
$stmt_check->execute([$funcionario_id]);
$funcionario_sendo_visto = $stmt_check->fetch();
$departamento_alvo = $funcionario_sendo_visto['departamento'] ?? null;


// --- Tomada de Decisão ---
// Começamos por assumir que o acesso é proibido por defeito.
$pode_ver_perfil = false;

// Agora, verificamos as razões para PERMITIR o acesso.

// REGRA 1: Admins e RH podem ver tudo. Ponto final.
if ($logged_in_role_id === ROLE_ADMIN || $logged_in_role_id === ROLE_RH) {
    $pode_ver_perfil = true;
}
// REGRA 2: Se não for Admin/RH, verificamos se é Manager/Supervisor
elseif ($logged_in_role_id === ROLE_MANAGER || $logged_in_role_id === ROLE_SUPERVISOR) {
    // Eles só podem ver se o departamento for o mesmo.
    if (isset($_SESSION['user_departamento']) && $_SESSION['user_departamento'] === $departamento_alvo) {
        $pode_ver_perfil = true;
    }
}
// REGRA 3: Se não for nenhum dos anteriores, verificamos se é um Funcionário
elseif ($logged_in_role_id === ROLE_FUNCIONARIO) {
    // Ele só pode ver se for o seu próprio perfil.
    if ($logged_in_funcionario_id === $funcionario_id) {
        $pode_ver_perfil = true;
    }
}

// Decisão Final: Se nenhuma das regras acima permitiu o acesso, bloqueia.
if (!$pode_ver_perfil) {
    header('Location: acesso_negado.php');
    exit;
}

// ==========================================================
// == FIM DO BLOCO DE SEGURANÇA                            ==
// ==========================================================

// 3. Ir buscar todos os dados do funcionário e os seus documentos
try {
    // Query para os detalhes do funcionário (das duas tabelas)
    $stmt = $pdo->prepare("
        SELECT f.*, dp.* FROM funcionarios f
        LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id
        WHERE f.id = ? AND f.ativo = 1
    ");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se não encontrar o funcionário, volta para a lista
    if (!$funcionario) {
        header("Location: listar_funcionarios.php");
        exit;
    }

    // Query para os documentos do funcionário
    $stmt_docs = $pdo->prepare("SELECT * FROM funcionario_documentos WHERE funcionario_id = ? ORDER BY uploaded_at DESC");
    $stmt_docs->execute([$funcionario_id]);
    $documentos = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA PARA AS MÉDIAS DAS AVALIAÇÕES ---
    // 1. Ir buscar a média de cada métrica
    $stmt_medias_metricas = $pdo->prepare("
        SELECT m.nome_metrica, AVG(r.pontuacao) as media
        FROM avaliacao_respostas r
        JOIN avaliacoes a ON r.avaliacao_id = a.id
        JOIN avaliacao_metricas m ON r.metrica_id = m.id
        WHERE a.funcionario_id = ?
        GROUP BY r.metrica_id, m.nome_metrica
        ORDER BY m.id
    ");
    $stmt_medias_metricas->execute([$funcionario_id]);
    $medias_por_metrica = $stmt_medias_metricas->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ir buscar a média total de todas as avaliações
    $stmt_media_total = $pdo->prepare("
        SELECT AVG(r.pontuacao) as media_total
        FROM avaliacao_respostas r
        JOIN avaliacoes a ON r.avaliacao_id = a.id
        WHERE a.funcionario_id = ?
    ");
    $stmt_media_total->execute([$funcionario_id]);
    $media_total = $stmt_media_total->fetchColumn(); // fetchColumn() é perfeito para ir buscar um único valor
} catch (PDOException $e) {
    die("Erro ao carregar dados do funcionário: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes de <?= htmlspecialchars($funcionario['nome_completo']) ?> - CrewSync</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-7xl mx-auto">

            <div class="mb-6">
                <a href="listar_funcionarios.php" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" /></svg>
                    Voltar à Lista de Funcionários
                </a>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <img class="h-24 w-24 rounded-full object-cover" src="mostrar_foto.php?id=<?= $funcionario['id'] ?>" alt="Foto de <?= htmlspecialchars($funcionario['nome_completo']) ?>">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($funcionario['nome_completo']) ?></h1>
                        <p class="text-lg text-gray-600"><?= htmlspecialchars($funcionario['cargo']) ?> - <span class="font-medium text-gray-800"><?= htmlspecialchars($funcionario['departamento']) ?></span></p>
                        <p class="text-sm text-gray-500">Nº de Funcionário: <?= htmlspecialchars($funcionario['numero_funcionario']) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-6 text-sm">
                    <a href="exportar_dados.php?id=<?= $funcionario['id'] ?>" target="_blank" class="text-gray-600 hover:text-gray-900 font-medium transition-colors">Exportar Dados</a>
                    <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH || (int)$utilizador_logado['role_id'] === ROLE_MANAGER): ?>
                        <a href="editar_funcionario.php?id=<?= $funcionario['id'] ?>" class="text-indigo-600 hover:text-indigo-900 font-medium transition-colors">Editar</a>
                        <form action="eliminar_funcionario.php" method="POST" class="inline" onsubmit="return confirm('Tem a certeza?');"><input type="hidden" name="id" value="<?= $funcionario['id'] ?>"><button type="submit" class="text-red-600 hover:text-red-900 font-medium transition-colors">Eliminar</button></form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 space-y-8">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Detalhes de Contacto</h2>
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Email Corporativo</dt>
                            <dd class="text-gray-900 mb-3 break-words"><?= htmlspecialchars($funcionario['email_corporativo'] ?? 'N/A') ?></dd>
                            <dt class="text-sm font-medium text-gray-500">Telemóvel</dt>
                            <dd class="text-gray-900"><?= htmlspecialchars($funcionario['telemovel'] ?? 'N/A') ?></dd>
                        </dl>
                    </div>
                     <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Informação Profissional</h2>
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Data de Contratação</dt>
                            <dd class="text-gray-900 mb-3"><?= htmlspecialchars(date('d/m/Y', strtotime($funcionario['data_contratacao']))) ?></dd>

                            <?php if ($funcionario['departamento'] === 'Piscinas' && !empty($funcionario['sector_piscina'])): ?>
                                <dt class="text-sm font-medium text-gray-500">Sector Piscina</dt>
                                <dd class="text-gray-900 mb-3"><?= htmlspecialchars($funcionario['sector_piscina']) ?></dd>
                            <?php endif; ?>

                            <dt class="text-sm font-medium text-gray-500">ID Cartão NFC</dt>
                            <dd class="text-gray-900 font-mono mb-3"><?= htmlspecialchars($funcionario['nfc_card_id'] ?? 'N/A') ?></dd>
                            
                            <dt class="text-sm font-medium text-gray-500">Status Atual</dt>
                            <dd class="font-semibold <?= ($funcionario['status_servico'] == 'Ao Serviço') ? 'text-green-600' : 'text-orange-600' ?>">
                                <?= htmlspecialchars($funcionario['status_servico']) ?>
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-8">
                    <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Dados Pessoais (Confidencial)</h2>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Data de Nascimento</dt>
                                    <dd class="text-gray-900"><?= $funcionario['data_nascimento'] ? htmlspecialchars(date('d/m/Y', strtotime($funcionario['data_nascimento']))) : 'N/A' ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">NIF</dt>
                                    <dd class="text-gray-900"><?= htmlspecialchars($funcionario['nif'] ?? 'N/A') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nº Cartão de Cidadão</dt>
                                    <dd class="text-gray-900"><?= htmlspecialchars($funcionario['cartao_cidadao'] ?? 'N/A') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nº Segurança Social</dt>
                                    <dd class="text-gray-900"><?= htmlspecialchars($funcionario['nss'] ?? 'N/A') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">IBAN</dt>
                                    <dd class="text-gray-900"><?= htmlspecialchars($funcionario['iban'] ?? 'N/A') ?></dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Morada</dt>
                                    <dd class="text-gray-900"><?= nl2br(htmlspecialchars($funcionario['morada_completa'] ?? 'N/A')) ?></dd>
                                </div>
                            </dl>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Documentos Confidenciais</h2>
                            <form action="upload_documento.php" method="POST" enctype="multipart/form-data" class="mb-6 bg-gray-50 p-4 rounded-md border">
                                <input type="hidden" name="funcionario_id" value="<?= $funcionario_id ?>">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                                    <div>
                                        <label for="tipo_documento" class="block text-sm font-medium text-gray-700">Tipo (ex: Contrato)</label>
                                        <input type="text" name="tipo_documento" id="tipo_documento" class="w-full px-3 py-2 mt-1 border rounded-md" placeholder="Contrato, Baixa, etc.">
                                    </div>
                                    <div>
                                        <label for="documento" class="block text-sm font-medium text-gray-700">Ficheiro</label>
                                        <input type="file" name="documento" id="documento" class="w-full mt-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                                    </div>
                                    <div class="sm:pt-6">
                                        <button type="submit" class="w-full bg-gray-700 text-white font-bold py-2 px-4 rounded-lg hover:bg-gray-800">Carregar</button>
                                    </div>
                                </div>
                            </form>
                            <ul class="space-y-3">
                                <?php if (empty($documentos)): ?>
                                    <li class="text-center text-gray-500 py-4">Nenhum documento encontrado.</li>
                                <?php else: ?>
                                    <?php foreach ($documentos as $doc): ?>
                                        <li class="flex items-center justify-between p-3 bg-white border rounded-md hover:bg-gray-50">
                                            <div>
                                                <p class="font-medium text-gray-800"><?= htmlspecialchars($doc['tipo_documento'] ?: 'Documento') ?></p>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($doc['nome_ficheiro_original']) ?> - <span class="text-xs"><?= date('d/m/Y H:i', strtotime($doc['uploaded_at'])) ?></span></p>
                                            </div>
                                            <a href="download_documento.php?id=<?= $doc['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">Descarregar</a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php
                    // A condição para mostrar o histórico de avaliações:
                    $pode_ver_avaliacoes = (
                        (int)$utilizador_logado['role_id'] === ROLE_ADMIN ||
                        (int)$utilizador_logado['role_id'] === ROLE_MANAGER || // Managers poderão ver (regra a refinar)
                        (int)$utilizador_logado['funcionario_id'] === $funcionario_id // A pessoa pode ver o seu próprio
                    );
                    // Define quem pode criar uma nova avaliação
                    $pode_avaliar = (
                        (int)$utilizador_logado['role_id'] === ROLE_ADMIN ||
                        (int)$utilizador_logado['role_id'] === ROLE_RH ||
                        (int)$utilizador_logado['role_id'] === ROLE_MANAGER ||
                        (int)$utilizador_logado['role_id'] === ROLE_SUPERVISOR
                    );
                    ?>

                    <?php if ($pode_ver_avaliacoes): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Resumo das Avaliações</h2>
                            
                            <?php if ($media_total > 0): ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="md:col-span-1 bg-blue-50 p-6 rounded-lg text-center">
                                        <p class="text-sm font-medium text-blue-700">MÉDIA GERAL</p>
                                        <p class="text-5xl font-bold text-blue-600 mt-2">
                                            <?= number_format($media_total, 2) ?>
                                        </p>
                                        <p class="text-xs text-blue-500">em 5</p>
                                    </div>

                                    <div class="md:col-span-2 space-y-3">
                                        <?php foreach ($medias_por_metrica as $media): ?>
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($media['nome_metrica']) ?></span>
                                                    <span class="text-sm font-semibold text-gray-800"><?= number_format($media['media'], 2) ?></span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                    <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?= ($media['media'] / 5) * 100 ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="text-center mt-6 border-t pt-4">
                                    <?php 
                                    // Só mostra o link para o histórico se o utilizador não estiver a ver o seu próprio perfil
                                    if ((int)($utilizador_logado['funcionario_id'] ?? 0) !== $funcionario_id): 
                                    ?>
                                        <a href="historico_avaliacoes.php?id=<?= $funcionario_id ?>" class="text-sm font-medium text-blue-600 hover:underline">
                                            Ver Histórico Detalhado &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-gray-500 py-8">Nenhuma avaliação encontrada para calcular as médias.</p>
                            <?php endif; ?>

                            <?php if ($pode_avaliar && (int)$utilizador_logado['funcionario_id'] !== $funcionario_id): ?>
                                <div class="text-center mt-6 border-t pt-4">
                                    <a href="adicionar_avaliacao.php?id=<?= $funcionario['id'] ?>" class="bg-green-500 text-white font-bold text-sm py-2 px-4 rounded-lg hover:bg-green-600">
                                        + Nova Avaliação
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

</body>
</html>