<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$errors = [];
$successMessage = '';

// --- LÓGICA PARA PROCESSAR OS FORMULÁRIOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identificar qual formulário foi submetido
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Lógica de alterar password (que já tínhamos)
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = 'Todos os campos de password são obrigatórios.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'A nova password e a sua confirmação não coincidem.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM utilizadores WHERE id = ?");
                $stmt->execute([$utilizador_logado['id']]);
                $user = $stmt->fetch();

                if ($user && password_verify($current_password, $user['password_hash'])) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id = ?");
                    $update_stmt->execute([$new_password_hash, $utilizador_logado['id']]);
                    $successMessage = 'A sua password foi alterada com sucesso!';
                } else {
                    $errors[] = 'A sua password atual está incorreta.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Ocorreu um erro ao tentar alterar a password.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'disable_2fa') {
        // Lógica para desativar o 2FA
        try {
            $stmt = $pdo->prepare("UPDATE utilizadores SET google_authenticator_secret = NULL WHERE id = ?");
            $stmt->execute([$utilizador_logado['id']]);
            $successMessage = 'Autenticação de dois fatores foi desativada com sucesso!';
        } catch (PDOException $e) {
            $errors[] = 'Ocorreu um erro ao desativar o 2FA.';
        }
    }
}

// --- LÓGICA PARA IR BUSCAR OS DADOS ATUAIS DO UTILIZADOR (GET) ---
try {
    $stmt = $pdo->prepare("SELECT google_authenticator_secret FROM utilizadores WHERE id = ?");
    $stmt->execute([$utilizador_logado['id']]);
    $user_data = $stmt->fetch();
    $is_2fa_enabled = !empty($user_data['google_authenticator_secret']);
} catch (PDOException $e) {
    $errors[] = "Erro ao carregar os dados do perfil.";
    $is_2fa_enabled = false;
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O Meu Perfil - CrewSync</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-4xl mx-auto space-y-8">
            <h1 class="text-3xl font-bold text-gray-800">O Meu Perfil</h1>

            <?php if ($successMessage): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md">
                    <ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-4">Autenticação de Dois Fatores (2FA)</h2>
                <?php if ($is_2fa_enabled): ?>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-green-600">Estado: Ativo</p>
                            <p class="text-sm text-gray-600">A sua conta está protegida com 2FA.</p>
                        </div>
                        <form action="perfil.php" method="POST">
                            <input type="hidden" name="action" value="disable_2fa">
                            <button type="submit" class="bg-red-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-600">Desativar</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-600">Estado: Inativo</p>
                            <p class="text-sm text-gray-600">Aumente a segurança da sua conta.</p>
                        </div>
                        <form action="ativar_2fa.php" method="POST">
                             <button type="submit" class="bg-green-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-600">Ativar 2FA</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-6 border-b pb-4">Alterar Password</h2>
                <form action="perfil.php" method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="change_password">
                    <div class="text-right">
                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Guardar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>