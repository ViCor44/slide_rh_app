<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Dashboard - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Dashboard</h1>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

                <?php
                // Vamos usar uma variável para facilitar a leitura
                $role_id = (int)($utilizador_logado['role_id'] ?? 0);

                // Se o utilizador tiver uma função de gestão (qualquer uma exceto Funcionário)
                if ($role_id === ROLE_ADMIN || $role_id === ROLE_RH || $role_id === ROLE_MANAGER || $role_id === ROLE_SUPERVISOR):
                ?>
                    <a href="listar_funcionarios.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-lg text-gray-800">Gerir Funcionários</h2>
                                <p class="text-sm text-gray-600">Ver, adicionar ou editar funcionários.</p>
                            </div>
                        </div>
                    </a>

                <?php
                // Se o utilizador for um Funcionário normal
                elseif ($role_id === ROLE_FUNCIONARIO):
                ?>
                    <a href="listar_funcionarios.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-lg text-gray-800">Diretório de Funcionários</h2>
                                <p class="text-sm text-gray-600">Ver a lista de colegas da empresa.</p>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ((int)($utilizador_logado['role_id'] ?? 0) === ROLE_ADMIN): ?>
                <a href="gerir_utilizadores.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Gerir Utilizadores</h2>
                            <p class="text-sm text-gray-600">Aprovar e gerir contas de acesso.</p>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ((int)($utilizador_logado['role_id'] ?? 0) === ROLE_ADMIN): ?>
                <a href="ver_logs.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Logs do Sistema</h2>
                            <p class="text-sm text-gray-600">Ver o registo de atividade.</p>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
                
                <a href="perfil.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-green-100 text-green-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">O Meu Perfil</h2>
                            <p class="text-sm text-gray-600">Alterar password e gerir 2FA.</p>
                        </div>
                    </div>
                </a>
                
                <?php
                $pode_avaliar = (
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_ADMIN ||
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_RH ||
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_MANAGER ||
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_SUPERVISOR
                );

                if ($pode_avaliar):
                ?>
                <a href="listar_funcionarios.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-teal-100 text-teal-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Avaliações</h2>
                            <p class="text-sm text-gray-600">Consultar ou iniciar avaliações.</p>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php
                // A mesma regra de permissão que já tínhamos
                $pode_ver_relatorios = (
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_ADMIN ||
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_RH ||
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_MANAGER ||
                    (int)($utilizador_logado['role_id'] ?? 0) === ROLE_SUPERVISOR
                );

                if ($pode_ver_relatorios):
                ?>
                <a href="relatorios.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-purple-100 text-purple-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Relatórios</h2>
                            <p class="text-sm text-gray-600">Exportar dados da aplicação.</p>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>