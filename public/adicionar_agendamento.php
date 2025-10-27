<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/logger.php';

// Permissões: Quem pode adicionar agendamentos?
$pode_agendar = (
    (int)$utilizador_logado['role_id'] === ROLE_ADMIN ||
    (int)$utilizador_logado['role_id'] === ROLE_RH ||
    (int)$utilizador_logado['role_id'] === ROLE_MANAGER ||
    (int)$utilizador_logado['role_id'] === ROLE_SUPERVISOR
);
if (!$pode_agendar) {
    header('Location: acesso_negado.php');
    exit;
}

$errors = [];
$successMessage = '';
$lista_funcionarios = []; // Inicializar

// Ir buscar a lista de funcionários para o dropdown
try {
    $stmt_funcionarios = $pdo->query("SELECT id, nome_completo, numero_funcionario FROM funcionarios WHERE ativo = 1 ORDER BY nome_completo ASC");
    $lista_funcionarios = $stmt_funcionarios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Erro ao carregar a lista de funcionários.";
}

// Processar o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario_ids = $_POST['funcionario_ids'] ?? [];
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo_evento = trim($_POST['tipo_evento'] ?? 'Geral');
    $data_inicio_str = trim($_POST['data_inicio'] ?? '');
    $hora_inicio_str = trim($_POST['hora_inicio'] ?? '00:00');
    $data_fim_str = trim($_POST['data_fim'] ?? '');
    $hora_fim_str = trim($_POST['hora_fim'] ?? '23:59');
    $descricao = trim($_POST['descricao'] ?? '');

    // --- Validação decomposta para funcionario_ids ---
    $isFuncionarioIdsSet = isset($funcionario_ids);
    $isFuncionarioIdsArray = $isFuncionarioIdsSet && is_array($funcionario_ids);
    $funcionarioIdsCount = $isFuncionarioIdsArray ? count($funcionario_ids) : 0;

    if ($funcionarioIdsCount === 0) {
        $errors['funcionario_ids'] = "Selecione pelo menos um funcionário.";
    }
    // --- Fim da validação decomposta ---

    if (empty($titulo)) $errors[] = "O título do evento é obrigatório.";
    if (empty($data_inicio_str)) $errors[] = "A data de início é obrigatória.";

    $data_inicio = $data_inicio_str . ' ' . $hora_inicio_str . ':00';
    $data_fim = !empty($data_fim_str) ? ($data_fim_str . ' ' . $hora_fim_str . ':00') : null;

    if (strtotime($data_inicio) === false) $errors[] = "Formato inválido para a data/hora de início.";
    if ($data_fim !== null && strtotime($data_fim) === false) $errors[] = "Formato inválido para a data/hora de fim.";
    if ($data_fim !== null && strtotime($data_fim) < strtotime($data_inicio)) $errors[] = "A data de fim não pode ser anterior à data de início.";

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO agendamentos (funcionario_id, titulo, tipo_evento, data_inicio, data_fim, descricao, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $nomes_funcionarios = [];

            // Criar um mapa de ID para nome para eficiência
            $func_map = array_column($lista_funcionarios, 'nome_completo', 'id');

            foreach ($funcionario_ids as $funcionario_id_str) {
                $funcionario_id = (int)$funcionario_id_str; // Converter para inteiro
                $stmt->execute([
                    $funcionario_id, $titulo, $tipo_evento, $data_inicio, $data_fim, $descricao, $utilizador_logado['id']
                ]);
                $nomes_funcionarios[] = $func_map[$funcionario_id] ?? $funcionario_id; // Obter nome do mapa
            }

            $pdo->commit();
            $successMessage = "Agendamento '" . htmlspecialchars($titulo) . "' criado com sucesso para " . count($funcionario_ids) . " funcionário(s).";
            log_event($pdo, 'INFO', 'GROUP_SCHEDULE_CREATED',
                      "Agendamento '{$titulo}' criado para múltiplos funcionários (" . implode(', ', $nomes_funcionarios) . ").",
                      $utilizador_logado['id'],
                      ['employee_ids' => $funcionario_ids]
                     );
            $_POST = [];

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Ocorreu um erro ao guardar o agendamento: " . $e->getMessage();
        } catch (Exception $e) {
             $pdo->rollBack();
             $errors[] = "Ocorreu um erro interno: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Novo Agendamento - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet">
    <style>
        /* Ajustar a aparência geral do container */
        .select2-container--bootstrap4 .select2-selection--multiple { border-color: #d1d5db; border-radius: 0.375rem; min-height: calc(1.5em + 1rem + 2px); padding: 0.5rem 0.5rem 0.1rem 0.5rem; box-shadow: none; }
        .select2-container--bootstrap4.select2-container--focus .select2-selection--multiple { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice { background-color: #e5e7eb; border: 1px solid #d1d5db; color: #374151; border-radius: 0.25rem; padding: 0.1rem 0.5rem; margin-right: 0.25rem; margin-bottom: 0.4rem; }
        .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove { color: #4b5563; margin-left: 0.5rem; }
        .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove:hover { color: #1f2937; }
        .select2-container--bootstrap4 .select2-search--inline .select2-search__field { color: #6b7280; margin-top: 0.5rem; }
        .select2-dropdown { border-color: #d1d5db; border-radius: 0.375rem; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>
    <main class="p-8">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <a href="calendario.php" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Voltar ao Calendário</a>
            </div>

            <form action="adicionar_agendamento.php" method="POST" class="bg-white p-8 rounded-2xl shadow-lg space-y-6">
                <h1 class="text-3xl font-bold text-gray-800 border-b pb-4">Novo Agendamento</h1>

                <?php if ($successMessage): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md">
                        <ul>
                           <?php foreach ($errors as $field => $msg): ?>
                                <li><?= htmlspecialchars($msg) ?></li>
                           <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="funcionario_ids" class="block text-sm font-medium text-gray-700 mb-1">Funcionário(s) *</label>
                    <select id="funcionario_ids" name="funcionario_ids[]" multiple="multiple" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required style="width: 100%;">
                        <?php if (!empty($lista_funcionarios)): ?>
                            <?php foreach($lista_funcionarios as $func): ?>
                                <option value="<?= $func['id'] ?>"
                                    <?= (!empty($_POST['funcionario_ids']) && in_array($func['id'], $_POST['funcionario_ids'])) ? 'selected' : '' ?>
                                >
                                    (<?= htmlspecialchars($func['numero_funcionario']) ?>) <?= htmlspecialchars($func['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($errors['funcionario_ids'])): ?>
                        <p class="text-red-500 text-xs mt-1"><?= $errors['funcionario_ids'] ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700">Título do Evento *</label>
                    <input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                </div>

                <div>
                    <label for="tipo_evento" class="block text-sm font-medium text-gray-700">Tipo de Evento</label>
                    <select id="tipo_evento" name="tipo_evento" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="Geral" <?= (($tipo_evento ?? 'Geral') == 'Geral') ? 'selected' : '' ?>>Geral</option>
                        <option value="Folga" <?= (($tipo_evento ?? '') == 'Folga') ? 'selected' : '' ?>>Folga</option>
                        <option value="Férias" <?= (($tipo_evento ?? '') == 'Férias') ? 'selected' : '' ?>>Férias</option>
                        <option value="Médico" <?= (($tipo_evento ?? '') == 'Médico') ? 'selected' : '' ?>>Consulta/Baixa Médica</option>
                        <option value="Formação" <?= (($tipo_evento ?? '') == 'Formação') ? 'selected' : '' ?>>Formação</option>
                        <option value="Reunião" <?= (($tipo_evento ?? '') == 'Reunião') ? 'selected' : '' ?>>Reunião</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700">Data de Início *</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($_POST['data_inicio'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                     <div>
                        <label for="hora_inicio" class="block text-sm font-medium text-gray-700">Hora de Início</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" value="<?= htmlspecialchars($_POST['hora_inicio'] ?? '00:00') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                </div>

                 <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="data_fim" class="block text-sm font-medium text-gray-700">Data de Fim (Opcional)</label>
                        <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($_POST['data_fim'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                     <div>
                        <label for="hora_fim" class="block text-sm font-medium text-gray-700">Hora de Fim</label>
                        <input type="time" id="hora_fim" name="hora_fim" value="<?= htmlspecialchars($_POST['hora_fim'] ?? '23:59') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                </div>

                <div>
                    <label for="descricao" class="block text-sm font-medium text-gray-700">Descrição / Notas</label>
                    <textarea id="descricao" name="descricao" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                </div>

                <div class="text-right border-t pt-6">
                    <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-blue-700">
                        Guardar Agendamento
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Inicializar o Select2
        $(document).ready(function() {
            $('#funcionario_ids').select2({
                placeholder: "-- Pesquise e selecione funcionários --",
                theme: "bootstrap4",
                allowClear: true
            });
        });
    </script>
</body>
</html>