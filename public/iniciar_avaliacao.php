<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Permissões: Quem pode aceder a esta página para iniciar uma avaliação?
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

// --- Lógica para ir buscar os funcionários que ESTE utilizador pode avaliar ---
$funcionarios_avaliáveis = [];
$erro_db = null;

try {
    // Reutilizar a lógica de permissão da página listar_funcionarios.php
    // Base da query com LEFT JOIN para aceder ao role_id
    $sql = "SELECT f.id, f.numero_funcionario, f.nome_completo, f.cargo, f.departamento
            FROM funcionarios f
            LEFT JOIN utilizadores u ON f.id = u.funcionario_id
            WHERE f.ativo = 1";
    $params = [];

    $logged_in_role_id = (int)$utilizador_logado['role_id'];

    if ($logged_in_role_id === ROLE_SUPERVISOR) {
        // Supervisor: Vê funcionários do seu departamento, excluindo Managers
        $sql .= " AND f.departamento = ? AND (u.role_id IS NULL OR u.role_id != ?)";
        $params[] = $_SESSION['user_departamento'] ?? '';
        $params[] = ROLE_MANAGER;

    } elseif ($logged_in_role_id === ROLE_MANAGER) {
        // Manager: Vê todos no seu departamento
        $sql .= " AND f.departamento = ?";
        if (!empty($_SESSION['user_departamento'])) {
            $params[] = $_SESSION['user_departamento'];
        } else {
            $sql .= " AND 1=0"; // Não vê ninguém se não tiver departamento
        }
    }
    // Admins e RH não têm filtros extras aplicados aqui, veem todos os ativos.

    $sql .= " ORDER BY f.nome_completo ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $funcionarios_avaliáveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao buscar funcionários para avaliação: " . $e->getMessage());
    $erro_db = "Ocorreu um erro ao carregar a lista de funcionários.";
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Nova Avaliação - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>
    <main class="p-8">
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Voltar ao Dashboard</a>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h1 class="text-3xl font-bold text-gray-800 border-b pb-4 mb-6">Iniciar Nova Avaliação</h1>

                <?php if ($erro_db): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md">
                        <?= htmlspecialchars($erro_db) ?>
                    </div>
                <?php elseif (empty($funcionarios_avaliáveis)): ?>
                     <p class="text-center text-gray-500 py-8">Não foram encontrados funcionários elegíveis para avaliação.</p>
                <?php else: ?>
                    <form action="adicionar_avaliacao.php" method="GET">
                        <div class="space-y-4">
                            <div>
                                <label for="funcionario_id" class="block text-sm font-medium text-gray-700">Selecione o funcionário a avaliar:</label>
                                <select id="funcionario_id" name="id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 py-2 px-3" required>
                                    <option value="">-- Escolha um funcionário --</option>
                                    <?php foreach($funcionarios_avaliáveis as $func): ?>
                                        <option value="<?= $func['id'] ?>">
                                            (<?= htmlspecialchars($func['numero_funcionario']) ?>) <?= htmlspecialchars($func['nome_completo']) ?> - [<?= htmlspecialchars($func['departamento']) ?>]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="text-right pt-4">
                                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700">
                                    Iniciar Avaliação &rarr;
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>