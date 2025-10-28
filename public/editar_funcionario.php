<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/logger.php';

$errors = [];
$successMessage = '';

// 1. Validar o ID do funcionário a ser editado
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: listar_funcionarios.php");
    exit;
}
$funcionario_id_a_editar = (int)$_GET['id'];

// 2. Carregar dados do funcionário
try {
    $stmt_check = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
    $stmt_check->execute([$funcionario_id_a_editar]);
    $funcionario = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$funcionario) {
        header("Location: listar_funcionarios.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados do funcionário: " . $e->getMessage());
}

// Carregar folgas semanais
$folgas = [];
$stmt_folgas = $pdo->prepare("SELECT dia_semana FROM folgas_semanais WHERE funcionario_id = ?");
$stmt_folgas->execute([$funcionario_id_a_editar]);
while ($row = $stmt_folgas->fetch(PDO::FETCH_ASSOC)) {
    $folgas[] = $row['dia_semana'];
}

// Carregar períodos de férias
$ferias = [];
$stmt_ferias = $pdo->prepare("SELECT * FROM periodos_ferias WHERE funcionario_id = ? ORDER BY data_inicio_ferias ASC");
$stmt_ferias->execute([$funcionario_id_a_editar]);
$ferias = $stmt_ferias->fetchAll(PDO::FETCH_ASSOC);

// ==========================================================
// == BLOCO DE SEGURANÇA REFORÇADO                         ==
// ==========================================================
$departamento_alvo = $funcionario['departamento'];
$logged_in_role_id = (int)($utilizador_logado['role_id'] ?? 0);
$pode_editar = false;

if ($logged_in_role_id === ROLE_ADMIN || $logged_in_role_id === ROLE_RH) {
    $pode_editar = true;
} elseif ($logged_in_role_id === ROLE_MANAGER) {
    if (isset($_SESSION['user_departamento']) && $_SESSION['user_departamento'] === $departamento_alvo) {
        $pode_editar = true;
    }
}

if (!$pode_editar) {
    header('Location: acesso_negado.php');
    exit;
}
// ==========================================================

// 3. PROCESSAR FORMULÁRIO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Dados do POST ---
    $numero_funcionario = trim($_POST['numero_funcionario'] ?? '');
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email_corporativo = trim($_POST['email_corporativo'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $sector_piscina = !empty($_POST['sector_piscina']) ? (int)$_POST['sector_piscina'] : null;
    $data_contratacao = trim($_POST['data_contratacao'] ?? '');
    $data_fim_contrato = !empty($_POST['data_fim_contrato']) ? trim($_POST['data_fim_contrato']) : null;
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
    $nfc_card_id = trim($_POST['nfc_card_id'] ?? '');

    $nif = trim($_POST['nif'] ?? '');
    $nss = trim($_POST['nss'] ?? '');
    $cartao_cidadao = trim($_POST['cartao_cidadao'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telemovel = trim($_POST['telemovel'] ?? '');
    $morada_completa = trim($_POST['morada_completa'] ?? '');
    $iban = trim($_POST['iban'] ?? '');

    $folgas = $_POST['folgas'] ?? []; // Array de dias para folgas

    // Novos períodos de férias (arrays para múltiplos)
    $novas_ferias_inicio = $_POST['nova_data_inicio_ferias'] ?? [];
    $novas_ferias_fim = $_POST['nova_data_fim_ferias'] ?? [];
    $novas_ano_referencia = $_POST['nova_ano_referencia'] ?? [];

    // --- Validações básicas ---
    if (empty($nome_completo)) $errors['nome_completo'] = 'O nome completo é obrigatório.';
    if (empty($numero_funcionario)) $errors['numero_funcionario'] = 'O número de funcionário é obrigatório.';
    if (empty($email_corporativo)) $errors['email_corporativo'] = 'O email corporativo é obrigatório.';
    if (empty($cargo)) $errors['cargo'] = 'O cargo é obrigatório.';
    if (empty($departamento)) $errors['departamento'] = 'O departamento é obrigatório.';
    if (empty($data_contratacao)) $errors['data_contratacao'] = 'A data de contratação é obrigatória.';

    // --- Validação da Foto ---
    $foto_error = null;
    $foto_tmp_name = null;
    $foto_final_path = $funcionario['foto_path'] ?? null; // Manter antiga se não houver nova

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp_name = $_FILES['foto']['tmp_name'];
        $foto_size = $_FILES['foto']['size'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $foto_mime_type = finfo_file($finfo, $foto_tmp_name);
        finfo_close($finfo);

        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_file_size = 2 * 1024 * 1024; // 2MB

        if ($foto_size > $max_file_size) {
            $foto_error = 'A foto não pode exceder 2MB.';
        } elseif (!in_array($foto_mime_type, $allowed_mime_types)) {
            $foto_error = 'Formato inválido. Apenas JPG, PNG e GIF.';
        }

        if ($foto_error) {
            $errors['foto'] = $foto_error;
            $foto_tmp_name = null;
        }
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $errors['foto'] = 'Erro no upload da foto. Código: ' . $_FILES['foto']['error'];
    }

    // Validação para novos períodos de férias
    for ($i = 0; $i < count($novas_ferias_inicio); $i++) {
        if (!empty($novas_ferias_inicio[$i]) || !empty($novas_ferias_fim[$i])) {
            if (empty($novas_ferias_inicio[$i])) $errors["nova_data_inicio_ferias_$i"] = 'Data de início obrigatória.';
            if (empty($novas_ferias_fim[$i])) $errors["nova_data_fim_ferias_$i"] = 'Data de fim obrigatória.';
            if (!empty($novas_ferias_inicio[$i]) && !empty($novas_ferias_fim[$i]) && strtotime($novas_ferias_inicio[$i]) >= strtotime($novas_ferias_fim[$i])) {
                $errors["nova_data_fim_ferias_$i"] = 'Data de fim deve ser após a início.';
            }
        }
    }

    // --- Se não houver erros, processar ---
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // 1. Atualizar tabela `funcionarios`
            $sql1 = "UPDATE funcionarios SET 
                        numero_funcionario = ?, 
                        nome_completo = ?, 
                        email_corporativo = ?, 
                        cargo = ?, 
                        departamento = ?, 
                        sector_piscina = ?, 
                        data_contratacao = ?,
                        data_fim_contrato = ?,
                        ativo = ?, 
                        nfc_card_id = ?
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
                $data_fim_contrato,
                $ativo,
                $nfc_card_id,
                $funcionario_id_a_editar
            ]);

            // 2. Atualizar dados pessoais (APENAS ADMIN/RH)
            if (($logged_in_role_id === ROLE_ADMIN || $logged_in_role_id === ROLE_RH) && isset($_POST['nif'])) {
                $sql2 = "UPDATE funcionarios_dados_pessoais SET
                            nif = ?, nss = ?, cartao_cidadao = ?, data_nascimento = ?,
                            telemovel = ?, morada_completa = ?, iban = ?
                         WHERE funcionario_id = ?";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([
                    $nif, $nss, $cartao_cidadao, $data_nascimento,
                    $telemovel, $morada_completa, $iban, $funcionario_id_a_editar
                ]);
            }

            // 3. Processar upload da foto (se válida)
            if ($foto_tmp_name) {
                $upload_dir = '../storage/fotos_funcionarios/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $ext = strtolower($ext);
                $novo_nome = 'foto_' . $funcionario_id_a_editar . '_' . time() . '.' . $ext;
                $caminho_fisico = $upload_dir . $novo_nome;

                if (move_uploaded_file($foto_tmp_name, $caminho_fisico)) {
                    // Apagar foto antiga
                    if (!empty($funcionario['foto_path']) && file_exists($upload_dir . $funcionario['foto_path'])) {
                        @unlink($upload_dir . $funcionario['foto_path']);
                    }
                    $foto_final_path = $novo_nome;
                } else {
                    throw new Exception("Falha ao mover a foto para o servidor.");
                }
            }

            // 4. Atualizar caminho da foto na BD
            $sql_foto = "UPDATE funcionarios SET foto_path = ? WHERE id = ?";
            $stmt_foto = $pdo->prepare($sql_foto);
            $stmt_foto->execute([$foto_final_path, $funcionario_id_a_editar]);

            // 5. Atualizar folgas semanais: Apagar existentes e inserir novas
            $sql_delete_folgas = "DELETE FROM folgas_semanais WHERE funcionario_id = ?";
            $stmt_delete_folgas = $pdo->prepare($sql_delete_folgas);
            $stmt_delete_folgas->execute([$funcionario_id_a_editar]);

            if (!empty($folgas)) {
                $sql_insert_folga = "INSERT INTO folgas_semanais (funcionario_id, dia_semana) VALUES (?, ?)";
                $stmt_insert_folga = $pdo->prepare($sql_insert_folga);
                foreach ($folgas as $dia) {
                    $stmt_insert_folga->execute([$funcionario_id_a_editar, (int)$dia]);
                }
            }

            // 6. Adicionar novos períodos de férias
            if (!empty($novas_ferias_inicio)) {
                $sql_insert_ferias = "INSERT INTO periodos_ferias (funcionario_id, data_inicio_ferias, data_fim_ferias, ano_referencia, aprovado) VALUES (?, ?, ?, ?, 1)";
                $stmt_insert_ferias = $pdo->prepare($sql_insert_ferias);
                for ($i = 0; $i < count($novas_ferias_inicio); $i++) {
                    if (!empty($novas_ferias_inicio[$i]) && !empty($novas_ferias_fim[$i])) {
                        $stmt_insert_ferias->execute([$funcionario_id_a_editar, $novas_ferias_inicio[$i], $novas_ferias_fim[$i], $novas_ano_referencia[$i] ?? date('Y')]);
                    }
                }
            }

            $pdo->commit();
            $successMessage = "Dados do funcionário atualizados com sucesso!";
            header("Location: editar_funcionario.php?id=" . $funcionario_id_a_editar . "&status=success");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = "Erro ao salvar: " . $e->getMessage();
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
            $stmt->execute([$funcionario_id_a_editar]);
            $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt_folgas->execute([$funcionario_id_a_editar]);
            $folgas = [];
            while ($row = $stmt_folgas->fetch(PDO::FETCH_ASSOC)) {
                $folgas[] = $row['dia_semana'];
            }

            $ferias = $stmt_ferias->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Recarregar dados em caso de erro de validação
        $stmt = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
        $stmt->execute([$funcionario_id_a_editar]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt_folgas->execute([$funcionario_id_a_editar]);
        $folgas = [];
        while ($row = $stmt_folgas->fetch(PDO::FETCH_ASSOC)) {
            $folgas[] = $row['dia_semana'];
        }

        $ferias = $stmt_ferias->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Recarregar funcionário após sucesso (para exibir dados atualizados)
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $stmt = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id WHERE f.id = ?");
    $stmt->execute([$funcionario_id_a_editar]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_folgas->execute([$funcionario_id_a_editar]);
    $folgas = [];
    while ($row = $stmt_folgas->fetch(PDO::FETCH_ASSOC)) {
        $folgas[] = $row['dia_semana'];
    }

    $ferias = $stmt_ferias->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Funcionário - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
    <script>
        function adicionarFerias() {
            const container = document.getElementById('novas_ferias_container');
            const index = container.children.length;
            const html = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                        <input type="date" name="nova_data_inicio_ferias[${index}]" class="w-full px-4 py-2 border rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                        <input type="date" name="nova_data_fim_ferias[${index}]" class="w-full px-4 py-2 border rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ano Referência</label>
                        <input type="number" name="nova_ano_referencia[${index}]" value="${new Date().getFullYear()}" class="w-full px-4 py-2 border rounded-md border-gray-300" min="1900" max="2100">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    </script>
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

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $erro): ?>
                        <li><?= htmlspecialchars($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
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
                        <label for="data_fim_contrato" class="block text-sm font-medium text-gray-700 mb-1">Data Fim Contrato</label>
                        <input type="date" id="data_fim_contrato" name="data_fim_contrato" value="<?= htmlspecialchars($funcionario['data_fim_contrato'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md">
                    </div>
                    <div>
                        <label for="ativo" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="ativo" name="ativo" class="w-full px-4 py-2 border border-gray-300 rounded-md">
                            <option value="1" <?= ($funcionario['ativo'] == 1) ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= ($funcionario['ativo'] == 0) ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <div>
                        <label for="sector_piscina" class="block text-sm font-medium text-gray-700 mb-1">Sector (Piscinas)</label>
                        <input type="number" id="sector_piscina" name="sector_piscina" value="<?= htmlspecialchars($funcionario['sector_piscina'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md" min="1">
                        <p class="text-xs text-gray-500 mt-1">Apenas se Departamento = Piscinas.</p>
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

            <fieldset class="mb-8">
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Folgas Semanais</legend>
                <div class="flex flex-row gap-4 flex-wrap">
                    <?php $dias = ['Domingo' => 0, 'Segunda' => 1, 'Terça' => 2, 'Quarta' => 3, 'Quinta' => 4, 'Sexta' => 5, 'Sábado' => 6]; ?>
                    <?php foreach ($dias as $dia_nome => $dia_num): ?>
                        <div class="flex items-center">
                            <input type="checkbox" id="folga_<?= $dia_num ?>" name="folgas[]" value="<?= $dia_num ?>" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array($dia_num, $form_data['folgas'] ?? []) ? 'checked' : '' ?>>
                            <label for="folga_<?= $dia_num ?>" class="ml-2 text-sm text-gray-700"><?= $dia_nome ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="mb-8">
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Períodos de Férias</legend>
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Períodos Existentes</h3>
                    <?php if (empty($ferias)): ?>
                        <p class="text-gray-500">Nenhum período de férias registado.</p>
                    <?php else: ?>
                        <table class="min-w-full bg-white border border-gray-300">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 border-b">Início</th>
                                    <th class="px-4 py-2 border-b">Fim</th>
                                    <th class="px-4 py-2 border-b">Ano</th>
                                    <th class="px-4 py-2 border-b">Aprovado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ferias as $feria): ?>
                                    <tr>
                                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($feria['data_inicio_ferias']) ?></td>
                                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($feria['data_fim_ferias']) ?></td>
                                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($feria['ano_referencia']) ?></td>
                                        <td class="px-4 py-2 border-b"><?= $feria['aprovado'] ? 'Sim' : 'Não' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Adicionar Novo Período</h3>
                    <div id="novas_ferias_container">
                        <!-- Inicial com um campo -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                                <input type="date" name="nova_data_inicio_ferias[0]" class="w-full px-4 py-2 border rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                                <input type="date" name="nova_data_fim_ferias[0]" class="w-full px-4 py-2 border rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ano Referência</label>
                                <input type="number" name="nova_ano_referencia[0]" value="<?= date('Y') ?>" class="w-full px-4 py-2 border rounded-md border-gray-300" min="1900" max="2100">
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="adicionarFerias()" class="mt-4 bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600">Adicionar Outro Período</button>
                </div>
            </fieldset>

            <?php if ($logged_in_role_id === ROLE_ADMIN || $logged_in_role_id === ROLE_RH): ?>
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