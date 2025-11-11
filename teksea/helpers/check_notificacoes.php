<?php
// teksea/helpers/check_notificacoes.php
// Este ficheiro é o nosso "Verificador" (Backend)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Define um cabeçalho para que o navegador saiba que é JSON
header('Content-Type: application/json');

// Prepara uma resposta padrão de erro
$resposta = ['novas_notificacoes' => 0, 'status' => 'erro'];

// 1. Segurança: Precisamos ter a certeza de que é um admin logado
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11 || !isset($_SESSION['user_id'])) {
    $resposta['mensagem'] = 'Sessão inválida.';
    echo json_encode($resposta);
    exit;
}

// 2. Configuração e Conexão
// Como este ficheiro é chamado "sozinho" (via AJAX), ele precisa
// de carregar as suas próprias configurações e conexão.
require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . '/config/conexao.php';

// 3. Lógica Principal
try {
    $user_id_atual = $_SESSION['user_id'];
    $total_nao_lidas = 0;

    $sql_count = "SELECT COUNT(*) as total FROM notificacoes WHERE user_id_destino = ? AND lida = 0";
    $stmt_count = $conn->prepare($sql_count);
    
    if ($stmt_count) {
        $stmt_count->bind_param("i", $user_id_atual);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $row = $result_count->fetch_assoc();
        $total_nao_lidas = $row['total'] ?? 0;
        $stmt_count->close();
        
        // 4. Resposta de Sucesso
        $resposta = ['novas_notificacoes' => (int)$total_nao_lidas, 'status' => 'sucesso'];
        
    } else {
        $resposta['mensagem'] = 'Erro ao preparar consulta.';
    }

} catch (Exception $e) {
    // Se a conexão ou algo falhar
    $resposta['mensagem'] = $e->getMessage();
}

if (isset($conn)) { $conn->close(); }

// 5. Envia a resposta final (Ex: {"novas_notificacoes": 3, "status": "sucesso"})
echo json_encode($resposta);
exit;
?>