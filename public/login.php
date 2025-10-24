<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: listar_funcionarios.php');
    exit;
}

require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once '../src/logger.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Email e password são obrigatórios.";
    } else {
        try {
            // QUERY ATUALIZADA: Adicionar departamento à seleção
            $stmt = $pdo->prepare("
                SELECT u.*, COALESCE(f.nome_completo, u.nome) AS nome_final, f.departamento
                FROM utilizadores u 
                LEFT JOIN funcionarios f ON u.funcionario_id = f.id 
                WHERE u.email = ? AND u.is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // LOG DE SUCESSO
                log_event($pdo, 'INFO', 'LOGIN_SUCCESS', "Utilizador '{$email}' fez login com sucesso.", $user['id']);

                if (!empty($user['google_authenticator_secret'])) {
                    $_SESSION['2fa_user_id'] = $user['id'];
                    header('Location: verificar_2fa.php');
                    exit;
                } else {
                    session_regenerate_id(true);
                    
                    // SESSÃO CRIADA COM O DEPARTAMENTO
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role_id'] = $user['role_id'];
                    $_SESSION['user_name'] = $user['nome_final'];
                    $_SESSION['user_funcionario_id'] = $user['funcionario_id'];
                    $_SESSION['user_departamento'] = $user['departamento'] ?? null; // Adicionar esta linha

                    header('Location: index.php');
                    exit;
                }
            } else {
                // LOG DE FALHA
                log_event($pdo, 'WARNING', 'LOGIN_FAILURE', "Tentativa de login falhada para o email '{$email}'.");
                $errors[] = "Credenciais inválidas.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erro do sistema. Por favor, tente mais tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header_public.php'; ?>

    <main class="flex items-center justify-center py-12">

        <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
            <h1 class="text-3xl font-bold text-gray-800 text-center mb-2">Bem-vindo ao CrewSync</h1>
            <p class="text-gray-600 text-center mb-8">Faça login para aceder ao sistema.</p>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-6" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-md border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-md border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="text-center">
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300">
                        Entrar
                    </button>
                </div>
                <p class="text-center text-sm text-gray-600 mt-6">
                    Não tem conta? <a href="registar.php" class="font-medium text-blue-600 hover:text-blue-500">Registe-se aqui.</a>
                </p>
            </form>
        </div>
    </main>
</body>
</html>