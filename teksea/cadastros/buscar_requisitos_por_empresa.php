<?php
session_start();
// (Ajuste este caminho se o seu config/conexao.php estiver noutro local)
require_once __DIR__ . '/../../config/conexao.php';

// A nossa resposta será em JSON
header('Content-Type: application/json');

// Array para a resposta
$response = [];

// 1. Segurança: Apenas admins logados (emp_id 11) podem usar esta API
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

// 2. Validação: Verifica se o ID da empresa foi enviado pela URL
if (!isset($_GET['emp_id']) || !is_numeric($_GET['emp_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID da empresa inválido.']);
    exit;
}
$emp_id = (int)$_GET['emp_id'];

try {
    // 3. Passo A: Encontrar o seg_id da empresa selecionada
    $seg_id = null;
    $sql_seg = "SELECT seg_id FROM empresa WHERE emp_id = ?";
    $stmt_seg = $conn->prepare($sql_seg);
    $stmt_seg->bind_param("i", $emp_id);
    $stmt_seg->execute();
    $result_seg = $stmt_seg->get_result();
    
    if ($row_seg = $result_seg->fetch_assoc()) {
        $seg_id = $row_seg['seg_id'];
    }
    $stmt_seg->close();

    if (empty($seg_id)) {
        // Empresa não tem segmento, retorna lista vazia
        echo json_encode([]); // Envia um array JSON vazio
        exit;
    }

    // 4. Passo B: Encontrar os 'tipos' (requisitos) para esse seg_id
    // (Esta é a consulta que já usámos na página do fornecedor)
    $sql_tipos = "SELECT t.tipo_id, t.tipo_descricao 
                  FROM tipo t
                  JOIN requisito_segmento r ON t.tipo_id = r.tipo_id
                  WHERE r.seg_id = ?
                  ORDER BY t.tipo_descricao ASC";
                  
    $stmt_tipos = $conn->prepare($sql_tipos);
    $stmt_tipos->bind_param("i", $seg_id);
    $stmt_tipos->execute();
    $result_tipos = $stmt_tipos->get_result();

    // 5. Formatar a resposta como JSON
    $tipos_para_json = [];
    while ($tipo = $result_tipos->fetch_assoc()) {
        // Formata para o que o JavaScript espera: {'id': ..., 'nome': ...}
        $tipos_para_json[] = [
            'id'   => $tipo['tipo_id'],
            'nome' => $tipo['tipo_descricao']
        ];
    }
    $stmt_tipos->close();
    
    // Envia o array de tipos como resposta
    echo json_encode($tipos_para_json);

} catch (Exception $e) {
    // Captura qualquer erro de DB
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
exit;
?>