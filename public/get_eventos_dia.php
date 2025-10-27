<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Validar a data recebida
$data_selecionada_str = $_GET['data'] ?? null;
if (!$data_selecionada_str || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_selecionada_str)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data inválida']);
    exit;
}

// Preparar as datas de início e fim do dia
$dia_inicio = $data_selecionada_str . ' 00:00:00';
$dia_fim = $data_selecionada_str . ' 23:59:59';

$eventos_combinados = [];

try {
    // --- 1. BUSCAR AGENDAMENTOS EXPLICITOS PARA O DIA ---
    $stmt_agendamentos = $pdo->prepare("
        SELECT a.titulo, a.tipo_evento, f.nome_completo as funcionario_nome
        FROM agendamentos a
        JOIN funcionarios f ON a.funcionario_id = f.id
        WHERE (a.data_inicio BETWEEN :inicio AND :fim) 
           OR (a.data_fim BETWEEN :inicio AND :fim)
           OR (a.data_inicio < :inicio AND a.data_fim > :fim)
        ORDER BY a.data_inicio ASC
    ");
    $stmt_agendamentos->bindParam(':inicio', $dia_inicio);
    $stmt_agendamentos->bindParam(':fim', $dia_fim);
    $stmt_agendamentos->execute();
    $agendamentos_do_dia = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar ao array final
    $eventos_combinados = $agendamentos_do_dia;


} catch (PDOException $e) {
    http_response_code(500);
    error_log("Erro em get_eventos_dia.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao buscar eventos']);
    exit;
}

// Definir o cabeçalho como JSON e devolver os resultados combinados
header('Content-Type: application/json; charset=utf-8'); // Adicionar charset=utf-8
echo json_encode($eventos_combinados);
exit;
?>