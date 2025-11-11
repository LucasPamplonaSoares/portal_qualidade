<?php
session_start();
require_once __DIR__.'/../../config/conexao.php';

// 1. Segurança: Verifica se é admin E se tem user_id na sessão
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11 || !isset($_SESSION['user_id'])) {
    die('Acesso negado ou sessão inválida.');
}

// 2. Captura dos dados do formulário
$anx_id         = $_POST['anx_id'] ?? null;
$anx_nome_novo  = trim($_POST['anx_nome'] ?? '');
$tipo_id_novo   = $_POST['tipo_id'] ?? null;
$emp_id_novo    = $_POST['emp_id'] ?? null;
$anx_status_novo = $_POST['anx_status'] ?? null; // O novo campo de status

// 3. Captura do ID do Admin que está fazendo a atualização
$admin_user_id = $_SESSION['user_id'];

// 4. Validação
if (empty($anx_id) || empty($anx_nome_novo) || empty($tipo_id_novo) || empty($emp_id_novo) || $anx_status_novo === null) {
    header("Location: editar_anexo.php?anx_id=" . $anx_id . "&erro=" . urlencode("Todos os campos são obrigatórios."));
    exit;
}

// --- NOVO: Mapa de Status (para a mensagem da notificação) ---
$status_map = [
    0 => 'Pendente',
    1 => 'Aguardando Aprovação',
    2 => 'Aprovado',
    3 => 'Recusado'
];
// --- FIM NOVO ---

// --- NOVO: Inicia a Transação e Bloco Try/Catch ---
$conn->begin_transaction();
try {

    // --- NOVO: Passo 5a - Buscar dados ANTIGOS do anexo (para comparar) ---
    $sql_get_old = "SELECT anx_status, emp_id FROM anexo WHERE anx_id = ?";
    $stmt_get = $conn->prepare($sql_get_old);
    if (!$stmt_get) {
         throw new Exception("Erro ao preparar consulta (GET): " . $conn->error);
    }
    $stmt_get->bind_param("i", $anx_id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    if ($result_get->num_rows === 0) {
        throw new Exception("Anexo original não encontrado.");
    }
    $anexo_antigo = $result_get->fetch_assoc();
    $anx_status_antigo = $anexo_antigo['anx_status'];
    $emp_id_antigo = $anexo_antigo['emp_id']; // ID da empresa dona do doc
    $stmt_get->close();
    // --- FIM NOVO ---


    // 5. Preparação da Consulta SQL de ATUALIZAÇÃO
    $sql_update = "UPDATE anexo SET 
            anx_nome = ?, 
            tipo_id = ?, 
            emp_id = ?, 
            anx_status = ?, 
            anx_data_atualizacao = NOW(), 
            user_id_atualizacao = ? 
        WHERE anx_id = ?";

    $stmt = $conn->prepare($sql_update);

    if (!$stmt) {
        throw new Exception("Erro no SQL (UPDATE): " . $conn->error);
    }

    $stmt->bind_param("siiiii", 
        $anx_nome_novo, 
        $tipo_id_novo, 
        $emp_id_novo, 
        $anx_status_novo, 
        $admin_user_id, 
        $anx_id
    );

    // 6. Execução
    if (!$stmt->execute()) {
         throw new Exception("Falha ao atualizar: " . $stmt->error);
    }
    $stmt->close();
    
    // --- NOVO: Passo 7 - Lógica do Gatilho de Notificação ---
    
    // Compara o status antigo com o novo que veio do formulário
    if ($anx_status_novo != $anx_status_antigo) {
        
        // 7a. O Status MUDOU. Vamos buscar os usuários da empresa.
        // Usamos o $emp_id_novo (o que está no formulário),
        // caso o admin tenha mudado o dono E o status ao mesmo tempo.
        $sql_recipients = "SELECT user_id FROM usuario WHERE emp_id = ?";
        $stmt_recipients = $conn->prepare($sql_recipients);
        if (!$stmt_recipients) {
            throw new Exception("Erro ao preparar busca de destinatários: " . $conn->error);
        }
        
        $stmt_recipients->bind_param("i", $emp_id_novo);
        $stmt_recipients->execute();
        $result_recipients = $stmt_recipients->get_result();
        
        $recipient_ids = [];
        while ($row = $result_recipients->fetch_assoc()) {
            $recipient_ids[] = $row['user_id'];
        }
        $stmt_recipients->close();

        // 7b. Preparar a notificação
        if (!empty($recipient_ids)) {
            $status_texto = $status_map[$anx_status_novo] ?? 'ATUALIZADO'; // Pega o texto (ex: "Aprovado")
            
            $mensagem = "O status do seu documento '" . $anx_nome_novo . "' foi alterado para: " . $status_texto . ".";
            $link = "/fornecedores/homologacao.php"; // Link para a central do fornecedor
            
            $sql_notif = "INSERT INTO notificacoes (user_id_destino, mensagem, link) VALUES (?, ?, ?)";
            $stmt_notif = $conn->prepare($sql_notif);
            if (!$stmt_notif) {
                 throw new Exception("Erro ao preparar notificação: " . $conn->error);
            }
            
            // 7c. Loop e envio para cada usuário da empresa
            foreach ($recipient_ids as $user_id_destino) {
                $stmt_notif->bind_param("iss", $user_id_destino, $mensagem, $link);
                if (!$stmt_notif->execute()) {
                    // Se falhar, não paramos o script, mas registamos no log
                    error_log("Falha ao inserir notificação para user_id: $user_id_destino. Erro: " . $stmt_notif->error);
                }
            }
            $stmt_notif->close();
        }
    }
    // --- FIM NOVO ---
    
    // 8. Sucesso - Commit
    $conn->commit();
    $conn->close();
    
    $_SESSION['mensagem'] = "SUCESSO: Documento atualizado com sucesso!";
    header("Location: upload_anexo.php"); 
    exit;

} catch (Exception $e) {
    // 9. Erro - Rollback
    $conn->rollback();
    $conn->close();
    
    // Volta para a página de edição com o erro
    header("Location: editar_anexo.php?anx_id=" . $anx_id . "&erro=" . urlencode($e->getMessage()));
    exit;
}
?>