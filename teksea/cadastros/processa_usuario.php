<?php
session_start();
require_once __DIR__.'/../../config/conexao.php';

// --- MODIFICADO: Verificação de Segurança ---
// Adicionamos a verificação !isset($_SESSION['user_id'])
// Isto garante que sabemos QUEM está a criar o novo usuário.
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11 || !isset($_SESSION['user_id'])) {
    die('Acesso negado ou sessão inválida. Faça login novamente.');
}

// --- NOVO: Captura do ID do Admin ---
// Pegamos o user_id do admin que está logado na sessão.
$criado_por_id = $_SESSION['user_id'];

// --- Captura dos dados do formulário ---
$nome = $_POST['nome'] ?? '';
$sobrenome = $_POST['sobrenome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar-senha'] ?? '';
$emp_id = $_POST['emp_id'] ?? ''; 

// Validação básica
if (empty($nome) || empty($email) || empty($senha) || empty($emp_id)) {
    header("Location: usuarios.php?erro=" . urlencode("Todos os campos são obrigatórios."));
    exit;
}

if ($senha !== $confirmar_senha) {
    header("Location: usuarios.php?erro=" . urlencode("As senhas não coincidem."));
    exit;
}

// Criptografar a senha
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// --- MODIFICADO: Consulta SQL ---
// Adicionamos a nova coluna 'criado_por_id' ao INSERT.
// (Não precisamos adicionar 'data_criacao', pois o banco faz isso sozinho)
$stmt = $conn->prepare("INSERT INTO usuario (user_nome, user_sobrenome, user_email, user_senha, emp_id, criado_por_id) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    header("Location: usuarios.php?erro=" . urlencode("Erro na preparação da consulta: " . $conn->error));
    exit;
}

// --- MODIFICADO: bind_param ---
// Mudamos de "ssssi" para "ssssii" (o último 'i' é para o $criado_por_id)
$stmt->bind_param("ssssii", $nome, $sobrenome, $email, $senha_hash, $emp_id, $criado_por_id);

if ($stmt->execute()) {
    // Sucesso
    header("Location: usuarios.php?sucesso=" . urlencode("Usuário cadastrado com sucesso!"));
    exit;
} else {
    // Erro na execução (ex: email duplicado)
    $erro = $stmt->error;
    $stmt->close();
    $conn->close();
    header("Location: usuarios.php?erro=" . urlencode("Erro ao cadastrar usuário: " . $erro));
    exit;
}

// Este código não será alcançado, mas é uma boa prática
$stmt->close();
$conn->close();
?>