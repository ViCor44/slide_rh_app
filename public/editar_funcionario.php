<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/logger.php';

$errors = [];
$successMessage = '';

// 1. Validar o ID do funcionário a ser editado, vindo do URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: listar_funcionarios.php");
    exit;
}
$funcionario_id_a_editar = (int)$_GET['id'];

// 2. Ir buscar os dados principais do funcionário a ser editado (para sabermos o seu departamento)
try {
    $stmt_check = $pdo->prepare("SELECT * FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
    $stmt_check->execute([$funcionario_id_a_editar]);
    $funcionario = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$funcionario) {
        // Se o funcionário não existe, volta para a lista
        header("Location: listar_funcionarios.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados do funcionário: " . $e->getMessage());
}


// ==========================================================
// == INÍCIO DO NOVO BLOCO DE SEGURANÇA REFORÇADO          ==
// ==========================================================
$departamento_alvo = $funcionario['departamento'];
$logged_in_role_id = (int)($utilizador_logado['role_id'] ?? 0);

$pode_editar = false; 

// Regra 1: Admins e RH podem editar qualquer pessoa
if ($logged_in_role_id === ROLE_ADMIN || $logged_in_role_id === ROLE_RH) {
    $pode_editar = true;
}
// Regra 2: Managers podem editar pessoas APENAS do seu próprio departamento
elseif ($logged_in_role_id === ROLE_MANAGER) {
    if (isset($_SESSION['user_departamento']) && $_SESSION['user_departamento'] === $departamento_alvo) {
        $pode_editar = true;
    }
}

// Decisão final
if (!$pode_editar) {
    header('Location: acesso_negado.php');
    exit;
}
// ==========================================================
// == FIM DO BLOCO DE SEGURANÇA                            ==
// ==========================================================


// 3. Se as permissões foram validadas, continuar com o processamento do POST (se existir)
// 2. PROCESSAR O FORMULÁRIO QUANDO FOR SUBMETIDO (MÉTODO POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter todos os dados do POST
    $numero_funcionario = trim($_POST['numero_funcionario'] ?? '');
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email_corporativo = trim($_POST['email_corporativo'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $sector_piscina = !empty($_POST['sector_piscina']) ? (int)$_POST['sector_piscina'] : null;
    $data_contratacao = trim($_POST['data_contratacao'] ?? '');
    $nfc_card_id = trim($_POST['nfc_card_id'] ?? '');
    $status_servico = trim($_POST['status_servico'] ?? 'Ao Serviço');

    $nif = trim($_POST['nif'] ?? '');
    $nss = trim($_POST['nss'] ?? '');
    $cartao_cidadao = trim($_POST['cartao_cidadao'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telemovel = trim($_POST['telemovel'] ?? '');
    $morada_completa = trim($_POST['morada_completa'] ?? '');
    $iban = trim($_POST['iban'] ?? '');

    // Validações (copiar as validações do adicionar_funcionario.php, se necessário)
    if (empty($nome_completo)) $errors['nome_completo'] = 'O nome completo é obrigatório.';
    if (empty($numero_funcionario)) $errors['numero_funcionario'] = 'O número de funcionário é obrigatório.';
    // ... adicionar outras validações ...

    // Validação da nova foto (se existir)
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp_name = $_FILES['foto']['tmp_name'];
        $foto_size = $_FILES['foto']['size'];
        $foto_type = mime_content_type($foto_tmp_name);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        if ($foto_size > 2097152) { $errors['foto'] = 'A foto não pode exceder 2MB.'; }
        if (!in_array($foto_type, $allowed_types)) { $errors['foto'] = 'Formato inválido. Apenas JPG, PNG e GIF.';}
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // 1. Atualizar a tabela `funcionarios` (informação geral)
            $sql1 = "UPDATE funcionarios SET 
                        numero_funcionario = ?, 
                        nome_completo = ?, 
                        email_corporativo = ?, 
                        cargo = ?, 
                        departamento = ?, 
                        sector_piscina = ?, 
                        data_contratacao = ?, 
                        nfc_card_id = ?, 
                        status_servico = ? 
                     WHERE id = ?";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([
                $numero_funcionario, 
                $nome_completo, 
                $email_corporativo, 
                $cargo, 
                $departamento, 
                $sector_piscina, 
                $data_contratacao, 
                $nfc_card_id, 
                $status_servico, 
                $funcionario_id_a_editar // ID do funcionário vindo do GET
            ]);

            // 2. Atualizar a tabela `funcionarios_dados_pessoais` (dados sensíveis)
            // SÓ EXECUTAR SE O UTILIZADOR FOR ADMIN OU RH
            if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH) {
                // Verificação extra para garantir que os campos foram enviados antes de tentar usá-los
                // (necessário porque estes campos podem não existir no formulário para um Manager)
                if (isset($_POST['nif'])) { 
                    $sql2 = "UPDATE funcionarios_dados_pessoais SET
                                nif = ?, 
                                nss = ?, 
                                cartao_cidadao = ?, 
                                data_nascimento = ?, 
                                telemovel = ?, 
                                morada_completa = ?, 
                                iban = ?
                             WHERE funcionario_id = ?";
                    $stmt2 = $pdo->prepare($sql2);
                    $stmt2->execute([
                        $nif, 
                        $nss, 
                        $cartao_cidadao, 
                        $data_nascimento, 
                        $telemovel, 
                        $morada_completa, 
                        $iban,
                        $funcionario_id_a_editar // ID do funcionário vindo do GET
                    ]);
                }
            }
            
            // 3. Lógica para atualizar a foto se uma nova foi enviada
            if (isset($foto_tmp_name)) {
                $foto_extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $novo_nome_foto = $funcionario_id_a_editar . '.' . strtolower($foto_extension);
                $caminho_destino = realpath(__DIR__ . '/../storage/fotos_funcionarios') . '/' . $novo_nome_foto;

                // (Opcional: apagar a foto antiga antes de mover a nova)

                if (move_uploaded_file($foto_tmp_name, $caminho_destino)) {
                    $sql_update_foto = "UPDATE funcionarios SET foto_path = ? WHERE id = ?";
                    $stmt_update = $pdo->prepare($sql_update_foto);
                    $stmt_update->execute([$novo_nome_foto, $funcionario_id_a_editar]);
                } else {
                    throw new Exception("Não foi possível mover o novo ficheiro da foto.");
                }
            }

            $pdo->commit();
            $successMessage = "Dados do funcionário atualizados com sucesso!";
            
            // Recarregar os dados para mostrar as alterações no formulário (Redirecionar)
            header("Location: editar_funcionario.php?id=" . $funcionario_id_a_editar . "&status=success");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = "Erro ao atualizar o funcionário: " . $e->getMessage();
            // Recarregar os dados do funcionário para preencher o formulário novamente após erro
            $stmt = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
            $stmt->execute([$funcionario_id_a_editar]);
            $funcionario = $stmt->fetch(PDO::FETCH_ASSOC); // Sobrescreve $funcionario com os dados atuais
        }
    } else {
         // Se houver erros de validação, recarrega os dados do funcionário para preencher o formulário
        $stmt = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
        $stmt->execute([$funcionario_id_a_editar]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Funcionário - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-6 mb-8 pb-6 border-b">
            <img class="h-24 w-24 rounded-full object-cover shadow-md flex-shrink-0" src="mostrar_foto.php?id=<?= $funcionario['id'] ?>" alt="Foto de <?= htmlspecialchars($funcionario['nome_completo']) ?>">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Editar Funcionário</h1>
                <p class="text-gray-600 mt-1">A alterar os dados de: <strong class="font-medium"><?= htmlspecialchars($funcionario['nome_completo']) ?></strong></p>
            </div>
        </div>
        
        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <p><?= htmlspecialchars($successMessage) ?> <a href="listar_funcionarios.php" class="font-bold underline hover:text-green-800">Voltar à lista</a>.</p>
            </div>
        <?php endif; ?>

        <form action="editar_funcionario.php?id=<?= $funcionario['id'] ?>" method="POST" enctype="multipart/form-data">
            
            <fieldset class="mb-8">
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Informação Principal</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="numero_funcionario" class="block text-sm font-medium text-gray-700 mb-1">Nº de Funcionário</label>
                        <input type="text" id="numero_funcionario" name="numero_funcionario" value="<?= htmlspecialchars($funcionario['numero_funcionario'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" required>
                    </div>
                    <div>
                        <label for="nome_completo" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                        <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($funcionario['nome_completo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" required>
                    </div>
                    <div>
                        <label for="email_corporativo" class="block text-sm font-medium text-gray-700 mb-1">Email Corporativo</label>
                        <input type="email" id="email_corporativo" name="email_corporativo" value="<?= htmlspecialchars($funcionario['email_corporativo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" required>
                    </div>
                    <div>
                        <label for="cargo" class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                        <input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($funcionario['cargo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" required>
                    </div>
                    <div>
                        <label for="departamento" class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <input type="text" id="departamento" name="departamento" value="<?= htmlspecialchars($funcionario['departamento'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" required>
                    </div>
                    <div>
                        <label for="data_contratacao" class="block text-sm font-medium text-gray-700 mb-1">Data de Contratação</label>
                        <input type="date" id="data_contratacao" name="data_contratacao" value="<?= htmlspecialchars($funcionario['data_contratacao'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" required>
                    </div>
                    <div>
                        <label for="sector_piscina" class="block text-sm font-medium text-gray-700 mb-1">Sector (Piscinas)</label>
                        <input type="number" id="sector_piscina" name="sector_piscina" value="<?= htmlspecialchars($funcionario['sector_piscina'] ?? '') ?>" class="w-full ..." min="1">
                        <p class="text-xs text-gray-500 mt-1">Apenas se Departamento = Piscinas.</p>
                    </div>

                    <div>
                        <label for="status_servico" class="block text-sm font-medium text-gray-700 mb-1">Status de Serviço</label>
                        <select id="status_servico" name="status_servico" class="w-full ...">
                            <option value="Ao Serviço" <?= ($funcionario['status_servico'] == 'Ao Serviço') ? 'selected' : '' ?>>Ao Serviço</option>
                            <option value="Baixa Médica" <?= ($funcionario['status_servico'] == 'Baixa Médica') ? 'selected' : '' ?>>Baixa Médica</option>
                            <option value="Férias" <?= ($funcionario['status_servico'] == 'Férias') ? 'selected' : '' ?>>Férias</option>
                            <option value="Licença" <?= ($funcionario['status_servico'] == 'Licença') ? 'selected' : '' ?>>Licença</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="nfc_card_id" class="block text-sm font-medium text-gray-700 mb-1">ID Cartão NFC</label>
                        <input type="text" id="nfc_card_id" name="nfc_card_id" value="<?= htmlspecialchars($funcionario['nfc_card_id'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                    <div>
                        <label for="foto" class="block text-sm font-medium text-gray-700 mb-1">Alterar Foto</label>
                        <input type="file" id="foto" name="foto" accept="image/png, image/jpeg, image/gif" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                    </div>
                </div>
            </fieldset>

            <?php if ((int)$utilizador_logado['role_id'] === ROLE_ADMIN || (int)$utilizador_logado['role_id'] === ROLE_RH): ?>
             <fieldset>
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Informação Pessoal (Acesso Restrito)</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="cartao_cidadao" class="block text-sm font-medium text-gray-700 mb-1">Nº Cartão de Cidadão</label>
                        <input type="text" id="cartao_cidadao" name="cartao_cidadao" value="<?= htmlspecialchars($funcionario['cartao_cidadao'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>  
                    
                    <div>
                        <label for="nif" class="block text-sm font-medium text-gray-700 mb-1">NIF</label>
                        <input type="text" id="nif" name="nif" value="<?= htmlspecialchars($funcionario['nif'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                    <div>
                        <label for="nss" class="block text-sm font-medium text-gray-700 mb-1">Nº Segurança Social</label>
                        <input type="text" id="nss" name="nss" value="<?= htmlspecialchars($funcionario['nss'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                    <div>
                        <label for="data_nascimento" class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                        <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($funcionario['data_nascimento'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                    <div>
                        <label for="telemovel" class="block text-sm font-medium text-gray-700 mb-1">Telemóvel</label>
                        <input type="tel" id="telemovel" name="telemovel" value="<?= htmlspecialchars($funcionario['telemovel'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="iban" class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                        <input type="text" id="iban" name="iban" value="<?= htmlspecialchars($funcionario['iban'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                </div>
                <div class="mt-6">
                    <label for="morada_completa" class="block text-sm font-medium text-gray-700 mb-1">Morada Completa</label>
                    <textarea id="morada_completa" name="morada_completa" rows="3" class="w-full px-4 py-2 border rounded-md"><?= htmlspecialchars($funcionario['morada_completa'] ?? '') ?></textarea>
                </div>
             </fieldset>
            <?php endif; ?>

            <div class="mt-8 flex justify-end gap-4">
                <a href="listar_funcionarios.php" class="bg-gray-200 text-gray-800 font-bold py-3 px-6 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-blue-700 transition-colors">
                    Guardar Alterações
                </button>
            </div>
        </form>

    </div>
    </main>
</body>
</html>