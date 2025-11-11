<?php
session_start();
require_once __DIR__.'/../../config/conexao.php';

$nome = $_POST['nome'] ?? '';
$sobrenome = $_POST['sobrenome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar-senha'] ?? '';
$empresa = $_POST['emp_id'] ?? '';

// Validação básica
if ($senha !== $confirmar_senha) {
    echo "As senhas não coincidem.";
    header("Location: ../cadastros/usuarios.php?error=" . urlencode("As senhas não coincidem."));
    exit;
}

// Criptografar a senha
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// Buscar o emp_id da empresa (supondo que você tenha uma tabela Empresa com nome ou código)
$stmt_empresa = $conn->prepare("SELECT emp_id FROM Empresa WHERE emp_id = ?");
if (!$stmt_empresa) {
    die("Erro na preparação da consulta: " . $conn->error);
}
$stmt_empresa->bind_param("s", $empresa);
$stmt_empresa->execute();
$result_empresa = $stmt_empresa->get_result();

if ($result_empresa->num_rows === 0) {
    echo "Empresa não encontrada.";
    header("Location: ../cadastros/usuarios.php?error=" . urlencode("Empresa não encontrada."));
    exit;
}

$empresa_data = $result_empresa->fetch_assoc();
$emp_id = $empresa_data['emp_id'];

// Inserir usuário
$stmt = $conn->prepare("INSERT INTO Usuario (user_nome, user_sobrenome, user_email, user_senha, emp_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssi", $nome, $sobrenome, $email, $senha_hash, $emp_id);

if ($stmt->execute()) {
    header("Location: usuarios.php?sucesso=2");
    exit;
} else {
    header("Location: usuarios.php?erro=2");
    exit;
}
?>
