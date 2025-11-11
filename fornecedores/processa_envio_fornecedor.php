<?php
// PARTE 1: CONFIGURAÇÃO E SEGURANÇA
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carrega as configurações (APP_ROOT, ROOT_PATH)
require_once __DIR__ . '/../config/config.php';

// Página para onde o usuário será redirecionado
$redirect_page = APP_ROOT . "fornecedores/homologacao.php";

// Definições de Upload
define('UPLOAD_DIR_SERVIDOR', ROOT_PATH . '/uploads/anexos/'); 
define('UPLOAD_DIR_BANCO', '/uploads/anexos/'); 

// --- Inicia o Try/Catch para tratamento de erros ---
try {
    // 1. Conexão com o banco
    require_once ROOT_PATH . '/config/conexao.php';

    // 2. Segurança: Verifica se é um Fornecedor logado e se temos os IDs
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || 
        !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 1 || 
        !isset($_SESSION['emp_id']) || !isset($_SESSION['user_id'])) { 
        
        throw new Exception("Acesso negado. A sua sessão é inválida ou expirou.");
    }

    // 3. Captura de Dados da Sessão
    $emp_id = $_SESSION['emp_id'];      // ID da Empresa do Fornecedor
    $criador_id = $_SESSION['user_id']; // ID do Usuário Fornecedor
    $status_inicial = 1; // 1 = Aguardando Aprovação

    // --- Passo 4a - Buscar dados para a Notificação ---
    
    // Buscar o Nome Fantasia do Fornecedor
    $sql_empresa = "SELECT emp_nome_fantasia FROM empresa WHERE emp_id = ?";
    $stmt_empresa = $conn->prepare($sql_empresa);
    $stmt_empresa->bind_param("i", $emp_id);
    $stmt_empresa->execute();
    $empresa_nome = $stmt_empresa->get_result()->fetch_assoc()['emp_nome_fantasia'] ?? 'Empresa ID ' . $emp_id;
    $stmt_empresa->close();

    // Buscar todos os IDs de usuários Admin (emp_id 11)
    $admin_ids = [];
    $sql_admins = "SELECT user_id FROM usuario WHERE emp_id = 11"; // 11 = Teksea Admin
    $result_admins = $conn->query($sql_admins);
    if ($result_admins) {
        while ($row = $result_admins->fetch_assoc()) {
            $admin_ids[] = $row['user_id'];
        }
    }
    // --- Fim da busca de notificação ---

    // 4. Captura de Dados do Formulário (POST)
    $anx_nome = trim($_POST['anx_nome'] ?? '');
    $tipo_id = $_POST['tipo_id'] ?? null;
    
    // --- MODIFICADO: Captura de Data e Vitalício ---
    $data_vencimento = $_POST['anx_data_vencimento'] ?? null;
    
    // O rádio 'anx_vitalicio' sempre enviará um valor (0 ou 1).
    $vitalicio = $_POST['anx_vitalicio'] ?? 0;
    $vitalicio = ($vitalicio == 1) ? 1 : 0; // Garante 1 ou 0

    // REGRA DE NEGÓCIO: Se for vitalício, data DEVE ser NULL.
    if ($vitalicio == 1) {
        $data_vencimento = NULL;
    } 
    elseif (empty($data_vencimento)) {
        $data_vencimento = NULL;
    }
    // --- FIM DA MODIFICAÇÃO ---
    
    // 5. Captura do Arquivo (FILES)
    $arquivo = $_FILES['anx_arquivo'] ?? null;

    // 6. Validação dos Dados
    if (empty($anx_nome) || empty($tipo_id) || $arquivo === null || $arquivo['error'] == UPLOAD_ERR_NO_FILE) {
        throw new Exception("O 'Nome do Documento' e o 'Arquivo' são obrigatórios.");
    }
    
    if ($arquivo['error'] != UPLOAD_ERR_OK) {
        throw new Exception("Falha no upload do arquivo (Código: " . $arquivo['error'] . ").");
    }

    // --- Inicia a transação SÓ DEPOIS das validações ---
    $conn->begin_transaction();

    // 7. Processamento e Movimentação do Arquivo
    if (!is_dir(UPLOAD_DIR_SERVIDOR)) {
        if (!mkdir(UPLOAD_DIR_SERVIDOR, 0775, true)) {
            throw new Exception("Falha ao criar o diretório de uploads. Verifique as permissões do servidor.");
        }
    }

    $nome_original = basename($arquivo['name']);
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $nome_seguro = preg_replace("/[^a-zA-Z0-9_.-]/", "", pathinfo($nome_original, PATHINFO_FILENAME));
    $nome_final = "emp" . $emp_id . "_user" . $criador_id . "_" . time() . "_" . $nome_seguro . "." . $extensao;
    $destino_servidor = UPLOAD_DIR_SERVIDOR . $nome_final;
    $destino_banco = UPLOAD_DIR_BANCO . $nome_final; 

    if (!move_uploaded_file($arquivo['tmp_name'], $destino_servidor)) {
        throw new Exception("Falha ao mover o arquivo para o diretório final.");
    }

    // 8. Inserção no Banco de Dados (Documento)
    
    // --- MODIFICADO: SQL ATUALIZADO ---
    // Adiciona a coluna 'anx_vitalicio'
    $sql = "INSERT INTO anexo (
                anx_nome, tipo_id, emp_id, anx_arquivo, 
                anx_criado_por_id, anx_status,
                user_id_atualizacao, anx_data_atualizacao,
                anx_data_vencimento, anx_vitalicio 
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)"; // <-- Adicionado um '?'
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Falha ao preparar a consulta SQL: " . $conn->error);
    }

    // --- MODIFICADO: BIND_PARAM ATUALIZADO ---
    // A string de tipos muda de "siisiiis" para "siisiiisi" (adiciona 'i' para vitalicio)
    $stmt->bind_param("siisiiisi", 
        $anx_nome, 
        $tipo_id, 
        $emp_id, 
        $destino_banco, 
        $criador_id, 
        $status_inicial, 
        $criador_id,
        $data_vencimento,
        $vitalicio // <-- Parâmetro adicionado
    );
    // --- FIM DAS MODIFICAÇÕES ---

    if (!$stmt->execute()) {
        if (file_exists($destino_servidor)) {
            unlink($destino_servidor);
        }
        throw new Exception("Falha ao salvar no banco de dados: " . $stmt->error);
    }

    // --- Passo 9 - Criação do Gatilho de Notificação ---
    
    // 9a. Obter o ID do documento que acabamos de inserir
    $novo_anexo_id = $stmt->insert_id;
    if ($novo_anexo_id == 0) {
        throw new Exception("Falha ao obter o ID do novo anexo para a notificação.");
    }

    // 9b. Preparar a consulta de notificação
    $sql_notif = "INSERT INTO notificacoes (user_id_destino, mensagem, link) 
                  VALUES (?, ?, ?)";
    $stmt_notif = $conn->prepare($sql_notif);
    if (!$stmt_notif) {
        throw new Exception("Falha ao preparar a notificação: " . $conn->error);
    }
    
    // 9c. Definir a Mensagem e o Link
    $mensagem = "O fornecedor '" . $empresa_nome . "' enviou um novo documento para aprovação: '" . $anx_nome . "'.";
    $link = "/teksea/cadastros/editar_anexo.php?anx_id=" . $novo_anexo_id;

    // 9d. Inserir uma notificação para CADA admin encontrado
    if (empty($admin_ids)) {
        error_log("Aviso: Nenhum admin (emp_id 11) encontrado para notificar sobre o Anexo ID: $novo_anexo_id.");
    } else {
        foreach ($admin_ids as $admin_id) {
            $stmt_notif->bind_param("iss", $admin_id, $mensagem, $link);
            if (!$stmt_notif->execute()) {
                throw new Exception("Falha ao criar notificação para o admin $admin_id: " . $stmt_notif->error);
            }
        }
    }
    $stmt_notif->close();
    // --- FIM NOTIFICAÇÃO ---

    $stmt->close();
    
    // 10. Sucesso - Commit
    $conn->commit(); // Salva o Anexo E as Notificações no banco
    $conn->close();
    
    header("Location: $redirect_page?sucesso=" . urlencode("Documento enviado com sucesso! Aguardando aprovação."));
    exit;

} catch (Exception $e) {
    // 11. Captura de Erro
    if (isset($conn)) $conn->rollback(); // Desfaz tudo se algo falhou
    if (isset($conn)) $conn->close(); 
    
    // Deleta o arquivo se ele foi salvo mas o DB falhou
    if (isset($destino_servidor) && file_exists($destino_servidor)) {
        unlink($destino_servidor);
    }
    
    header("Location: $redirect_page?erro=" . urlencode($e->getMessage()));
    exit;
}
?>