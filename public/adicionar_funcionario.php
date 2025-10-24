<?php
require_once '../config/db.php';

$errors = [];
$successMessage = '';

// Valores pré-preenchidos para manter os dados no formulário em caso de erro
$form_data = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Obter e limpar os dados de ambas as tabelas
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $numero_funcionario = trim($_POST['numero_funcionario'] ?? '');
    $email_corporativo = trim($_POST['email_corporativo'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $data_contratacao = trim($_POST['data_contratacao'] ?? '');
    $nfc_card_id = trim($_POST['nfc_card_id'] ?? '');
    
    $cartao_cidadao = trim($_POST['cartao_cidadao'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $nss = trim($_POST['nss'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telemovel = trim($_POST['telemovel'] ?? '');
    $morada_completa = trim($_POST['morada_completa'] ?? '');
    $iban = trim($_POST['iban'] ?? '');

    // Validação da Foto
    $foto_path = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp_name = $_FILES['foto']['tmp_name'];
        $foto_size = $_FILES['foto']['size'];
        $foto_type = mime_content_type($foto_tmp_name);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        if ($foto_size > 2097152) { // 2MB
            $errors['foto'] = 'A foto não pode exceder 2MB.';
        }
        if (!in_array($foto_type, $allowed_types)) {
            $errors['foto'] = 'Formato de ficheiro inválido. Apenas JPG, PNG e GIF são permitidos.';
        }
    }

    // 2. Validação mais completa
    if (empty($nome_completo)) $errors['nome_completo'] = 'O nome completo é obrigatório.';
    if (empty($numero_funcionario)) $errors['numero_funcionario'] = 'O número de funcionário é obrigatório.';
    if (empty($cargo)) $errors['cargo'] = 'O cargo é obrigatório.';
    if (empty($departamento)) $errors['departamento'] = 'O departamento é obrigatório.';
    if (empty($data_contratacao)) $errors['data_contratacao'] = 'A data de contratação é obrigatória.';
    if (!filter_var($email_corporativo, FILTER_VALIDATE_EMAIL)) $errors['email_corporativo'] = 'O formato do email é inválido.';
    // Adicionar mais validações (ex: formato do NIF, IBAN, etc.) aqui se necessário

    // 3. Se não houver erros, inserir na BD
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Inserir na tabela `funcionarios`            
            $sql1 = "INSERT INTO funcionarios 
                        (numero_funcionario, nome_completo, email_corporativo, cargo, departamento, data_contratacao, nfc_card_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
            $stmt1 = $pdo->prepare($sql1); // <-- ESTA É A LINHA QUE FALTAVA
            $stmt1->execute([$numero_funcionario, $nome_completo, $email_corporativo, $cargo, $departamento, $data_contratacao, $nfc_card_id]);

            $funcionario_id = $pdo->lastInsertId();

            // Inserir na tabela `funcionarios_dados_pessoais`
            $sql2 = "INSERT INTO funcionarios_dados_pessoais (funcionario_id, nif, nss, cartao_cidadao, data_nascimento, telemovel, morada_completa, iban) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$funcionario_id, $nif, $nss, $cartao_cidadao, $data_nascimento, $telemovel, $morada_completa, $iban]);

            if (isset($foto_tmp_name)) {
                // Criar um nome de ficheiro único usando o ID do funcionário
                $foto_extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $novo_nome_foto = $funcionario_id . '.' . strtolower($foto_extension);
                
                // Construir o caminho completo e seguro para o destino
                $caminho_destino = realpath(__DIR__ . '/../storage/fotos_funcionarios') . '/' . $novo_nome_foto;

                // Mover o ficheiro temporário para o destino final
                if (move_uploaded_file($foto_tmp_name, $caminho_destino)) {
                    // Se o upload foi bem sucedido, atualiza o registo do funcionário com o nome da foto
                    $sql_update_foto = "UPDATE funcionarios SET foto_path = ? WHERE id = ?";
                    $stmt_update = $pdo->prepare($sql_update_foto);
                    $stmt_update->execute([$novo_nome_foto, $funcionario_id]);
                } else {
                    // Se não for possível mover o ficheiro, lança um erro para cancelar a transação
                    throw new Exception("Não foi possível mover o ficheiro da foto para o destino.");
                }
            }

            $pdo->commit();

            $successMessage = "Funcionário '$nome_completo' registado com sucesso!";
            $form_data = []; // Limpar o formulário após sucesso

        } catch (PDOException $e) {
            $pdo->rollBack();
            // Verificar se é um erro de duplicado (UNIQUE constraint)
            if ($e->getCode() == 23000) {
                 $errors['db'] = "Erro: O Email ou o ID do Cartão NFC já existem na base de dados.";
            } else {
                 $errors['db'] = "Erro ao registar o funcionário: " . $e->getMessage();
            }
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
                        <label for="data_contratacao" class="block text-sm font-medium text-gray-700 mb-1">Data de Contratação</label>
                        <input type="date" id="data_contratacao" name="data_contratacao" value="<?= htmlspecialchars($form_data['data_contratacao'] ?? '') ?>" class="w-full px-4 py-2 border rounded-md <?= isset($errors['data_contratacao']) ? 'border-red-500' : 'border-gray-300' ?> focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if (isset($errors['data_contratacao'])): ?><p class="text-red-500 text-xs mt-1"><?= $errors['data_contratacao'] ?></p><?php endif; ?>
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