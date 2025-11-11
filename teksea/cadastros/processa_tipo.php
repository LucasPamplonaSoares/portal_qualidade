<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexao.php';

// 1. SEGURANÇA: Garante que apenas administradores possam executar este script.
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    // Grava uma mensagem de erro na sessão para ser exibida na página anterior.
    $_SESSION['mensagem'] = "❌ ERRO: Acesso não autorizado.";
    header("Location: " . APP_ROOT . "teksea/user.php");
    exit;
}

// Verifica se os dados foram enviados
if (!empty($_POST['nova_descricao']) && !empty($_POST['tipo_acao'])) {

    // Prepara a consulta UMA VEZ, fora do loop, para melhor performance.
    // 2. NOME DA TABELA CORRIGIDO para 'tipo' (minúsculo).
    $stmt = $conn->prepare("INSERT INTO tipo (tipo_descricao, tipo_acao) VALUES (?, ?)");
    if (!$stmt) {
        $_SESSION['mensagem'] = "❌ ERRO: Falha ao preparar a consulta: " . $conn->error;
        header("Location: " . APP_ROOT . "teksea/user.php");
        exit;
    }

    // 3. LÓGICA DO LOOP CORRIGIDA
    // Agora, iteramos pelo array de descrições e pegamos o tipo de ação correspondente pelo índice.
    foreach ($_POST['nova_descricao'] as $indice => $descricao) {
        // Pega a ação correspondente usando o mesmo índice
        $tipo_acao = $_POST['tipo_acao'][$indice] ?? null;

        // Valida se ambos os campos para esta linha não estão vazios
        if (!empty(trim($descricao)) && $tipo_acao !== null) {
            // Converte tipo_acao para inteiro
            $tipo_acao_int = intval($tipo_acao);

            // "s" para string (descricao), "i" para integer (tipo_acao)
            $stmt->bind_param("si", $descricao, $tipo_acao_int);
            $stmt->execute();
        }
    }
    
    $stmt->close();
    $_SESSION['mensagem'] = "✅ Categoria(s) salva(s) com sucesso!";

} else {
    $_SESSION['mensagem'] = "❌ ERRO: Nenhum dado válido enviado.";
}

$conn->close();
// Redireciona de volta para a página de configurações com a mensagem de feedback.
header("Location: " . APP_ROOT . "teksea/user.php");
exit;
?>