<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Apenas utilizadores com permissão podem aceder
$pode_ver_relatorios = (
    (int)$utilizador_logado['role_id'] === ROLE_ADMIN ||
    (int)$utilizador_logado['role_id'] === ROLE_RH ||
    (int)$utilizador_logado['role_id'] === ROLE_MANAGER ||
    (int)$utilizador_logado['role_id'] === ROLE_SUPERVISOR
);
if (!$pode_ver_relatorios) {
    header('Location: acesso_negado.php');
    exit;
}

// Ir buscar departamentos para o filtro
$departamentos = $pdo->query("SELECT DISTINCT departamento FROM funcionarios ORDER BY departamento")->fetchAll(PDO::FETCH_COLUMN);
$funcionarios = $pdo->query("SELECT id, nome_completo, numero_funcionario FROM funcionarios WHERE ativo = 1 ORDER BY nome_completo")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Central de Relatórios - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">
    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="index.php" class="inline-flex items-center gap-2 text-sm ...">&larr; Voltar ao Dashboard</a>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Central de Relatórios</h1>
                
                <form action="gerar_relatorio.php" method="POST" target="_blank" class="space-y-6">
                    <div>
                        <label for="report_type" class="block text-sm font-medium text-gray-700">1. Escolha o tipo de relatório:</label>
                        <select name="report_type" id="report_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">-- Selecione uma opção --</option>
                            
                            <?php $role_id = (int)$utilizador_logado['role_id']; ?>

                            <?php if ($role_id === ROLE_ADMIN || $role_id === ROLE_RH): ?>
                                <option value="relatorio_individual">Relatório Individual de Funcionário</option>
                            <?php endif; ?>

                            <?php if ($role_id === ROLE_ADMIN): ?>
                                <option value="lista_funcionarios_completa">Lista de Funcionários (Dados Completos)</option>
                                <option value="avaliacoes_geral">Relatório de Avaliações</option>
                                <option value="resumo_medias_geral">Resumo de Médias de Avaliação (Geral)</option>
                            <?php endif; ?>

                            <?php if ($role_id === ROLE_RH): ?>
                                <option value="lista_funcionarios_completa">Lista de Funcionários (Dados Completos)</option>
                            <?php endif; ?>

                            <?php if ($role_id === ROLE_MANAGER): ?>
                                <option value="lista_profissionais_dep">Lista de Funcionários (Dados Profissionais do Dept.)</option>
                                <option value="avaliacoes_dep">Relatório de Avaliações (Supervisores e Funcionários do Dept.)</option>
                            <?php endif; ?>

                            <?php if ($role_id === ROLE_SUPERVISOR): ?>
                                <option value="lista_profissionais_dep">Lista de Funcionários (Dados Profissionais do Dept.)</option>
                                <option value="avaliacoes_dep_func">Relatório de Avaliações (Funcionários do Dept.)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">2. Escolha os filtros (opcional):</label>
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($role_id === ROLE_ADMIN || $role_id === ROLE_RH): ?>
                            <div>
                                <label for="funcionario" class="block text-xs font-medium text-gray-600">Funcionário Específico</label>
                                <select name="filtro_funcionario_id" id="funcionario" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                    <option value="">-- Selecione um funcionário (opcional) --</option>
                                    <?php foreach($funcionarios as $func): ?>
                                        <option value="<?= $func['id'] ?>">(<?= htmlspecialchars($func['numero_funcionario']) ?>) <?= htmlspecialchars($func['nome_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="departamento" class="block text-xs font-medium text-gray-600">Departamento</label>
                                <select name="filtro_departamento" id="departamento" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                    <option value="">Todos</option>
                                    <?php foreach($departamentos as $dep): ?>
                                        <option value="<?= htmlspecialchars($dep) ?>"><?= htmlspecialchars($dep) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            </div>
                    </div>

                    <div class="text-right border-t pt-6">
                        <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-blue-700">
                            Gerar Relatório PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>