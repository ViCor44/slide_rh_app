<?php
// Incluir tudo o que precisamos
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

// Usar as classes do Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// --- Validação Inicial ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

$report_type = $_POST['report_type'] ?? null;
if (!$report_type) {
    die("Tipo de relatório não especificado.");
}

$logged_in_role_id = (int)$utilizador_logado['role_id'];
$html = ''; // Variável que vai conter o HTML do nosso PDF
$report_title = '';

// --- Lógica Principal: Escolher o relatório a gerar ---
try {
    switch ($report_type) {

        case 'resumo_medias_geral':
            $report_title = 'Resumo de Médias de Avaliação';

            // 1. Verificação de Segurança
            if ($logged_in_role_id !== ROLE_ADMIN && $logged_in_role_id !== ROLE_RH) {
                die('Acesso Negado.');
            }

            // 2. Query SQL para calcular a média geral por funcionário
            $sql = "SELECT 
                        f.numero_funcionario,
                        f.nome_completo,
                        f.departamento,
                        AVG(r.pontuacao) AS media_geral,
                        COUNT(DISTINCT a.id) as numero_avaliacoes
                    FROM funcionarios f
                    JOIN avaliacoes a ON f.id = a.funcionario_id
                    JOIN avaliacao_respostas r ON a.id = r.avaliacao_id
                    WHERE f.ativo = 1
                    GROUP BY f.id
                    ORDER BY media_geral DESC";
            
            $stmt = $pdo->query($sql);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Construir o HTML do relatório
            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 12px; }
                h1 { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                thead { background-color: #f2f2f2; }
                .footer { position: fixed; bottom: -20px; text-align: center; font-size: 9px; color: #999; width:100%;}
            </style>
            <h1><?= htmlspecialchars($report_title) ?></h1>
            <p>Relatório gerado em: <?= date('d/m/Y H:i') ?></p>
            <table>
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Nome Completo</th>
                        <th>Departamento</th>
                        <th>Nº de Avaliações</th>
                        <th>Média Geral</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultados)): ?>
                        <tr><td colspan="5" style="text-align: center;">Nenhum dado de avaliação encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultados as $res): ?>
                            <tr>
                                <td><?= htmlspecialchars($res['numero_funcionario']) ?></td>
                                <td><?= htmlspecialchars($res['nome_completo']) ?></td>
                                <td><?= htmlspecialchars($res['departamento']) ?></td>
                                <td><?= htmlspecialchars($res['numero_avaliacoes']) ?></td>
                                <td style="font-weight: bold;"><?= number_format($res['media_geral'], 2) ?> / 5.00</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="footer">
                CrewSync | Relatório gerado por <?= htmlspecialchars($utilizador_logado['nome'] ?? 'N/A') ?>
            </div>
            <?php
            $html = ob_get_clean();
            $pdf_orientation = 'portrait';
            break;

case 'avaliacoes_geral':
        case 'avaliacoes_dep':
        case 'avaliacoes_dep_func':
            // 1. OBTER FILTROS E PERMISSÕES (esta parte fica igual)
            $filtro_departamento = $_POST['filtro_departamento'] ?? '';
            $filtro_funcionario_id = $_POST['filtro_funcionario_id'] ?? null;
            $logged_in_funcionario_id = (int)($utilizador_logado['funcionario_id'] ?? 0);

            if ($filtro_funcionario_id && (int)$filtro_funcionario_id === $logged_in_funcionario_id) {
                header('Location: acesso_negado.php');
                exit;
            }

            // 2. CONSTRUIR A QUERY SQL DINAMICAMENTE (esta parte fica igual)
            $report_title = 'Relatório de Avaliações';
            $params_funcionarios = [];
            $sql_funcionarios = "SELECT f.id, f.nome_completo, f.departamento, f.foto_path FROM funcionarios f JOIN utilizadores u ON f.id = u.funcionario_id WHERE f.ativo = 1";

            if (!empty($filtro_funcionario_id)) {
                $sql_funcionarios .= " AND f.id = ?";
                $params_funcionarios[] = $filtro_funcionario_id;
            } else {
                // (lógica para Admin, Manager, Supervisor fica igual)
                if ($report_type === 'avaliacoes_geral') {
                    if ($logged_in_role_id !== ROLE_ADMIN) { header('Location: acesso_negado.php'); exit; }
                    $report_title .= ' (Geral)';
                    if (!empty($filtro_departamento)) {
                        $sql_funcionarios .= " AND f.departamento = ?";
                        $params_funcionarios[] = $filtro_departamento;
                    }
                } elseif ($report_type === 'avaliacoes_dep') {
                    if ($logged_in_role_id !== ROLE_MANAGER) { header('Location: acesso_negado.php'); exit; }
                    $report_title .= ' (Departamento: ' . htmlspecialchars($_SESSION['user_departamento']) . ')';
                    $sql_funcionarios .= " AND f.departamento = ? AND u.role_id IN (?, ?)";
                    array_push($params_funcionarios, $_SESSION['user_departamento'], ROLE_SUPERVISOR, ROLE_FUNCIONARIO);
                } elseif ($report_type === 'avaliacoes_dep_func') {
                    if ($logged_in_role_id !== ROLE_SUPERVISOR) { header('Location: acesso_negado.php'); exit; }
                    $report_title .= ' (Departamento: ' . htmlspecialchars($_SESSION['user_departamento']) . ')';
                    $sql_funcionarios .= " AND f.departamento = ? AND u.role_id = ?";
                    array_push($params_funcionarios, $_SESSION['user_departamento'], ROLE_FUNCIONARIO);
                }
            }
            $sql_funcionarios .= " ORDER BY f.departamento, f.nome_completo";

            // 3. EXECUTAR QUERY E GERAR O HTML
            $stmt_funcionarios = $pdo->prepare($sql_funcionarios);
            $stmt_funcionarios->execute($params_funcionarios);
            $funcionarios_no_relatorio = $stmt_funcionarios->fetchAll(PDO::FETCH_ASSOC);

            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 11px; }
                h1 { font-size: 20px; text-align: center; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px;}
                .employee-header { background-color: #f2f2f2; padding: 10px; margin-top: 25px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
                .employee-header h2 { font-size: 16px; margin: 5px 0 0 0; }
                .summary-section { margin-bottom: 20px; border: 1px solid #eee; padding: 10px; border-radius: 5px;}
                .summary-section h3 { font-size: 14px; margin-bottom: 10px; }
                .progress-bar-bg { width: 100%; background-color: #e9ecef; border-radius: 5px; height: 8px; }
                .progress-bar { background-color: #0d6efd; height: 8px; border-radius: 5px; }
                h3.evaluation-title { font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 20px; margin-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                thead { background-color: #fafafa; }
                .footer { position: fixed; bottom: -20px; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; }
                blockquote { border-left: 3px solid #ccc; padding-left: 10px; margin: 5px 0 10px 0; font-style: italic; color: #555; }
            </style>

            <h1><?= htmlspecialchars($report_title) ?></h1>

            <?php if (empty($funcionarios_no_relatorio)): ?>
                <p>Nenhum funcionário encontrado para os critérios selecionados.</p>
            <?php else: ?>
                <?php foreach ($funcionarios_no_relatorio as $func): ?>
                    <div class="employee-header">
                        <h2><?= htmlspecialchars($func['nome_completo']) ?></h2>
                    </div>

                    <?php
                    // ==========================================================
                    // == INÍCIO DA NOVA LÓGICA PARA AS MÉDIAS                 ==
                    // ==========================================================
                    $stmt_medias_metricas = $pdo->prepare("SELECT m.nome_metrica, AVG(r.pontuacao) as media FROM avaliacao_respostas r JOIN avaliacoes a ON r.avaliacao_id = a.id JOIN avaliacao_metricas m ON r.metrica_id = m.id WHERE a.funcionario_id = ? GROUP BY r.metrica_id, m.nome_metrica ORDER BY m.id");
                    $stmt_medias_metricas->execute([$func['id']]);
                    $medias_por_metrica = $stmt_medias_metricas->fetchAll(PDO::FETCH_ASSOC);

                    $stmt_media_total = $pdo->prepare("SELECT AVG(r.pontuacao) as media_total FROM avaliacao_respostas r JOIN avaliacoes a ON r.avaliacao_id = a.id WHERE a.funcionario_id = ?");
                    $stmt_media_total->execute([$func['id']]);
                    $media_total = $stmt_media_total->fetchColumn();
                    // ==========================================================
                    ?>
                    
                    <?php if ($media_total > 0): ?>
                        <div class="summary-section">
                            <h3>Resumo de Performance (Média Geral: <?= number_format($media_total, 2) ?>)</h3>
                            <?php foreach ($medias_por_metrica as $media): ?>
                                <p style="margin-bottom: 8px; font-size: 10px;">
                                    <strong><?= htmlspecialchars($media['nome_metrica']) ?>:</strong> <?= number_format($media['media'], 2) ?><br>
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar" style="width: <?= ($media['media'] / 5) * 100 ?>%"></div>
                                    </div>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // O resto da lógica para ir buscar e mostrar as avaliações detalhadas continua igual...
                    $stmt_avals = $pdo->prepare("SELECT a.*, COALESCE(u.nome, u.email) as avaliador_nome FROM avaliacoes a LEFT JOIN utilizadores u ON a.avaliador_user_id = u.id WHERE a.funcionario_id = ? ORDER BY a.data_avaliacao DESC");
                    $stmt_avals->execute([$func['id']]);
                    $avaliacoes = $stmt_avals->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (empty($avaliacoes)): ?>
                        <p>Nenhuma avaliação encontrada para este funcionário.</p>
                    <?php else: ?>
                        <?php foreach ($avaliacoes as $aval): ?>
                            <h3 class="evaluation-title">Avaliação de <?= htmlspecialchars($aval['periodo']) ?> (<?= date('d/m/Y', strtotime($aval['data_avaliacao'])) ?>)</h3>
                            
                            <?php
                            // Para cada avaliação, ir buscar as respostas
                            $stmt_resps = $pdo->prepare("SELECT r.pontuacao, r.comentarios, m.nome_metrica FROM avaliacao_respostas r JOIN avaliacao_metricas m ON r.metrica_id = m.id WHERE r.avaliacao_id = ? ORDER BY m.id");
                            $stmt_resps->execute([$aval['id']]);
                            $respostas = $stmt_resps->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                             <table>
                                <thead><tr><th>Métrica</th><th>Pontuação</th><th>Comentários</th></tr></thead>
                                <tbody>
                                    <?php foreach ($respostas as $resp): ?>
                                    <tr>
                                        <td width="30%"><?= htmlspecialchars($resp['nome_metrica']) ?></td>
                                        <td width="10%"><?= htmlspecialchars($resp['pontuacao']) ?> / 5</td>
                                        <td><?= !empty($resp['comentarios']) ? '<blockquote>' . nl2br(htmlspecialchars($resp['comentarios'])) . '</blockquote>' : '' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>

            <div class="footer">
                CrewSync | Relatório gerado em <?= date('d/m/Y H:i') ?> por <?= htmlspecialchars($utilizador_logado['nome'] ?? 'N/A') ?>
            </div>

            <?php
            $html = ob_get_clean();
            $pdf_orientation = 'portrait';
            break;
                    
        case 'lista_profissionais_dep':
            $report_title = 'Lista de Funcionários por Departamento';

            // 1. Verificação de Segurança
            if ($logged_in_role_id !== ROLE_MANAGER && $logged_in_role_id !== ROLE_SUPERVISOR) {
                header('Location: acesso_negado.php');
                exit;
            }

            // 2. Ir buscar os dados
            $departamento = $_SESSION['user_departamento'];
            $stmt = $pdo->prepare("SELECT numero_funcionario, nome_completo, email_corporativo, cargo FROM funcionarios WHERE departamento = ? AND ativo = 1 ORDER BY nome_completo ASC");
            $stmt->execute([$departamento]);
            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Construir o HTML do relatório
            ob_start(); // Inicia o buffer de saída para "desenhar" o HTML
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 12px; }
                h1 { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                thead { background-color: #f2f2f2; }
            </style>
            <h1><?= htmlspecialchars($report_title) ?> - <?= htmlspecialchars($departamento) ?></h1>
            <p>Relatório gerado em: <?= date('d/m/Y H:i') ?></p>
            <table>
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Nome Completo</th>
                        <th>Email</th>
                        <th>Cargo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): ?>
                        <tr>
                            <td><?= htmlspecialchars($func['numero_funcionario']) ?></td>
                            <td><?= htmlspecialchars($func['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($func['email_corporativo']) ?></td>
                            <td><?= htmlspecialchars($func['cargo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean(); // Guarda o HTML "desenhado" na variável $html
            break;

        case 'lista_funcionarios_completa':
            $report_title = 'Lista Completa de Funcionários';

            // 1. Verificação de Segurança: Apenas Admins e RH
            if ($logged_in_role_id !== ROLE_ADMIN && $logged_in_role_id !== ROLE_RH) {
                header('Location: acesso_negado.php');
                exit;
            }

            // 2. Ir buscar os dados (com todos os campos das duas tabelas)
            $filtro_departamento = $_POST['filtro_departamento'] ?? '';
            $sql = "SELECT f.*, dp.* FROM funcionarios f
                    LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id
                    WHERE f.ativo = 1";
            $params = [];

            if (!empty($filtro_departamento)) {
                $sql .= " AND f.departamento = ?";
                $params[] = $filtro_departamento;
                $report_title .= ' - Dept: ' . htmlspecialchars($filtro_departamento);
            }
            $sql .= " ORDER BY f.nome_completo ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Construir o HTML do relatório
            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 9px; } /* Fonte mais pequena para caberem as colunas */
                h1 { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 16px;}
                p { font-size: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
                thead { background-color: #f2f2f2; }
            </style>
            <h1><?= htmlspecialchars($report_title) ?></h1>
            <p>Relatório gerado em: <?= date('d/m/Y H:i') ?></p>
            <table>
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Nome Completo</th>
                        <th>Email</th>
                        <th>Departamento</th>
                        <th>NIF</th>
                        <th>NSS</th>
                        <th>Telemóvel</th>
                        <th>IBAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): ?>
                        <tr>
                            <td><?= htmlspecialchars($func['numero_funcionario']) ?></td>
                            <td><?= htmlspecialchars($func['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($func['email_corporativo']) ?></td>
                            <td><?= htmlspecialchars($func['departamento']) ?></td>
                            <td><?= htmlspecialchars($func['nif'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($func['nss'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($func['telemovel'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($func['iban'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();
            $pdf_orientation = 'landscape'; // Definir a orientação como horizontal
            break;

        case 'relatorio_individual':
            // 1. Obter e Validar o ID do funcionário
            $funcionario_id = $_POST['filtro_funcionario_id'] ?? null;
            if (empty($funcionario_id)) {
                die("Para este relatório, é obrigatório selecionar um funcionário.");
            }
            $funcionario_id = (int)$funcionario_id;

            // 2. Ir buscar todos os dados
            $stmt = $pdo->prepare("
                SELECT f.*, dp.* FROM funcionarios f
                LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id
                WHERE f.id = ? AND f.ativo = 1
            ");
            $stmt->execute([$funcionario_id]);
            $func = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$func) { die("Funcionário não encontrado."); }
            
            // 3. Lógica de Segurança (garantir que o utilizador pode ver este perfil)
            // (Esta lógica deve ser a mesma da sua página funcionario_detalhe.php)
            // ...

            $report_title = 'Relatório Individual - ' . htmlspecialchars($func['nome_completo']);

            $photo_html = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mN8/x8AAuMB8DtXNJsAAAAASUVORK5CYII=" class="profile-photo">'; // Placeholder cinzento
            if (!empty($func['foto_path'])) {
                $photo_path_on_disk = realpath(__DIR__ . '/../storage/fotos_funcionarios') . '/' . $func['foto_path'];
                if (file_exists($photo_path_on_disk)) {
                    $photo_data = base64_encode(file_get_contents($photo_path_on_disk));
                    $photo_mime = mime_content_type($photo_path_on_disk);
                    $photo_html = '<img src="data:' . $photo_mime . ';base64,' . $photo_data . '" class="profile-photo">';
                }
            }
            // ==========================================================

            // 4. Construir o HTML do relatório (COM A CORREÇÃO)
            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; color: #333; font-size: 11px; }
                .profile-photo { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; }
                .photo-placeholder { width: 90px; height: 90px; border-radius: 50%; background-color: #f0f0f0; color: #999; display: table-cell; vertical-align: middle; text-align: center; font-size: 10px; }
                .header-container { border-bottom: 2px solid #eee; padding-bottom: 15px; }
                .header-photo, .header-text { display: inline-block; vertical-align: middle; }
                .header-text { padding-left: 20px; }
                .header-text h1 { margin: 0; font-size: 22px; }
                .header-text p { margin: 4px 0; color: #666; font-size: 12px;}
                .section { margin-top: 20px; }
                .section h2 { font-size: 14px; background-color: #f2f2f2; padding: 8px; border-radius: 4px; margin-bottom: 10px; }
                .details p { margin: 6px 0; }
                .details strong { color: #000; }
                .footer { position: fixed; bottom: -20px; left: 0; right: 0; width: 100%; text-align: center; font-size: 9px; color: #999; }
            </style>
            
            <table width="100%" class="header-container">
                <tr>
                    <td width="100px" class="header-photo"><?= $photo_html ?></td>
                    <td class="header-text">
                        <h1><?= htmlspecialchars($func['nome_completo'] ?? 'N/A') ?></h1>
                        <p><?= htmlspecialchars($func['cargo'] ?? 'N/A') ?> | <?= htmlspecialchars($func['departamento'] ?? 'N/A') ?></p>
                        <p>Nº de Funcionário: <?= htmlspecialchars($func['numero_funcionario'] ?? 'N/A') ?> | Email: <?= htmlspecialchars($func['email_corporativo'] ?? 'N/A') ?></p>
                    </td>
                </tr>
            </table>

            <div class="section">
                <h2>Dados Pessoais</h2>
                <div class="details">
                    <p><strong>Data de Nascimento:</strong> <?= isset($func['data_nascimento']) ? date('d/m/Y', strtotime($func['data_nascimento'])) : 'N/A' ?></p>
                    <p><strong>Telemóvel:</strong> <?= htmlspecialchars($func['telemovel'] ?? 'N/A') ?></p>
                    <p><strong>Morada:</strong> <?= htmlspecialchars($func['morada_completa'] ?? 'N/A') ?></p>
                </div>
            </div>

            <div class="section">
                <h2>Dados Confidenciais</h2>
                <div class="details">
                    <p><strong>NIF:</strong> <?= htmlspecialchars($func['nif'] ?? 'N/A') ?></p>
                    <p><strong>Nº Cartão de Cidadão:</strong> <?= htmlspecialchars($func['cartao_cidadao'] ?? 'N/A') ?></p>
                    <p><strong>Nº Segurança Social:</strong> <?= htmlspecialchars($func['nss'] ?? 'N/A') ?></p>
                    <p><strong>IBAN:</strong> <?= htmlspecialchars($func['iban'] ?? 'N/A') ?></p>
                </div>
            </div>

            <div class="footer">
                CrewSync | Relatório gerado em <?= date('d/m/Y H:i') ?> por <?= htmlspecialchars($utilizador_logado['nome'] ?? 'N/A') ?>
            </div>
            <?php
            $html = ob_get_clean();
            $pdf_orientation = 'portrait';
            break;
    }

} catch (Exception $e) {
    die("Ocorreu um erro ao gerar o relatório: " . $e->getMessage());
}


// --- Geração do PDF com Dompdf ---
if (!empty($html)) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Permite carregar imagens, etc.

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);

    // (Opcional) Definir o tamanho e orientação do papel
    $dompdf->setPaper('A4', 'portrait'); // 'portrait' (vertical) ou 'landscape' (horizontal)

    // Renderizar o HTML como PDF
    $dompdf->render();

    // Enviar o PDF para o browser
    // O segundo parâmetro ["Attachment" => false] faz com que o PDF tente abrir no browser em vez de descarregar
    $dompdf->stream(strtolower(str_replace(' ', '_', $report_title)) . ".pdf", ["Attachment" => false]);
    exit;
}