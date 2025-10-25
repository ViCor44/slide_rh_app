<?php

// ----- FORÇAR EXIBIÇÃO DE ERROS (PARA DEBUG) -----
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------------------
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// === VALIDAÇÃO ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

$report_type = $_POST['report_type'] ?? null;
if (!$report_type) {
    die("Tipo de relatório não especificado.");
}

$logged_in_role_id = (int)$utilizador_logado['role_id'];
$html = '';
$report_title = '';
$pdf_orientation = 'portrait';

// === FUNÇÃO: Foto em base64 ===
function getFotoBase64($foto_path, $width = 60, $height = 60, $border_radius = '50%') {
    if (empty($foto_path)) {
        return "<div style=\"width:{$width}px;height:{$height}px;border-radius:{$border_radius};background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;border:2px solid #eee;\">Sem foto</div>";
    }
    $full_path = realpath(__DIR__ . '/../storage/fotos_funcionarios') . '/' . basename($foto_path);
    if (!file_exists($full_path)) {
        return "<div style=\"width:{$width}px;height:{$height}px;border-radius:{$border_radius};background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;border:2px solid #eee;\">N/D</div>";
    }
    $data = base64_encode(file_get_contents($full_path));
    $mime = mime_content_type($full_path) ?: 'image/jpeg';
    return "<img src=\"data:{$mime};base64,{$data}\" style=\"width:{$width}px;height:{$height}px;border-radius:{$border_radius};object-fit:cover;border:2px solid #eee;\">";
}

// === FUNÇÃO: Gráfico de linha (SVG) – 100% COMPATÍVEL COM DOMPDF v2.0+ ===
// === FUNÇÃO: Gráfico de BARRAS (HTML Puro) – Otimizada para Dompdf ===
function gerarGraficoLinha($periodos, $medias) {
    if (empty($periodos) || count($periodos) !== count($medias)) {
        return '<p style="text-align:center;color:#999;font-style:italic;margin:15px 0;">Dados insuficientes ou inválidos para gráfico.</p>';
    }

    $max_height = 80; // Altura máxima das barras em pixels
    $bar_width = 30; // Largura de cada barra
    $spacing = 15; // Espaçamento entre barras
    $max_value = 5.0;

    $bars_html = '';
    $labels_html = '';

    foreach ($periodos as $i => $periodo) {
        $media_atual = (float)($medias[$i] ?? 0);
        // Calcular a altura da barra proporcionalmente
        $bar_height = max(1, round(($media_atual / $max_value) * $max_height)); // Garante altura mínima de 1px
        // Cor da barra (pode variar com a pontuação se desejar)
        $bar_color = '#28a745'; 
        
        // Estilo inline para cada barra
        $bar_style = "height: {$bar_height}px; width: {$bar_width}px; background-color: {$bar_color}; margin: 0 auto; border-radius: 3px 3px 0 0;";
        // Estilo para o valor da média em cima da barra
        $value_style = "font-size: 9px; color: #333; margin-bottom: 2px; text-align: center; height: 12px;"; // Altura fixa para alinhar
         // Estilo para a legenda do período em baixo
        $label_style = "font-size: 9px; color: #555; margin-top: 4px; text-align: center; width: " . ($bar_width + $spacing) . "px; word-wrap: break-word;"; // Largura total para alinhar

        $periodo_curto = htmlspecialchars(substr(trim($periodo), 0, 10));

        // HTML para cada barra + valor + legenda
        $bars_html .= "<td style='vertical-align: bottom; padding: 0 " . ($spacing / 2) . "px;'>";
        $bars_html .= "<div style='{$value_style}'>" . number_format($media_atual, 1) . "</div>";
        $bars_html .= "<div style='{$bar_style}'></div>";
        $bars_html .= "<div style='{$label_style}'>{$periodo_curto}</div>";
        $bars_html .= "</td>";
    }

    // Montagem final do HTML do gráfico (usando uma tabela para alinhar)
    $html_output = <<<HTML
    <div style="width:100%; margin:15px 0 25px 0; page-break-inside:avoid;">
        <div style="font-weight:bold; font-size:13px; color:#333; margin-bottom:8px; text-align: center;">Evolução da Performance</div>
        <table style="width: auto; margin: 0 auto; border-collapse: collapse;">
            <tr>
                {$bars_html}
            </tr>
        </table>
    </div>
    HTML;

    return $html_output;
}

// ===============================================================
try {
    switch ($report_type) {

        // ===============================================================
        // 1. RELATÓRIO DE AVALIAÇÕES — RESUMO + GRÁFICO
        // ===============================================================
        case 'avaliacoes_geral':
        case 'avaliacoes_dep':
        case 'avaliacoes_dep_func':
            $filtro_departamento = $_POST['filtro_departamento'] ?? '';
            $filtro_funcionario_id = $_POST['filtro_funcionario_id'] ?? null;
            $logged_in_funcionario_id = (int)($utilizador_logado['funcionario_id'] ?? 0);

            if ($filtro_funcionario_id && (int)$filtro_funcionario_id === $logged_in_funcionario_id) {
                header('Location: acesso_negado.php'); exit;
            }

            $report_title = 'Relatório de Avaliações';
            $params = [];
            $sql = "SELECT f.id, f.nome_completo, f.departamento, f.foto_path 
                    FROM funcionarios f 
                    JOIN utilizadores u ON f.id = u.funcionario_id 
                    WHERE f.ativo = 1";

            if (!empty($filtro_funcionario_id)) {
                $sql .= " AND f.id = ?";
                $params[] = $filtro_funcionario_id;
            } else {
                if ($report_type === 'avaliacoes_geral') {
                    if ($logged_in_role_id !== ROLE_ADMIN) { header('Location: acesso_negado.php'); exit; }
                    $report_title .= ' (Geral)';
                    if (!empty($filtro_departamento)) {
                        $sql .= " AND f.departamento = ?";
                        $params[] = $filtro_departamento;
                    }
                } elseif ($report_type === 'avaliacoes_dep') {
                    if ($logged_in_role_id !== ROLE_MANAGER) { header('Location: acesso_negado.php'); exit; }
                    $report_title .= ' (Departamento)';
                    $sql .= " AND f.departamento = ? AND u.role_id IN (?, ?)";
                    array_push($params, $_SESSION['user_departamento'], ROLE_SUPERVISOR, ROLE_FUNCIONARIO);
                } elseif ($report_type === 'avaliacoes_dep_func') {
                    if ($logged_in_role_id !== ROLE_SUPERVISOR) { header('Location: acesso_negado.php'); exit; }
                    $report_title .= ' (Departamento)';
                    $sql .= " AND f.departamento = ? AND u.role_id = ?";
                    array_push($params, $_SESSION['user_departamento'], ROLE_FUNCIONARIO);
                }
            }
            $sql .= " ORDER BY f.departamento, f.nome_completo";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 11px; margin: 0; padding: 20px; }
                h1 { text-align: center; margin-bottom: 20px; font-size: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                .employee { display: flex; align-items: center; gap: 12px; background: #f9f9f9; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
                .employee h2 { margin: 0; font-size: 15px; }
                .employee p { margin: 2px 0 0; font-size: 11px; color: #666; }
                .summary { border: 1px solid #eee; border-radius: 8px; padding: 18px; background: #fff; margin-bottom: 35px; }
                .summary h3 { margin: 0 0 16px; font-size: 13px; color: #333; }
                .bar-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
                .bar-table td { padding: 7px 0; vertical-align: middle; }
                .bar-container { height: 18px; background: #eee; border-radius: 9px; overflow: hidden; position: relative; width: 100%; }
                .bar-fill { height: 100%; background: #28a745; border-radius: 9px; }
                .bar-value { font-weight: bold; color: #333; width: 55px; text-align: center; }
                .footer { position: fixed; bottom: 10px; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; }
            </style>

            <h1><?= htmlspecialchars($report_title) ?></h1>

            <?php if (empty($funcionarios)): ?>
                <p style="text-align:center; color:#666;">Nenhum funcionário encontrado.</p>
            <?php else: ?>
                <?php foreach ($funcionarios as $func): ?>
                    <div class="employee">
                        <?= getFotoBase64($func['foto_path'], 50, 50, '50%') ?>
                        <div>
                            <h2><?= htmlspecialchars($func['nome_completo']) ?></h2>
                            <p><?= htmlspecialchars($func['departamento']) ?></p>
                        </div>
                    </div>

                    <?php
                    $stmt_media = $pdo->prepare("
                        SELECT m.nome_metrica, AVG(r.pontuacao) as media
                        FROM avaliacao_respostas r
                        JOIN avaliacoes a ON r.avaliacao_id = a.id
                        JOIN avaliacao_metricas m ON r.metrica_id = m.id
                        WHERE a.funcionario_id = ?
                        GROUP BY m.id, m.nome_metrica
                        ORDER BY m.id
                    ");
                    $stmt_media->execute([$func['id']]);
                    $medias = $stmt_media->fetchAll(PDO::FETCH_ASSOC);

                    $stmt_total = $pdo->prepare("SELECT AVG(r.pontuacao) FROM avaliacao_respostas r JOIN avaliacoes a ON r.avaliacao_id = a.id WHERE a.funcionario_id = ?");
                    $stmt_total->execute([$func['id']]);
                    $media_geral = $stmt_total->fetchColumn();

                    $stmt_evolucao = $pdo->prepare("
                        SELECT a.periodo, AVG(r.pontuacao) as media
                        FROM avaliacoes a
                        JOIN avaliacao_respostas r ON a.id = r.avaliacao_id
                        WHERE a.funcionario_id = ?
                        GROUP BY a.id, a.periodo
                        ORDER BY a.data_avaliacao ASC
                    ");
                    $stmt_evolucao->execute([$func['id']]);
                    $evolucao = $stmt_evolucao->fetchAll(PDO::FETCH_ASSOC);
                    $periodos = array_column($evolucao, 'periodo');
                    $medias_evolucao = array_column($evolucao, 'media');
                    $grafico_svg = gerarGraficoLinha($periodos, $medias_evolucao);
                    ?>

                    <?php if ($media_geral > 0): ?>
                    <div class="summary">
                        <h3>Resumo de Performance (Média Geral: <?= number_format($media_geral, 2) ?> / 5.00)</h3>
                        <table class="bar-table">
                            <?php foreach ($medias as $m):
                            $percent = round(($m['media'] / 5) * 100);
                            $bar_color = $percent >= 90 ? '#1e7e34' : ($percent >= 70 ? '#28a745' : ($percent >= 50 ? '#5cb85c' : '#7bc67b'));
                        ?>
                        <tr>
                            <td style="width:35%;"><strong><?= htmlspecialchars($m['nome_metrica']) ?>:</strong></td>
                            <td class="bar-value"><?= number_format($m['media'], 2) ?></td>
                            <td style="width:55%;">
                                <div class="bar-container">
                                    <div class="bar-fill" style="width:<?= $percent ?>%; background:<?= $bar_color ?>;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </table>

                        <?= $grafico_svg ?>
                    </div>
                    <?php else: ?>
                    <p style="margin:15px 0; padding:12px; background:#fff8e6; border-left:4px solid #ffc107; color:#856404; font-style:italic; border-radius:4px;">
                        Nenhuma avaliação registada.
                    </p>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="footer">
                CrewSync | Gerado em <?= date('d/m/Y H:i') ?> por <?= htmlspecialchars($utilizador_logado['nome'] ?? 'Sistema') ?>
            </div>
            <?php
            $html = ob_get_clean();
            break;

        // ===============================================================
        // 2. RESUMO DE MÉDIAS GERAIS
        // ===============================================================
        case 'resumo_medias_geral':
            $report_title = 'Resumo de Médias de Avaliação';
            if ($logged_in_role_id !== ROLE_ADMIN && $logged_in_role_id !== ROLE_RH) {
                die('Acesso Negado.');
            }

            $sql = "SELECT 
                        f.numero_funcionario, f.nome_completo, f.departamento,
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
                    <tr><th>Nº</th><th>Nome Completo</th><th>Departamento</th><th>Nº de Avaliações</th><th>Média Geral</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($resultados)): ?>
                        <tr><td colspan="5" style="text-align:center;">Nenhum dado encontrado.</td></tr>
                    <?php else: foreach ($resultados as $res): ?>
                        <tr>
                            <td><?= htmlspecialchars($res['numero_funcionario']) ?></td>
                            <td><?= htmlspecialchars($res['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($res['departamento']) ?></td>
                            <td><?= htmlspecialchars($res['numero_avaliacoes']) ?></td>
                            <td style="font-weight:bold;"><?= number_format($res['media_geral'], 2) ?> / 5.00</td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <div class="footer">CrewSync | Gerado por <?= htmlspecialchars($utilizador_logado['nome'] ?? 'N/A') ?></div>
            <?php
            $html = ob_get_clean();
            break;

        // ===============================================================
        // 3. LISTA DE FUNCIONÁRIOS POR DEPARTAMENTO
        // ===============================================================
        case 'lista_profissionais_dep':
            $report_title = 'Lista de Funcionários por Departamento';
            if ($logged_in_role_id !== ROLE_MANAGER && $logged_in_role_id !== ROLE_SUPERVISOR) {
                header('Location: acesso_negado.php'); exit;
            }

            $departamento = $_SESSION['user_departamento'];
            $stmt = $pdo->prepare("SELECT numero_funcionario, nome_completo, email_corporativo, cargo, foto_path 
                                   FROM funcionarios WHERE departamento = ? AND ativo = 1 ORDER BY nome_completo ASC");
            $stmt->execute([$departamento]);
            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 11px; }
                h1 { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: middle; }
                thead { background-color: #f2f2f2; }
            </style>
            <h1><?= htmlspecialchars($report_title) ?> - <?= htmlspecialchars($departamento) ?></h1>
            <p>Relatório gerado em: <?= date('d/m/Y H:i') ?></p>
            <table>
                <thead><tr><th>Foto</th><th>Nº</th><th>Nome Completo</th><th>Email</th><th>Cargo</th></tr></thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): ?>
                    <tr>
                        <td><?= getFotoBase64($func['foto_path'], 30, 30, '50%') ?></td>
                        <td><?= htmlspecialchars($func['numero_funcionario']) ?></td>
                        <td><?= htmlspecialchars($func['nome_completo']) ?></td>
                        <td><?= htmlspecialchars($func['email_corporativo']) ?></td>
                        <td><?= htmlspecialchars($func['cargo']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();
            break;

        // ===============================================================
        // 4. LISTA COMPLETA DE FUNCIONÁRIOS
        // ===============================================================
        case 'lista_funcionarios_completa':
            $report_title = 'Lista Completa de Funcionários';
            if ($logged_in_role_id !== ROLE_ADMIN && $logged_in_role_id !== ROLE_RH) {
                header('Location: acesso_negado.php'); exit;
            }

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

            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; font-size: 9px; }
                h1 { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 16px;}
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
                thead { background-color: #f2f2f2; }
            </style>
            <h1><?= htmlspecialchars($report_title) ?></h1>
            <p>Relatório gerado em: <?= date('d/m/Y H:i') ?></p>
            <table>
                <thead><tr><th>Nº</th><th>Nome Completo</th><th>Email</th><th>Departamento</th><th>NIF</th><th>NSS</th><th>Telemóvel</th><th>IBAN</th></tr></thead>
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
            $pdf_orientation = 'landscape';
            break;

// ===============================================================
        // 5. RELATÓRIO INDIVIDUAL (VERSÃO MELHORADA)
        // ===============================================================
        case 'relatorio_individual':
            // Verificar permissão (Admin ou RH)
            if ($logged_in_role_id !== ROLE_ADMIN && $logged_in_role_id !== ROLE_RH) {
                 header('Location: acesso_negado.php'); exit;
             }
            
            $funcionario_id = (int)($_POST['filtro_funcionario_id'] ?? 0);
            if (!$funcionario_id) die("ID do Funcionário é obrigatório para este relatório.");

            // Ir buscar TODOS os dados das duas tabelas
            $stmt = $pdo->prepare("SELECT f.*, dp.* FROM funcionarios f
                                   LEFT JOIN funcionarios_dados_pessoais dp ON f.id = dp.funcionario_id
                                   WHERE f.id = ? AND f.ativo = 1");
            $stmt->execute([$funcionario_id]);
            $func = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$func) die("Funcionário não encontrado ou inativo.");

            $report_title = 'Relatório Individual - ' . htmlspecialchars($func['nome_completo']);
            // Pedir uma foto maior (ex: 80x80)
            $photo_html = getFotoBase64($func['foto_path'], 100, 100, '50%'); 

            ob_start();
            ?>
            <style>
                body { font-family: 'Helvetica', sans-serif; color: #333; font-size: 11px; line-height: 1.5; }
                .header-table { width: 100%; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
                .header-table td { vertical-align: middle; }
                .header-text h1 { margin: 0 0 5px 0; font-size: 20px; color: #000; }
                .header-text p { margin: 2px 0; color: #555; font-size: 11px; }
                .section { margin-bottom: 20px; page-break-inside: avoid; } /* Evita quebras de página dentro de uma secção */
                .section h2 { font-size: 14px; background-color: #f0f0f0; padding: 8px 12px; border-radius: 4px; margin: 0 0 10px 0; color: #333; }
                .details-grid { display: block; margin-left: 15px; } /* Usar display block para compatibilidade */
                .details-grid p { margin: 5px 0; }
                .details-grid strong { display: inline-block; width: 150px; color: #000; } /* Largura fixa para alinhar */
                .footer { position: fixed; bottom: -20px; left: 0; right: 0; width: 100%; text-align: center; font-size: 9px; color: #999; }
            </style>
            
            <table class="header-table">
                <tr>
                    <td width="100px"><?= $photo_html ?></td>
                    <td class="header-text">
                        <h1><?= htmlspecialchars($func['nome_completo']) ?></h1>
                        <p><?= htmlspecialchars($func['cargo'] ?? 'N/A') ?> | <?= htmlspecialchars($func['departamento'] ?? 'N/A') ?></p>
                        <p>Nº: <?= htmlspecialchars($func['numero_funcionario']) ?> | Email: <?= htmlspecialchars($func['email_corporativo']) ?></p>
                        <p>Contratado em: <?= isset($func['data_contratacao']) ? date('d/m/Y', strtotime($func['data_contratacao'])) : 'N/A' ?></p>
                    </td>
                </tr>
            </table>

            <div class="section">
                <h2>Dados Pessoais</h2>
                <div class="details-grid">
                    <p><strong>Data de Nascimento:</strong> <?= isset($func['data_nascimento']) ? date('d/m/Y', strtotime($func['data_nascimento'])) : 'N/A' ?></p>
                    <p><strong>Telemóvel:</strong> <?= htmlspecialchars($func['telemovel'] ?? 'N/A') ?></p>
                    <p><strong>Morada Completa:</strong> <?= nl2br(htmlspecialchars($func['morada_completa'] ?? 'N/A')) ?></p>
                </div>
            </div>

            <div class="section">
                <h2>Dados de Identificação e Bancários (Confidencial)</h2>
                <div class="details-grid">
                    <p><strong>NIF:</strong> <?= htmlspecialchars($func['nif'] ?? 'N/A') ?></p>
                    <p><strong>Nº Cartão Cidadão:</strong> <?= htmlspecialchars($func['cartao_cidadao'] ?? 'N/A') ?></p>
                    <p><strong>Nº Segurança Social:</strong> <?= htmlspecialchars($func['nss'] ?? 'N/A') ?></p>
                    <p><strong>IBAN:</strong> <?= htmlspecialchars($func['iban'] ?? 'N/A') ?></p>
                </div>
            </div>

            <div class="section">
                <h2>Informação Adicional</h2>
                <div class="details-grid">
                    <p><strong>ID Cartão NFC:</strong> <?= htmlspecialchars($func['nfc_card_id'] ?? 'N/A') ?></p>
                    <p><strong>Estado Atual:</strong> <?= ($func['ativo'] ?? 1) ? 'Ativo' : 'Inativo' ?></p>
                    </div>
            </div>
            
            <div class="footer">
                CrewSync | Gerado em <?= date('d/m/Y H:i') ?> por <?= htmlspecialchars($utilizador_logado['nome'] ?? 'N/A') ?>
            </div>
            <?php
            $html = ob_get_clean();
            break; // Fim do case 'relatorio_individual'

        default:
            die("Relatório desconhecido.");
    }

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// === GERAÇÃO DO PDF ===
if (!empty($html)) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $options->set('isFontSubsettingEnabled', true);
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', $pdf_orientation);
    $dompdf->render();

    $filename = strtolower(str_replace([' ', '/'], '_', $report_title)) . ".pdf";
    $dompdf->stream($filename, ["Attachment" => false]);
    exit;
}