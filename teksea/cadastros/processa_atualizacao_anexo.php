<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/config.php';

// --- VERIFICAÇÕES DE SEGURANÇA ---
// 1. Apenas usuários logados
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    die("Acesso negado.");
}

// 2. Garante que os dados necessários foram enviados
if (!isset($_POST['anx_id']) || !isset($_FILES['novo_anexo']) || $_FILES['novo_anexo']['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . APP_ROOT . "fornecedores/homologacao.php?erro=" . urlencode("Falha no envio do arquivo. Por favor, tente novamente."));
    exit;
}

$anx_id = intval($_POST['anx_id']);
$emp_id_usuario = $_SESSION['emp_id']; // ID da empresa do usuário logado
$novo_arquivo = $_FILES['novo_anexo'];

try {
    $conn->begin_transaction();

    // 3. SEGURANÇA: Busca o anexo e VERIFICA SE O USUÁRIO É O DONO
    $stmt = $conn->prepare("SELECT anx_arquivo, emp_id FROM anexo WHERE anx_id = ?");
    $stmt->bind_param("i", $anx_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        throw new Exception("Anexo não encontrado.");
    }
    $anexo_antigo = $result->fetch_assoc();
    $stmt->close();

    // Compara o emp_id do anexo com o emp_id do usuário na sessão
    // Apenas o admin (emp_id 11) ou o dono do arquivo podem alterar
    if ($anexo_antigo['emp_id'] != $emp_id_usuario && $_SESSION['emp_id'] != 11) {
        throw new Exception("Você não tem permissão para alterar este anexo.");
    }

    // 4. APAGA O ARQUIVO FÍSICO ANTIGO
    $caminho_antigo = realpath(__DIR__ . '/../uploads/') . '/' . $anexo_antigo['anx_arquivo'];
    if (file_exists($caminho_antigo)) {
        unlink($caminho_antigo);
    }

    // 5. SALVA O NOVO ARQUIVO
    $diretorio_uploads = realpath(__DIR__ . '/../uploads/');
    $nome_original = basename($novo_arquivo['name']);
    $nome_final_novo = uniqid() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "", $nome_original);
    $caminho_novo = $diretorio_uploads . '/' . $nome_final_novo;

    if (!move_uploaded_file($novo_arquivo['tmp_name'], $caminho_novo)) {
        throw new Exception("Falha ao salvar o novo arquivo no servidor.");
    }

    // 6. ATUALIZA O REGISTRO NO BANCO com o nome do novo arquivo
    // A coluna 'anx_data_atualizacao' será atualizada AUTOMATICAMENTE pelo banco!
    $stmt_update = $conn->prepare("UPDATE anexo SET anx_arquivo = ? WHERE anx_id = ?");
    $stmt_update->bind_param("si", $nome_final_novo, $anx_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Falha ao atualizar o registro no banco de dados.");
    }
    $stmt_update->close();

    // Se tudo deu certo:
    $conn->commit();
    header("Location: " . APP_ROOT . "fornecedores/homologacao.php?sucesso=" . urlencode("Documento atualizado com sucesso!"));

} catch (Exception $e) {
    // Se algo deu errado:
    $conn->rollback();
    header("Location: " . APP_ROOT . "fornecedores/homologacao.php?erro=" . urlencode($e->getMessage()));
}

$conn->close();
exit;
?>