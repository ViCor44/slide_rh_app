<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Apenas Admins podem aceder a esta página
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN) {
    header('Location: acesso_negado.php');
    exit;
}

// --- LÓGICA DE PAGINAÇÃO ---
$per_page = 25; // Logs por página
$page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

try {
    // Contar o total de registos de log
    $total_logs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);

    // Ir buscar os logs para a página atual, com JOIN para obter o nome do utilizador
    $stmt = $pdo->prepare("
        SELECT l.*, COALESCE(u.nome, l.user_id) as user_identifier
        FROM logs l
        LEFT JOIN utilizadores u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erro ao carregar os logs do sistema.";
}

// Função auxiliar para dar cor aos níveis de log
function get_level_color_class(string $level): string {
    return match (strtoupper($level)) {
        'SECURITY' => 'bg-red-100 text-red-800',
        'ERROR' => 'bg-red-100 text-red-800',
        'WARNING' => 'bg-yellow-100 text-yellow-800',
        default => 'bg-blue-100 text-blue-800',
    };
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Logs do Sistema - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                 <h1 class="text-3xl font-bold text-gray-800">Registo de Atividade do Sistema</h1>
                 <a href="gerir_utilizadores.php" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Voltar à Gestão de Utilizadores</a>
            </div>

            <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilizador</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensagem</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum registo de log encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= get_level_color_class($log['level']) ?>">
                                            <?= htmlspecialchars($log['level']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($log['user_identifier'] ?? 'Sistema') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono"><?= htmlspecialchars($log['ip_address']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-800"><?= htmlspecialchars($log['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <span class="text-sm text-gray-600">
                    Página <?= $page ?> de <?= $total_pages ?> (Total: <?= $total_logs ?> registos)
                </span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="ver_logs.php?page=<?= $page - 1 ?>" class="bg-white text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-100 border">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="ver_logs.php?page=<?= $page + 1 ?>" class="bg-white text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-100 border">Seguinte</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

</body>
</html>