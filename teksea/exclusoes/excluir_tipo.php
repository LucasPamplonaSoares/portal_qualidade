<?php
session_start();
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/conexao.php';

// 1. SEGURANÇA: Garante que apenas administradores possam excluir.
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    // Se não for admin, redireciona para a home com mensagem de erro.
    header("Location: " . APP_ROOT . "teksea/home.php?erro=" . urlencode("Acesso não autorizado."));
    exit;
}

// Verifica se o ID foi passado e é um número
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tipo_id = intval($_GET['id']);

    // 2. NOME DA TABELA: Corrigido para 'tipo' (minúsculo).
    $stmt = $conn->prepare("DELETE FROM tipo WHERE tipo_id = ?");
    
    if (!$stmt) {
        $erro = "Erro na preparação da consulta: " . $conn->error;
        // 3. REDIRECIONAMENTO: Usando caminho absoluto e mensagens claras.
        header("Location: " . APP_ROOT . "teksea/user.php?erro=" . urlencode($erro));
        exit;
    }

    $stmt->bind_param("i", $tipo_id);

    if ($stmt->execute()) {
        // Sucesso na exclusão
        header("Location: " . APP_ROOT . "teksea/user.php?sucesso=" . urlencode("Categoria excluída com sucesso!"));
        exit;
    } else {
        // Erro na execução (ex: a categoria está em uso por um anexo)
        $erro = "Erro ao excluir a categoria: " . $stmt->error;
        header("Location: " . APP_ROOT . "teksea/user.php?erro=" . urlencode($erro));
        exit;
    }
} else {
    // Se nenhum ID válido for passado
    header("Location: " . APP_ROOT . "teksea/user.php?erro=" . urlencode("ID de categoria inválido."));
    exit;
}
?>