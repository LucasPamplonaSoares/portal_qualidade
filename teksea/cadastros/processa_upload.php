<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';

// --- CONFIGURAÇÕES ---
$redirect_page = "upload_anexo.php"; 
define('UPLOAD_DIR_SERVIDOR', __DIR__ . '/../../uploads/anexos/'); 
define('UPLOAD_DIR_BANCO', '/uploads/anexos/'); 
// ---------------------

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 1. Segurança e Captura de IDs da Sessão
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11 || !isset($_SESSION['user_id'])) {
        throw new Exception("ERRO: Acesso negado ou sessão inválida. Faça login novamente.");
    }
    
    $criador_id = $_SESSION['user_id'];
    $status_inicial = 0; 

    // 2. Captura de Dados do Formulário (POST)
    $anx_nome = trim($_POST['anx_nome'] ?? '');
    $tipo_id = $_POST['tipo_id'] ?? null;
    $emp_id = $_POST['emp_id'] ?? null;
    
    // --- MODIFICADO: Captura de Data de Vencimento e Vitalício ---
    $data_vencimento = $_POST['anx_data_vencimento'] ?? null;
    
    // --- LÓGICA ATUALIZADA ---
    // O rádio 'anx_vitalicio' sempre enviará um valor (0 ou 1).
    // O '?? 0' é uma segurança caso algo falhe.
    $vitalicio = $_POST['anx_vitalicio'] ?? 0;
    
    // Garante que é 1 ou 0
    $vitalicio = ($vitalicio == 1) ? 1 : 0;
    
    // REGRA DE NEGÓCIO: Se o documento for vitalício, a data de vencimento DEVE ser NULL.
    if ($vitalicio == 1) {
        $data_vencimento = NULL;
    } 
    // Se não for vitalício, mas a data veio vazia, define como NULL.
    elseif (empty($data_vencimento)) {
        $data_vencimento = NULL;
    }
    // --- FIM DA MODIFICAÇÃO ---
    
    // 3. Captura do Arquivo (FILES)
    $arquivo = $_FILES['anx_arquivo'] ?? null;

    // 4. Validação dos Dados
    if (empty($anx_nome) || empty($tipo_id) || empty($emp_id) || $arquivo === null || $arquivo['error'] == UPLOAD_ERR_NO_FILE) {
        throw new Exception("ERRO: Todos os campos (exceto vencimento) e um arquivo são obrigatórios.");
    }
    
    if ($arquivo['error'] != UPLOAD_ERR_OK) {
        throw new Exception("ERRO: Falha no upload do arquivo (Código: " . $arquivo['error'] . ").");
    }

    // 5. Processamento e Movimentação do Arquivo
    if (!is_dir(UPLOAD_DIR_SERVIDOR)) {
        if (!mkdir(UPLOAD_DIR_SERVIDOR, 0775, true)) {
            throw new Exception("ERRO: Falha ao criar o diretório de uploads. Verifique as permissões do servidor.");
        }
    }

    $nome_original = basename($arquivo['name']);
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $nome_seguro = preg_replace("/[^a-zA-Z0-9_.-]/", "", pathinfo($nome_original, PATHINFO_FILENAME));
    $nome_final = "emp" . $emp_id . "_user" . $criador_id . "_" . time() . "_" . $nome_seguro . "." . $extensao;
    $destino_servidor = UPLOAD_DIR_SERVIDOR . $nome_final;
    $destino_banco = UPLOAD_DIR_BANCO . $nome_final; 

    if (!move_uploaded_file($arquivo['tmp_name'], $destino_servidor)) {
        throw new Exception("ERRO: Falha ao mover o arquivo para o diretório final.");
    }

    // 6. Inserção no Banco de Dados
    
    // O SQL e o bind_param (siisiisi) da sua versão anterior já estavam corretos
    // para aceitar os 8 campos (incluindo $vitalicio).
    $sql = "INSERT INTO anexo (
                anx_nome, tipo_id, emp_id, anx_arquivo, 
                anx_criado_por_id, anx_status, 
                anx_data_vencimento, anx_vitalicio
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; // <-- 8 campos
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("ERRO: Falha ao preparar a consulta SQL: "."$conn->error");
    }

    $stmt->bind_param("siisiisi", 
        $anx_nome, 
        $tipo_id, 
        $emp_id, 
        $destino_banco, 
        $criador_id, 
        $status_inicial, 
        $data_vencimento, 
        $vitalicio
    );

    if (!$stmt->execute()) {
        if (file_exists($destino_servidor)) {
            unlink($destino_servidor);
        }
        throw new Exception("ERRO: Falha ao salvar no banco de dados: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // 7. Sucesso
    $_SESSION['mensagem'] = "SUCESSO: Documento enviado e marcado como 'Pendente'.";
    header("Location: $redirect_page");
    exit;

} catch (Exception $e) {
    // 8. Captura de Erro
    $_SESSION['mensagem'] = $e->getMessage(); 
    if (isset($conn)) $conn->close(); 
    header("Location: $redirect_page");
    exit;
}
?>