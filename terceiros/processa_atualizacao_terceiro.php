<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/config/conexao.php';

// Habilitar TODOS os erros para depuração
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Diretórios
define('UPLOAD_DIR_SERVIDOR', ROOT_PATH . '/uploads/anexos/'); 
define('UPLOAD_DIR_BANCO', '/uploads/anexos/'); 

// Página de redirecionamento em caso de SUCESSO
$redirect_page_sucesso = "home.php";

try {
    // 1. Segurança
    if (!isset($_SESSION['logado']) || $_SESSION['emp_tipo'] != 2 || !isset($_SESSION['emp_id']) || !isset($_SESSION['user_id'])) {
        throw new Exception("Acesso negado ou sessão inválida.");
    }
    
    $emp_id_session = $_SESSION['emp_id'];
    $user_id_session = $_SESSION['user_id'];

    // 2. Captura de Dados
    $anx_id = $_POST['anx_id'] ?? null;
    $caminho_arquivo_antigo_relativo = $_POST['caminho_arquivo_antigo'] ?? '';
    $novo_arquivo = $_FILES['novo_arquivo'] ?? null;
    
    // 3. Validação
    if (empty($anx_id)) {
        throw new Exception("ERRO CRÍTICO: O 'anx_id' (ID do anexo) não foi enviado pelo formulário.");
    }
    if ($novo_arquivo === null || $novo_arquivo['error'] != UPLOAD_ERR_OK) {
        // Esta é a excepção que está a ser ativada
        throw new Exception("Nenhum arquivo enviado ou erro no upload. (Código de Erro: " . ($novo_arquivo['error'] ?? 'N/A') . ")");
    }
    
    // 4. Processamento do Arquivo
    if (!is_dir(UPLOAD_DIR_SERVIDOR)) {
        if (!mkdir(UPLOAD_DIR_SERVIDOR, 0775, true)) {
            throw new Exception("Falha ao criar diretório de upload. Verifique permissões.");
        }
    }
    
    $nome_original = basename($novo_arquivo['name']);
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $nome_seguro = preg_replace("/[^a-zA-Z0-9_.-]/", "", pathinfo($nome_original, PATHINFO_FILENAME));
    $nome_final = "emp" . $emp_id_session . "_user" . $user_id_session . "_UPDATE_" . time() . "_" . $nome_seguro . "." . $extensao;
    $destino_servidor = UPLOAD_DIR_SERVIDOR . $nome_final;
    $destino_banco = UPLOAD_DIR_BANCO . $nome_final; 

    if (!move_uploaded_file($novo_arquivo['tmp_name'], $destino_servidor)) {
        throw new Exception("Falha ao mover o novo arquivo.");
    }

    // 5. ATUALIZAÇÃO NO BANCO DE DADOS
    $novo_status = 1; // 1 = "Aguardando Aprovação"
    
    $sql_update = "UPDATE anexo SET 
                        anx_arquivo = ?,
                        anx_status = ?,
                        anx_data_atualizacao = NOW(),
                        user_id_atualizacao = ?
                   WHERE 
                        anx_id = ? AND emp_id = ?";
                       
    $stmt = $conn->prepare($sql_update);
    if (!$stmt) {
        throw new Exception("Erro de SQL: " . $conn->error);
    }
    
    $stmt->bind_param("siiii", $destino_banco, $novo_status, $user_id_session, $anx_id, $emp_id_session);

    if (!$stmt->execute()) {
        if (file_exists($destino_servidor)) {
            unlink($destino_servidor);
        }
        throw new Exception("Falha ao atualizar o banco de dados: " . $stmt->error);
    }
    $stmt->close();
    
    // 6. Limpeza (Deletar o arquivo antigo)
    if (!empty($caminho_arquivo_antigo_relativo)) {
        $caminho_antigo_absoluto = ROOT_PATH . $caminho_arquivo_antigo_relativo;
        if (file_exists($caminho_antigo_absoluto)) {
            unlink($caminho_antigo_absoluto);
        }
    }

    // 7. Sucesso
    $conn->close();
    header("Location: $redirect_page_sucesso?sucesso=" . urlencode("Documento atualizado e enviado para aprovação!"));
    exit;

} catch (Exception $e) {
    // ########################################################
    // ##          NOVO BLOCO DE DEPURAÇÃO (catch)           ##
    // ########################################################
    if (isset($conn)) $conn->close();
    
    // Em vez de redirecionar, vamos parar o script e mostrar tudo.
    // Isto VAI QUEBRAR o loop "ERR_TOO_MANY_REDIRECTS".
    
    echo "<h1>Erro Crítico no Processamento</h1>";
    echo "<p>O script 'processa_atualizacao_terceiro.php' parou por segurança.</p>";
    echo "<p><strong>Mensagem de Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<hr>";
    echo "<h3>Dados POST Recebidos (o que o formulário enviou):</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    echo "<h3>Dados FILES Recebidos (o arquivo):</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    // Paramos o script aqui.
    die("Depuração terminada."); 
    // ########################################################
    // ##                FIM DO BLOCO DE DEPURAÇÃO           ##
    // ########################################################
}
?>