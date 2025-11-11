<?php
session_start();

// Carrega as configurações (caminho para a conexão)
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/config/conexao.php';

// Define o tipo de resposta como JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => ''];

try {
    // 1. Segurança: Verifica se o utilizador está logado
    if (!isset($_SESSION['logado']) || !isset($_SESSION['user_id'])) {
        throw new Exception('Acesso negado. Sessão inválida.');
    }
    
    $user_id_logado = $_SESSION['user_id'];

    // 2. Prepara a atualização
    $sql = "UPDATE notificacoes SET lida = 1 WHERE user_id_destino = ? AND lida = 0";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar a consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id_logado);
    
    // 3. Executa
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Notificações marcadas como lidas.';
    } else {
        throw new Exception('Erro ao executar a atualização: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Envia a resposta JSON de volta para o JavaScript
echo json_encode($response);
exit;
?>