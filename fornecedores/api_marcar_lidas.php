<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Segurança: Só utilizadores logados podem aceder
if (!isset($_SESSION['logado']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

// Apenas aceita pedidos POST (para segurança)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

require_once ROOT_PATH . '/config/conexao.php';

$user_id_logado = (int)$_SESSION['user_id'];
$sucesso = false;

try {
    $sql_update = "UPDATE notificacoes SET lida = 1 WHERE user_id_destino = ? AND lida = 0";
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $stmt_update->bind_param("i", $user_id_logado);
        $stmt_update->execute();
        
        // Verifica se alguma linha foi de facto atualizada
        if ($stmt_update->affected_rows >= 0) {
            $sucesso = true;
        }
        $stmt_update->close();
    }
} catch (Exception $e) {
    error_log("Erro em api_marcar_lidas: " . $e->getMessage());
}

if (isset($conn)) { $conn->close(); }

// Devolve a resposta
if ($sucesso) {
    http_response_code(200);
    echo json_encode(['sucesso' => true]);
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível atualizar as notificações.']);
}
exit;