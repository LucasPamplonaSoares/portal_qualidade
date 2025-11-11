<?php
session_start();
require_once __DIR__.'/../../config/conexao.php';

// 1. VERIFICAÇÃO DE SEGURANÇA
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    $_SESSION['mensagem'] = "❌ ERRO: Acesso não autorizado para exclusão.";
    header("Location: /teksea/home.php");
    exit;
}

// Verifica se o ID do anexo foi passado pela URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['mensagem'] = "❌ ERRO: ID de anexo inválido.";
    // MUDANÇA IMPORTANTE: Usando caminho absoluto no redirecionamento
    header("Location: /teksea/cadastros/upload_anexo.php");
    exit;
}

$anx_id = $_GET['id'];

$conn->begin_transaction();

try {
    // Busca o nome do arquivo no banco (usando tabela 'anexo' minúscula)
    $stmt_select = $conn->prepare("SELECT anx_arquivo FROM anexo WHERE anx_id = ?");
    if (!$stmt_select) throw new Exception("Erro ao preparar a busca do arquivo.");
    
    $stmt_select->bind_param("i", $anx_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Anexo não encontrado no banco de dados.");
    }
    
    $row = $result->fetch_assoc();
    $nome_arquivo = $row['anx_arquivo'];
    $stmt_select->close();

    // Deleta o registro do banco de dados
    $stmt_delete = $conn->prepare("DELETE FROM anexo WHERE anx_id = ?");
    if (!$stmt_delete) throw new Exception("Erro ao preparar a exclusão.");

    $stmt_delete->bind_param("i", $anx_id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Erro ao excluir o registro do banco.");
    }
    $stmt_delete->close();

    // Deleta o arquivo físico do servidor
    $caminho_arquivo = realpath(__DIR__ . '/../uploads/') . '/' . $nome_arquivo;
    if (!empty($nome_arquivo) && file_exists($caminho_arquivo)) {
        unlink($caminho_arquivo);
    }

    $conn->commit();
    $_SESSION['mensagem'] = "✅ Anexo excluído com sucesso!";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['mensagem'] = "❌ ERRO: " . $e->getMessage();
}

// MUDANÇA IMPORTANTE: Usando caminho absoluto para redirecionar de volta para a página certa
header("Location: /teksea/cadastros/upload_anexo.php");
exit;
?>