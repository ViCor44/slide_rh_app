<?php
// Incluímos o auth_guard para que o header possa mostrar o nome do utilizador, etc.
// Se o utilizador não estiver logado, será redirecionado para o login.
require_once '../src/auth_guard.php';
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - CrewSync</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="flex items-center justify-center" style="height: calc(100vh - 80px);">
        <div class="text-center">
            <h1 class="text-9xl font-bold text-red-500">403</h1>
            <h2 class="text-3xl font-semibold text-gray-800 mt-4">Acesso Negado</h2>
            <p class="text-gray-600 mt-2">Ups! Parece que não tem as permissões necessárias para ver esta página.</p>
            <div class="mt-8 flex justify-center gap-4">
                <a href="listar_funcionarios.php" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors">
                    Ir para a Página Principal
                </a>
                <button onclick="window.history.back()" class="bg-gray-200 text-gray-800 font-bold py-3 px-6 rounded-lg hover:bg-gray-300 transition-colors">
                    Voltar Atrás
                </button>
            </div>
        </div>
    </main>

</body>
</html>