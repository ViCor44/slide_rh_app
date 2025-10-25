<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Obter os valores dos filtros (GET)
$termo_pesquisa = trim($_GET['q'] ?? '');
$status_filtro = trim($_GET['filtro_status'] ?? '');
$sector_filtro = !empty($_GET['filtro_sector']) ? (int)$_GET['filtro_sector'] : null;

$funcionarios = [];
$erro_db = null; // Inicializar a variável de erro

try {
    // Base da query com LEFT JOIN para aceder ao role_id do utilizador associado ao funcionário
    $sql = "SELECT f.id, f.numero_funcionario, f.nome_completo, f.email_corporativo, f.cargo, f.departamento, f.sector_piscina, f.status_servico
            FROM funcionarios f
            LEFT JOIN utilizadores u ON f.id = u.funcionario_id
            WHERE f.ativo = 1";
    $params = [];

    // Filtro de Pesquisa Geral (LIKE)
    if (!empty($termo_pesquisa)) {
        // Adiciona parêntesis para garantir a prioridade do OR
        $sql .= " AND (f.numero_funcionario LIKE ? OR f.nome_completo LIKE ? OR f.email_corporativo LIKE ? OR f.cargo LIKE ? OR f.departamento LIKE ?)";
        $like_term = "%{$termo_pesquisa}%";
        // Adiciona o termo 5 vezes ao array de parâmetros
        array_push($params, $like_term, $like_term, $like_term, $like_term, $like_term);
    }

    // Filtro por Status de Serviço
    if (!empty($status_filtro)) {
        $sql .= " AND f.status_servico = ?";
        $params[] = $status_filtro;
    }

    // Filtro por Sector Piscina
    if ($sector_filtro !== null) {
        // Garante que só filtra se o departamento for Piscinas
        $sql .= " AND f.departamento = 'Piscinas' AND f.sector_piscina = ?";
        $params[] = $sector_filtro;
    }

    // Lógica de Permissão para Manager/Supervisor
    $logged_in_role_id = (int)$utilizador_logado['role_id'];

    if ($logged_in_role_id === ROLE_SUPERVISOR) {
        // Regra para Supervisor: Vê o seu departamento, mas EXCLUI os Managers.
        $sql .= " AND f.departamento = ? AND (u.role_id IS NULL OR u.role_id != ?)";
        $params[] = $_SESSION['user_departamento'] ?? ''; // Departamento do supervisor
        $params[] = ROLE_MANAGER; // ID do Role Manager a excluir

    } elseif ($logged_in_role_id === ROLE_MANAGER) {
        // Regra para Manager: Vê todas as pessoas do seu departamento.
        $sql .= " AND f.departamento = ?";
        if (!empty($_SESSION['user_departamento'])) {
            $params[] = $_SESSION['user_departamento']; // Departamento do manager
        } else {
            // Se o manager não tem departamento definido na sessão, não deve ver ninguém.
            $sql .= " AND 1=0"; // Condição que nunca será verdadeira
        }
    }
    // NOTA: Admins e RH não entram nestas condições `if/elseif`, por isso a query não é restrita por departamento para eles.

    // Ordenação final
    $sql .= " ORDER BY f.nome_completo ASC";

    // Preparar e executar a query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Registar o erro para diagnóstico, mas mostrar uma mensagem genérica
    error_log("Erro na query de listar_funcionarios: " . $e->getMessage());
    $erro_db = "Ocorreu um erro ao carregar os dados dos funcionários. Por favor, tente mais tarde.";
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Funcionários - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">

    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-4xl font-bold text-gray-800">Equipa</h1>
            <div class="flex items-center gap-4">
                <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH): ?>
                    <div class="flex justify-end mb-6">
                        <a href="adicionar_funcionario.php" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            + Adicionar Funcionário
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-8 bg-white p-6 rounded-lg shadow space-y-4">
            <form action="listar_funcionarios.php" method="GET">
                <div class="flex items-center gap-4">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input 
                            type="search" 
                            name="q" 
                            placeholder="Pesquisar funcionários..."
                            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-gray-500"
                            autocomplete="off"
                        >
                    </div>
                    <button type="button" id="toggle-filters-btn" class="flex-shrink-0 bg-gray-100 text-gray-700 font-medium py-2 px-4 rounded-lg hover:bg-gray-200 text-sm">
                        Filtros Avançados
                    </button>
                    <a href="listar_funcionarios.php" class="text-sm font-medium text-blue-600 hover:text-blue-800 flex-shrink-0">Limpar Tudo</a>
                </div>
                
                <div id="advanced-filters" class="hidden pt-4 border-t space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="filtro_status" class="block text-xs font-medium text-gray-600 mb-1">Filtrar por Status</label>
                            <select id="filtro_status" name="filtro_status" class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Todos os Status --</option>
                                <option value="Ao Serviço" <?= (($status_filtro ?? '') == 'Ao Serviço') ? 'selected' : '' ?>>Ao Serviço</option>
                                <option value="Baixa Médica" <?= (($status_filtro ?? '') == 'Baixa Médica') ? 'selected' : '' ?>>Baixa Médica</option>
                                <option value="Férias" <?= (($status_filtro ?? '') == 'Férias') ? 'selected' : '' ?>>Férias</option>
                                <option value="Licença" <?= (($status_filtro ?? '') == 'Licença') ? 'selected' : '' ?>>Licença</option>
                            </select>
                        </div>
                        <div>
                            <label for="filtro_sector" class="block text-xs font-medium text-gray-600 mb-1">Filtrar por Sector (Piscinas)</label>
                            <input 
                                type="number" 
                                id="filtro_sector" 
                                name="filtro_sector" 
                                value="<?= htmlspecialchars($sector_filtro ?? '') ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Nº Sector"
                                min="1"
                            >
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="bg-gray-700 text-white font-bold py-2 px-6 rounded-lg hover:bg-gray-800 text-sm">
                            Aplicar Filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php if (isset($erro_db)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p><?= $erro_db ?></p>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Funcionário</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cargo</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departamento</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($funcionarios)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">Nenhum funcionário encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($funcionarios as $funcionario): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full object-cover" src="mostrar_foto.php?id=<?= $funcionario['id'] ?>" alt="Foto de <?= htmlspecialchars($funcionario['nome_completo']) ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <a href="funcionario_detalhe.php?id=<?= $funcionario['id'] ?>" class="hover:text-blue-600 hover:underline">
                                                        <?= htmlspecialchars($funcionario['nome_completo']) ?>
                                                    </a>
                                                </div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($funcionario['email_corporativo']) ?> (Nº: <?= htmlspecialchars($funcionario['numero_funcionario']) ?>)</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($funcionario['cargo']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?= htmlspecialchars($funcionario['departamento']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH || (int)$utilizador_logado['role_id'] === ROLE_MANAGER): ?>
                                            <a href="editar_funcionario.php?id=<?= $funcionario['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">Editar</a>
                                        <?php endif; ?>

                                        <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN): ?>
                                            <form action="eliminar_funcionario.php" method="POST" class="inline" onsubmit="return confirm('Tem a certeza que deseja eliminar este funcionário? Esta ação é irreversível.');">
                                                <input type="hidden" name="id" value="<?= $funcionario['id'] ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<script>
    // Encontra o botão e a secção de filtros
    const toggleButton = document.getElementById('toggle-filters-btn');
    const filtersSection = document.getElementById('advanced-filters');

    // Adiciona um 'ouvinte' para o evento de clique no botão
    toggleButton.addEventListener('click', () => {
        // Alterna a classe 'hidden' na secção de filtros
        filtersSection.classList.toggle('hidden');
        
        // Opcional: Mudar o texto do botão (Ex: "Esconder Filtros")
        if (filtersSection.classList.contains('hidden')) {
            toggleButton.textContent = 'Filtros Avançados';
        } else {
            toggleButton.textContent = 'Esconder Filtros';
        }
    });

    // Opcional: Manter os filtros visíveis se já estiverem a ser usados
    // (Verifica se algum filtro avançado tem valor no URL)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filtro_status') || urlParams.get('filtro_sector')) {
        filtersSection.classList.remove('hidden');
        toggleButton.textContent = 'Esconder Filtros';
    }
</script>

</body>
</html>