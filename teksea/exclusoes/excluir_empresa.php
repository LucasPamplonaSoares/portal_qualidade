<?php
session_start();
require_once __DIR__.'/../../config/conexao.php';
require_once __DIR__.'/../../config/config.php'; // Inclui para usar a constante APP_ROOT

// 1. SEGURANÇA: Garante que apenas administradores possam excluir.
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    // Se não for admin, redireciona para a home com mensagem de erro.
    $_SESSION['mensagem_erro'] = "Você não tem permissão para executar esta ação.";
    header("Location: " . APP_ROOT . "teksea/home.php");
    exit;
}

if (isset($_GET['emp_id'])) {
    $emp_id = intval($_GET['emp_id']); // Garante que seja um número inteiro

    // 2. NOME DA TABELA: Usando 'empresa' em minúsculo por segurança.
    $stmt = $conn->prepare("DELETE FROM empresa WHERE emp_id = ?");
    if (!$stmt) {
        $erro = "Erro na preparação da consulta: " . $conn->error;
        // 3. REDIRECIONAMENTO CORRIGIDO: Caminho absoluto e em minúsculo.
        header("Location: /teksea/cadastros/empresas.php?erro=" . urlencode($erro));
        exit;
    }

    $stmt->bind_param("i", $emp_id);

    if ($stmt->execute()) {
        // Sucesso na exclusão
        header("Location: /teksea/cadastros/empresas.php?sucesso=" . urlencode("Empresa excluída com sucesso!"));
        exit;
    } else {
        // Erro na execução
        $erro = "Erro ao excluir a empresa: " . $stmt->error;
        header("Location: /teksea/cadastros/empresas.php?erro=" . urlencode($erro));
        exit;
    }
} else {
    // Se nenhum ID for passado
    header("Location: /teksea/cadastros/empresas.php?erro=" . urlencode("Nenhum ID de empresa fornecido."));
    exit;
}
?>