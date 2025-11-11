<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Segurança: Só utilizadores logados podem aceder
if (!isset($_SESSION['logado']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

require_once ROOT_PATH . '/config/conexao.php';

$count_nao_lidas = 0;
$user_id_logado = (int)$_SESSION['user_id'];

$sql_count = "SELECT COUNT(*) as total FROM notificacoes WHERE user_id_destino = ? AND lida = 0";
$stmt_count = $conn->prepare($sql_count);

if ($stmt_count) {
    $stmt_count->bind_param("i", $user_id_logado);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $count_nao_lidas = $result_count->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
}

if (isset($conn)) { $conn->close(); }

// Devolve a resposta em formato JSON
header('Content-Type: application/json');
echo json_encode(['count' => $count_nao_lidas]);
exit;