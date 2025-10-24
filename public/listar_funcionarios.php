<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$termo_pesquisa = trim($_GET['q'] ?? '');
$funcionarios = [];

try {
    // Query simplificada: vai buscar todos os funcionários ativos.
    // A lógica de permissão foi REMOVIDA daqui.
    $sql = "SELECT id, numero_funcionario, nome_completo, email_corporativo, cargo, departamento 
            FROM funcionarios 
            WHERE ativo = 1";
    $params = [];

    // A lógica de pesquisa continua a funcionar normalmente
    if (!empty($termo_pesquisa)) {
        $sql .= " AND (numero_funcionario LIKE ? OR nome_completo LIKE ? OR email_corporativo LIKE ? OR cargo LIKE ? OR departamento LIKE ?)";
        $like_term = "%{$termo_pesquisa}%";
        array_push($params, $like_term, $like_term, $like_term, $like_term, $like_term);
    }

    $sql .= " ORDER BY nome_completo ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erro_db = "Erro ao carregar os dados dos funcionários: " . $e->getMessage();
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

        <div class="mb-8 bg-white p-4 rounded-lg shadow">
            <form action="listar_funcionarios.php" method="GET" class="flex items-center gap-4">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
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
                <a href="listar_funcionarios.php" class="text-sm font-medium text-blue-600 hover:text-blue-800 flex-shrink-0">Limpar Pesquisa</a>
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

</body>
</html>