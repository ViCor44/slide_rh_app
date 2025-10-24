<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validações
    if (empty($nome_completo) || empty($email) || empty($password)) {
        $errors[] = "Todos os campos são obrigatórios.";
    }
    if ($password !== $password_confirm) {
        $errors[] = "As passwords não coincidem.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "O email inserido não é válido.";
    }
    // Verificar se o email já existe
    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Este email já está registado.";
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // 1. Gerar a chave secreta 2FA
            $secret_key = $google2fa->generateSecretKey();

            // 2. Criar um registo básico de funcionário
            $stmt = $pdo->prepare("INSERT INTO funcionarios (nome_completo, email_corporativo, cargo, departamento, data_contratacao) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome_completo, $email, 'Pendente', 'Pendente', date('Y-m-d')]);
            $funcionario_id = $pdo->lastInsertId();

            // 3. Criar o utilizador com status inativo (is_active = 0)
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO utilizadores (funcionario_id, email, password_hash, google_authenticator_secret, is_active, role_id) VALUES (?, ?, ?, ?, 0, NULL)");
            $stmt->execute([$funcionario_id, $email, $password_hash, $secret_key]);
            
            $pdo->commit();

            // 4. Guardar dados na sessão para a próxima página e redirecionar
            $_SESSION['registration_success'] = true;
            $_SESSION['registration_secret_key'] = $secret_key;
            $_SESSION['registration_email'] = $email;

            header('Location: configurar_2fa.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ocorreu um erro ao criar a sua conta. " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registo - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-800 text-center mb-2">Criar Conta</h1>
        <p class="text-gray-600 text-center mb-8">Inicie o seu registo no Slide RH.</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-6" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="registar.php" method="POST">
            <div class="mb-4">
                <label for="nome_completo" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                <input type="text" id="nome_completo" name="nome_completo" class="w-full px-4 py-2 border rounded-md" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-md" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-md" required>
            </div>
            <div class="mb-6">
                <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Password</label>
                <input type="password" id="password_confirm" name="password_confirm" class="w-full px-4 py-2 border rounded-md" required>
            </div>
            <div class="text-center">
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700">
                    Registar
                </button>
            </div>
             <p class="text-center text-sm text-gray-600 mt-6">
                Já tem conta? <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">Faça login aqui.</a>
            </p>
        </form>
    </div>

</body>
</html>