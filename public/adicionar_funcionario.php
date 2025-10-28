<?php
require_once '../src/auth_guard.php'; // Incluir auth_guard para $utilizador_logado
require_once '../config/db.php';
require_once '../src/logger.php'; // Incluir logger

$errors = [];
$successMessage = '';
$form_data = $_POST; // Manter dados em caso de erro

// Definir $funcionario_id_a_editar (não aplicável aqui, mas para consistência)
$funcionario_id_a_editar = null; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do POST
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
    $data_nascimento = !empty($_POST['data_nascimento']) ? trim($_POST['data_nascimento']) : null;
    $telemovel = trim($_POST['telemovel'] ?? '');
    $morada_completa = trim($_POST['morada_completa'] ?? '');
    $iban = trim($_POST['iban'] ?? '');

    $folgas = $_POST['folgas'] ?? []; // Array de dias da semana para folgas
    $data_inicio_ferias = trim($_POST['data_inicio_ferias'] ?? '');
    $data_fim_ferias = trim($_POST['data_fim_ferias'] ?? '');
    $ano_referencia = trim($_POST['ano_referencia'] ?? '');

    // Inicializar variáveis da foto
    $foto_tmp_name = null;
    $foto_name = null;

    // Validação da Foto (se foi enviada)
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp_name = $_FILES['foto']['tmp_name'];
        $foto_name = $_FILES['foto']['name']; // Guardar nome original para extensão
        $foto_size = $_FILES['foto']['size'];
        $foto_type = mime_content_type($foto_tmp_name);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        if ($foto_size > 2097152) { // 2MB Max
            $errors['foto'] = 'A foto não pode exceder 2MB.';
        }
        if (!in_array($foto_type, $allowed_types)) {
            $errors['foto'] = 'Formato inválido. Apenas JPG, PNG e GIF.';
        }
    }

    // Outras Validações (essenciais)
    if (empty($numero_funcionario)) $errors['numero_funcionario'] = 'O número de funcionário é obrigatório.';
    if (empty($nome_completo)) $errors['nome_completo'] = 'O nome completo é obrigatório.';
    if (empty($email_corporativo) || !filter_var($email_corporativo, FILTER_VALIDATE_EMAIL)) $errors['email_corporativo'] = 'Email corporativo inválido.';
    if (empty($cargo)) $errors['cargo'] = 'O cargo é obrigatório.';
    if (empty($departamento)) $errors['departamento'] = 'O departamento é obrigatório.';
    if (empty($data_contratacao)) $errors['data_contratacao'] = 'Data de contratação obrigatória.';
    // Adicione mais validações conforme necessário (NIF, NSS, etc.)

    // Validação para férias iniciais (opcional, mas se preenchido, validar)
    if (!empty($data_inicio_ferias) || !empty($data_fim_ferias)) {
        if (empty($data_inicio_ferias)) $errors['data_inicio_ferias'] = 'Data de início de férias obrigatória se preenchido.';
        if (empty($data_fim_ferias)) $errors['data_fim_ferias'] = 'Data de fim de férias obrigatória se preenchido.';
        if (!empty($data_inicio_ferias) && !empty($data_fim_ferias) && strtotime($data_inicio_ferias) >= strtotime($data_fim_ferias)) {
            $errors['data_fim_ferias'] = 'Data de fim deve ser após a data de início.';
        }
    }

    // Se não houver erros, proceder com a inserção
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // 1. Inserir dados na tabela `funcionarios` (sem foto_path ainda)
            $sql1 = "INSERT INTO funcionarios 
                        (numero_funcionario, nome_completo, email_corporativo, cargo, departamento, sector_piscina, data_contratacao, data_fim_contrato, ativo, nfc_card_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 10 placeholders
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([
                $numero_funcionario, $nome_completo, $email_corporativo, $cargo, $departamento, 
                $sector_piscina, $data_contratacao, $data_fim_contrato, $ativo, $nfc_card_id
            ]); // 10 variáveis
            
            $funcionario_id = $pdo->lastInsertId(); // Obter o ID do novo funcionário

            // 2. Inserir dados na tabela `funcionarios_dados_pessoais`
            $sql2 = "INSERT INTO funcionarios_dados_pessoais 
                        (funcionario_id, nif, nss, cartao_cidadao, data_nascimento, telemovel, morada_completa, iban) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; // 8 placeholders
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([
                $funcionario_id, $nif, $nss, $cartao_cidadao, $data_nascimento, $telemovel, $morada_completa, $iban
            ]); // 8 variáveis

            // 3. Processar e Mover a Foto (se foi enviada)
            $novo_nome_foto = null;
            if ($foto_tmp_name && $foto_name) {
                $upload_dir = '../storage/fotos_funcionarios/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext = pathinfo($foto_name, PATHINFO_EXTENSION);
                $ext = strtolower($ext);
                $novo_nome_foto = 'foto_' . $funcionario_id . '_' . time() . '.' . $ext;
                $caminho_final = $upload_dir . $novo_nome_foto;

                if (!move_uploaded_file($foto_tmp_name, $caminho_final)) {
                    throw new Exception("Falha ao salvar a foto no servidor.");
                }

                // 4. Atualizar o registo do funcionário com o nome da foto
                $sql_update_foto = "UPDATE funcionarios SET foto_path = ? WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update_foto);
                $stmt_update->execute([$novo_nome_foto, $funcionario_id]);
            }

            // 5. Inserir folgas semanais
            if (!empty($folgas)) {
                $sql_folga = "INSERT INTO folgas_semanais (funcionario_id, dia_semana) VALUES (?, ?)";
                $stmt_folga = $pdo->prepare($sql_folga);
                foreach ($folgas as $dia) {
                    $stmt_folga->execute([$funcionario_id, (int)$dia]);
                }
            }

            // 6. Inserir período de férias inicial (se preenchido)
            if (!empty($data_inicio_ferias) && !empty($data_fim_ferias)) {
                $sql_ferias = "INSERT INTO periodos_ferias (funcionario_id, data_inicio_ferias, data_fim_ferias, ano_referencia, aprovado) VALUES (?, ?, ?, ?, 1)";
                $stmt_ferias = $pdo->prepare($sql_ferias);
                $stmt_ferias->execute([$funcionario_id, $data_inicio_ferias, $data_fim_ferias, $ano_referencia]);
            }
            
            $pdo->commit(); // Confirmar tudo
            $successMessage = "Funcionário '$nome_completo' registado com sucesso!";
            
            // Registar no log
            log_event($pdo, 'INFO', 'EMPLOYEE_CREATED', "Novo funcionário '{$nome_completo}' (ID: {$funcionario_id}) foi registado.", $utilizador_logado['id']);

            // Limpar dados do POST para não repreencher o formulário
            $_POST = [];
            $form_data = []; // Limpar também esta variável

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = "Erro ao registar o funcionário: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Novo Funcionário - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">

    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Registar Novo Funcionário</h1>
        <p class="text-gray-600 mb-8">Preencha os dados para adicionar um novo membro à equipa.</p>

        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <p><?= htmlspecialchars($successMessage) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['db'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <p><?= htmlspecialchars($errors['db']) ?></p>
            </div>
        <?php endif; ?>

        <form action="adicionar_funcionario.php" method="POST" enctype="multipart/form-data">
            <fieldset class="mb-8">
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Informação Principal</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="nome_completo" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                        <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($form_data['nome_completo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['nome_completo']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['nome_completo'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['nome_completo'] ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="numero_funcionario" class="block text-sm font-medium text-gray-700 mb-1">Nº de Funcionário</label>
                        <input type="text" id="numero_funcionario" name="numero_funcionario" value="<?= htmlspecialchars($form_data['numero_funcionario'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['numero_funcionario']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['numero_funcionario'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['numero_funcionario'] ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="email_corporativo" class="block text-sm font-medium text-gray-700 mb-1">Email Corporativo</label>
                        <input type="email" id="email_corporativo" name="email_corporativo" value="<?= htmlspecialchars($form_data['email_corporativo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['email_corporativo']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['email_corporativo'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['email_corporativo'] ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="cargo" class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                        <input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($form_data['cargo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['cargo']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['cargo'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['cargo'] ?></p><?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="departamento" class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <input type="text" id="departamento" name="departamento" value="<?= htmlspecialchars($form_data['departamento'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['departamento']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['departamento'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['departamento'] ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="sector_piscina" class="block text-sm font-medium text-gray-700 mb-1">Sector (Piscinas)</label>
                        <input type="number" id="sector_piscina" name="sector_piscina" value="<?= htmlspecialchars($form_data['sector_piscina'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" min="1">
                        <p class="text-xs text-gray-500 mt-1">Apenas preencher se Departamento = Piscinas.</p>
                    </div>

                    <div>
                        <label for="data_contratacao" class="block text-sm font-medium text-gray-700 mb-1">Data de Contratação</label>
                        <input type="date" id="data_contratacao" name="data_contratacao" value="<?= htmlspecialchars($form_data['data_contratacao'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['data_contratacao']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['data_contratacao'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['data_contratacao'] ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="data_fim_contrato" class="block text-sm font-medium text-gray-700 mb-1">Data Fim Contrato</label>
                        <input type="date" id="data_fim_contrato" name="data_fim_contrato" value="<?= htmlspecialchars($form_data['data_fim_contrato'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label for="ativo" class="block text-sm font-medium text-gray-700 mb-1">Status Inicial</label>
                        <select id="ativo" name="ativo" class="w-full px-4 py-2 border border-gray-300 rounded-md">
                            <option value="1" selected>Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>

                    <div>
                        <label for="nfc_card_id" class="block text-sm font-medium text-gray-700 mb-1">ID Cartão NFC</label>
                        <input type="text" id="nfc_card_id" name="nfc_card_id" value="<?= htmlspecialchars($form_data['nfc_card_id'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label for="foto" class="block text-sm font-medium text-gray-700 mb-1">Foto do Funcionário</label>
                        <input type="file" id="foto" name="foto" accept="image/png, image/jpeg, image/gif" class="w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100
                        "/>
                        <?php if (isset($errors['foto'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['foto'] ?></p><?php endif; ?>
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
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Período de Férias Inicial (Opcional)</legend>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="data_inicio_ferias" class="block text-sm font-medium text-gray-700 mb-1">Data Início Férias</label>
                        <input type="date" id="data_inicio_ferias" name="data_inicio_ferias" value="<?= htmlspecialchars($form_data['data_inicio_ferias'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['data_inicio_ferias']) ? 'border-red-500' : 'border-gray-300' ?>">
                        <?php if (isset($errors['data_inicio_ferias'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['data_inicio_ferias'] ?></p><?php endif; ?>
                    </div>
                    <div>
                        <label for="data_fim_ferias" class="block text-sm font-medium text-gray-700 mb-1">Data Fim Férias</label>
                        <input type="date" id="data_fim_ferias" name="data_fim_ferias" value="<?= htmlspecialchars($form_data['data_fim_ferias'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['data_fim_ferias']) ? 'border-red-500' : 'border-gray-300' ?>">
                        <?php if (isset($errors['data_fim_ferias'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['data_fim_ferias'] ?></p><?php endif; ?>
                    </div>
                    <div>
                        <label for="ano_referencia" class="block text-sm font-medium text-gray-700 mb-1">Ano de Referência</label>
                        <input type="number" id="ano_referencia" name="ano_referencia" value="<?= htmlspecialchars($form_data['ano_referencia'] ?? date('Y')) ?>" class="w-full px-4 py-2 border rounded-md border-gray-300" min="1900" max="2100">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-700 mb-6 border-b pb-2">Informação Pessoal (Acesso Restrito)</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="cartao_cidadao" class="block text-sm font-medium text-gray-700 mb-1">Nº Cartão de Cidadão</label>
                        <input type="text" id="cartao_cidadao" name="cartao_cidadao" value="<?= htmlspecialchars($form_data['cartao_cidadao'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="nif" class="block text-sm font-medium text-gray-700 mb-1">NIF</label>
                        <input type="text" id="nif" name="nif" value="<?= htmlspecialchars($form_data['nif'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="nss" class="block text-sm font-medium text-gray-700 mb-1">Nº Segurança Social</label>
                        <input type="text" id="nss" name="nss" value="<?= htmlspecialchars($form_data['nss'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="data_nascimento" class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                        <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($form_data['data_nascimento'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="telemovel" class="block text-sm font-medium text-gray-700 mb-1">Telemóvel</label>
                        <input type="tel" id="telemovel" name="telemovel" value="<?= htmlspecialchars($form_data['telemovel'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="iban" class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                        <input type="text" id="iban" name="iban" value="<?= htmlspecialchars($form_data['iban'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                 <div class="mt-6">
                    <label for="morada_completa" class="block text-sm font-medium text-gray-700 mb-1">Morada Completa</label>
                    <textarea id="morada_completa" name="morada_completa" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($form_data['morada_completa'] ?? '') ?></textarea>
                </div>
            </fieldset>

            <div class="mt-8 flex justify-end">
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300">
                    Registar Funcionário
                </button>
            </div>
        </form>
    </div>
</body>
</html>