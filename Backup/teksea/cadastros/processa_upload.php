<?php
session_start();
include __DIR__.'/../../config/conexao.php';

$anx_nome = $_POST['anx_nome'];
$tipo_id = $_POST['tipo_id'];
$anx_data_validade = !empty($_POST['anx_data_validade']) ? $_POST['anx_data_validade'] : null;

$diretorio = realpath(__DIR__ . '/../uploads/');
if (!is_dir($diretorio)) {
    mkdir($diretorio, 0755, true);
}

$arquivo = $_FILES['anx_arquivo'];
$nome_arquivo = basename($arquivo['name']);
$nome_final = uniqid() . "_" . $nome_arquivo;
$caminho_arquivo = $diretorio . '/' . $nome_final;
$caminho_para_banco = $nome_final;

if (move_uploaded_file($arquivo['tmp_name'], $caminho_arquivo)) {
    $stmt = $conn->prepare("INSERT INTO Anexo (anx_nome, anx_arquivo, tipo_id) VALUES (?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("ssi", $anx_nome, $caminho_para_banco, $tipo_id);
        if ($stmt->execute()) {
            $_SESSION['mensagem'] = "✅ Anexo enviado com sucesso!";
        } else {
            $_SESSION['mensagem'] = "❌ Erro ao executar: " . $stmt->error;
        }
    } else {
        $_SESSION['mensagem'] = "❌ Erro ao preparar a query: " . $conn->error;
    }
} else {
    $_SESSION['mensagem'] = "❌ Erro ao mover o arquivo.";
}

header("Location: upload_anexo.php");
exit;
