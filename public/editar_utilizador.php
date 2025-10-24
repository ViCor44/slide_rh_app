<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Apenas Admins podem aceder a esta página
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN) {
    header('Location: acesso_negado.php');
    exit;
}

// Validar o ID do utilizador a ser editado
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: gerir_utilizadores.php");
    exit;
}
$user_id_a_editar = (int)$_GET['id'];

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo_role_id = $_POST['role_id'] ?? null;
    if ($novo_role_id) {
        try {
            $stmt = $pdo->prepare("UPDATE utilizadores SET role_id = ? WHERE id = ?");
            $stmt->execute([$novo_role_id, $user_id_a_editar]);
            header("Location: gerir_utilizadores.php"); // Redireciona de volta após o sucesso
            exit;
        } catch (PDOException $e) {
            $error = "Erro ao atualizar a função do utilizador.";
        }
    }
}

// Ir buscar os dados do utilizador e a lista de roles
try {
    $stmt_user = $pdo->prepare("SELECT u.id, u.email, u.role_id, COALESCE(f.nome_completo, u.nome) AS nome_final FROM utilizadores u LEFT JOIN funcionarios f ON u.funcionario_id = f.id WHERE u.id = ?");
    $stmt_user->execute([$user_id_a_editar]);
    $user = $stmt_user->fetch();

    $roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: gerir_utilizadores.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados.");
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Editar Utilizador - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>
    <main class="p-8">
        <div class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Editar Utilizador</h1>
            <p class="text-gray-600 mb-6">A alterar a função de <strong class="font-medium"><?= htmlspecialchars($user['nome_final']) ?></strong>.</p>

            <form action="editar_utilizador.php?id=<?= $user_id_a_editar ?>" method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" class="mt-1 block w-full bg-gray-100 px-4 py-2 border rounded-md" disabled>
                </div>
                <div class="mb-6">
                    <label for="role_id" class="block text-sm font-medium text-gray-700">Função (Role)</label>
                    <select name="role_id" id="role_id" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= (int)$user['role_id'] === (int)$role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['nome_role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-4">
                    <a href="gerir_utilizadores.php" class="bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300">Cancelar</a>
                    <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Guardar Alterações</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>