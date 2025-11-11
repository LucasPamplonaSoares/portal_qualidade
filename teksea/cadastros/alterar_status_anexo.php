<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexao.php';

// Apenas admins
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    die('Acesso negado.');
}

$anx_id = $_GET['id'] ?? null;
$novo_status = $_GET['status'] ?? null;

if ($anx_id && is_numeric($novo_status)) {
    $stmt = $conn->prepare("UPDATE anexo SET anx_status = ? WHERE anx_id = ?");
    $stmt->bind_param("ii", $novo_status, $anx_id);
    $stmt->execute();
    $_SESSION['mensagem'] = "Status do anexo alterado com sucesso!";
} else {
    $_SESSION['mensagem'] = "❌ ERRO: Dados inválidos para alterar status.";
}
header("Location: " . APP_ROOT . "teksea/cadastros/upload_anexo.php");
exit;
?>