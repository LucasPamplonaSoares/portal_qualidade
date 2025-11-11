<?php
// PARTE 1: CONFIGURAÇÃO E SEGURANÇA
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carrega as configurações
require_once __DIR__ . '/../config/config.php';

// Página para onde o usuário será redirecionado
$redirect_page = "homologacao.php"; 

// Definições de Upload
define('UPLOAD_DIR_SERVIDOR', ROOT_PATH . '/uploads/anexos/'); 
define('UPLOAD_DIR_BANCO', '/uploads/anexos/'); 

// --- Inicia o Try/Catch para tratamento de erros ---
try {
    // 1. Conexão com o banco
    require_once ROOT_PATH . '/config/conexao.php';

    // 2. Segurança: Verifica se é Fornecedor (tipo 1) e se os IDs de sessão existem
    if (!isset($_SESSION['logado']) || $_SESSION['emp_tipo'] != 1 || !isset($_SESSION['emp_id']) || !isset($_SESSION['user_id'])) {
        throw new Exception("Acesso negado ou sessão inválida.");
    }
    
    // 3. Captura de Dados da Sessão
    $emp_id_session = $_SESSION['emp_id']; // ID da EMPRESA do fornecedor
    $user_id_session = $_SESSION['user_id']; // ID do USUÁRIO do fornecedor
    $novo_status = 1; // 1 = Aguardando Aprovação (Qualquer atualização re-submete para aprovação)

    // 4. Captura de Dados (POST e FILES)
    $anx_id = $_POST['anx_id'] ?? null;
    $novo_arquivo = $_FILES['novo_anexo'] ?? null; 
    
    // --- INÍCIO DAS MODIFICAÇÕES ---
    
    // 4a. Captura dos novos campos de Data e Vitalício
    $data_vencimento = $_POST['anx_data_vencimento'] ?? null;
    $vitalicio = $_POST['anx_vitalicio'] ?? 0;
    $vitalicio = ($vitalicio == 1) ? 1 : 0; // Garante 1 ou 0

    // REGRA DE NEGÓCIO: Se for vitalício, data DEVE ser NULL.
    if ($vitalicio == 1) {
        $data_vencimento = NULL;
    } 
    elseif (empty($data_vencimento)) {
        $data_vencimento = NULL;
    }
    
    // 5. Validação
    if (empty($anx_id)) {
        throw new Exception("ID do anexo não fornecido.");
    }

    // 5a. Verifica se um arquivo foi enviado E se deu erro
    $file_error = (isset($novo_arquivo) && $novo_arquivo['error'] != UPLOAD_ERR_OK && $novo_arquivo['error'] != UPLOAD_ERR_NO_FILE);
    if ($file_error) {
         throw new Exception("Falha no upload do arquivo (Código: " . $novo_arquivo['error'] . ").");
    }

    // 5b. Verifica se um arquivo foi realmente enviado com sucesso
    $file_uploaded = (isset($novo_arquivo) && $novo_arquivo['error'] == UPLOAD_ERR_OK);
    
    // 5c. Validação final: Se não enviou um arquivo novo E não mudou data/status, não faz sentido
    // (Esta validação é opcional, mas boa prática. Vou deixá-la comentada por enquanto)
    // if (!$file_uploaded && $data_vencimento === null && $vitalicio == 0) {
    //    throw new Exception("Nenhuma alteração detectada. Envie um novo arquivo ou atualize a data de vencimento.");
    // }

    // 6. Buscar dados do anexo antigo (Nome e Caminho)
    $sql_get_old = "SELECT anx_arquivo, anx_nome FROM anexo WHERE anx_id = ? AND emp_id = ?";
    $stmt_get = $conn->prepare($sql_get_old);
    $stmt_get->bind_param("ii", $anx_id, $emp_id_session);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    if ($result_get->num_rows === 0) {
        throw new Exception("Anexo não encontrado ou não pertence à sua empresa.");
    }
    $anexo_antigo = $result_get->fetch_assoc();
    $caminho_arquivo_antigo_relativo = $anexo_antigo['anx_arquivo'];
    $anx_nome_documento = $anexo_antigo['anx_nome']; // Nome para a mensagem
    $stmt_get->close();

    // 7. Buscar dados para a Notificação (Nome da Empresa e Admins)
    $sql_empresa = "SELECT emp_nome_fantasia FROM empresa WHERE emp_id = ?";
    $stmt_empresa = $conn->prepare($sql_empresa);
    $stmt_empresa->bind_param("i", $emp_id_session);
    $stmt_empresa->execute();
    $empresa_nome = $stmt_empresa->get_result()->fetch_assoc()['emp_nome_fantasia'] ?? 'Empresa ID ' . $emp_id_session;
    $stmt_empresa->close();

    $admin_ids = [];
    $sql_admins = "SELECT user_id FROM usuario WHERE emp_id = 11"; // 11 = Teksea Admin
    $result_admins = $conn->query($sql_admins);
    if ($result_admins) {
        while ($row = $result_admins->fetch_assoc()) {
            $admin_ids[] = $row['user_id'];
        }
    }
    
    // --- FIM DAS MODIFICAÇÕES ---

    // 8. Inicia a transação
    $conn->begin_transaction();

    // 9. Variáveis para o SQL dinâmico
    $destino_servidor = null; // Caminho completo do NOVO arquivo (se houver)
    $sql_parts = [];
    $params = [];
    $types = "";

    // 10. Processamento do Arquivo (SE UM NOVO ARQUIVO FOI ENVIADO)
    if ($file_uploaded) {
        if (!is_dir(UPLOAD_DIR_SERVIDOR)) {
            if (!mkdir(UPLOAD_DIR_SERVIDOR, 0775, true)) {
                throw new Exception("Falha ao criar diretório de upload. Verifique permissões.");
            }
        }
        
        $nome_original = basename($novo_arquivo['name']);
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
        $nome_seguro = preg_replace("/[^a-zA-Z0-9_.-]/", "", pathinfo($nome_original, PATHINFO_FILENAME));
        $nome_final = "emp" . $emp_id_session . "_user" . $user_id_session . "_FORNEC_" . time() . "_" . $nome_seguro . "." . $extensao;
        $destino_servidor = UPLOAD_DIR_SERVIDOR . $nome_final;
        $destino_banco = UPLOAD_DIR_BANCO . $nome_final; 

        if (!move_uploaded_file($novo_arquivo['tmp_name'], $destino_servidor)) {
            throw new Exception("Falha ao mover o novo arquivo."); // Irá acionar o rollback
        }
        
        // Adiciona a atualização do arquivo ao SQL
        $sql_parts[] = "anx_arquivo = ?";
        $params[] = $destino_banco;
        $types .= "s";
    }

    // 11. ATUALIZAÇÃO NO BANCO DE DADOS (Dinâmico)
    
    // Campos que SEMPRE são atualizados
    $sql_parts[] = "anx_status = ?";
    $params[] = $novo_status;
    $types .= "i";
    
    $sql_parts[] = "anx_data_atualizacao = NOW()";
    
    $sql_parts[] = "user_id_atualizacao = ?";
    $params[] = $user_id_session;
    $types .= "i";
    
    $sql_parts[] = "anx_data_vencimento = ?";
    $params[] = $data_vencimento;
    $types .= "s";
    
    $sql_parts[] = "anx_vitalicio = ?";
    $params[] = $vitalicio;
    $types .= "i";

    // Constroi a query
    $sql_update = "UPDATE anexo SET " . implode(", ", $sql_parts) . 
                  " WHERE anx_id = ? AND emp_id = ?";
                  
    // Adiciona os parâmetros do WHERE
    $params[] = $anx_id;
    $types .= "i";
    $params[] = $emp_id_session;
    $types .= "i";
            
    $stmt = $conn->prepare($sql_update);
    if (!$stmt) {
        throw new Exception("Erro de SQL: " . $conn->error);
    }
    
    // Faz o bind dinâmico
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        if ($file_uploaded && file_exists($destino_servidor)) {
            unlink($destino_servidor); // Deleta o NOVO arquivo
        }
        throw new Exception("Falha ao atualizar o banco de dados: " . $stmt->error);
    }
    $stmt->close();

    // 12. Criação do Gatilho de Notificação
    
    $sql_notif = "INSERT INTO notificacoes (user_id_destino, mensagem, link) 
                  VALUES (?, ?, ?)";
    $stmt_notif = $conn->prepare($sql_notif);
    if (!$stmt_notif) {
        throw new Exception("Falha ao preparar a notificação: " . $conn->error);
    }
    
    // --- MENSAGEM DINÂMICA ---
    if ($file_uploaded) {
        $mensagem = "O fornecedor '" . $empresa_nome . "' ATUALIZOU o documento para aprovação: '" . $anx_nome_documento . "'.";
    } else {
        $mensagem = "O fornecedor '" . $empresa_nome . "' ATUALIZOU A DATA do documento: '" . $anx_nome_documento . "'.";
    }
    // --- FIM MENSAGEM ---
    
    $link = "/teksea/cadastros/editar_anexo.php?anx_id=" . $anx_id;

    if (empty($admin_ids)) {
        error_log("Aviso: Nenhum admin (emp_id 11) encontrado para notificar sobre a ATUALIZAÇÃO do Anexo ID: $anx_id.");
    } else {
        foreach ($admin_ids as $admin_id) {
            $stmt_notif->bind_param("iss", $admin_id, $mensagem, $link);
            if (!$stmt_notif->execute()) {
                throw new Exception("Falha ao criar notificação para o admin $admin_id: " . $stmt_notif->error);
            }
        }
    }
    $stmt_notif->close();
    
    // 13. Limpeza (Deletar o arquivo antigo, SE um novo foi enviado)
    if ($file_uploaded && !empty($caminho_arquivo_antigo_relativo)) {
        $caminho_antigo_absoluto = ROOT_PATH . $caminho_arquivo_antigo_relativo;
        if (file_exists($caminho_antigo_absoluto)) {
            unlink($caminho_antigo_absoluto);
        }
    }

    // 14. Sucesso
    $conn->commit(); 
    $conn->close();
    
    header("Location: $redirect_page?sucesso=" . urlencode("Documento atualizado e enviado para aprovação!"));
    exit;

} catch (Exception $e) {
    // 15. Captura de Erro
    if (isset($conn)) $conn->rollback(); 
    if (isset($conn)) $conn->close();
    
    // Deleta o NOVO arquivo que foi enviado, se a transação falhou
    if (isset($destino_servidor) && file_exists($destino_servidor)) {
        unlink($destino_servidor);
    }
    
    header("Location: $redirect_page?erro=" . urlencode($e->getMessage()));
    exit;
}
?>