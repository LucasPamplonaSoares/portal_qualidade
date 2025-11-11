<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$host = "br606.hostgator.com.br";
$user = "teksea22_lucas";
$pass = "SUA_SENHA_AQUI"; 
$db   = "teksea22_portal_fornecedores";

// Conexão
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status"=>"erro","mensagem"=>"Erro de conexão com DB"]);
    exit;
}

// Recebe dados
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    echo json_encode(["status"=>"erro","mensagem"=>"Preencha todos os campos"]);
    exit;
}

// Consulta
$stmt = $conn->prepare("SELECT user_senha, tipo FROM User WHERE user_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['user_senha'] === $senha) { // Senha em texto simples
        $_SESSION['logado'] = true;
        $_SESSION['email']  = $email;
        $_SESSION['tipo']   = $row['tipo'];

        echo json_encode(["status"=>"ok", "tipo"=>(int)$row['tipo']]);
    } else {
        echo json_encode(["status"=>"erro","mensagem"=>"Senha incorreta"]);
    }
} else {
    echo json_encode(["status"=>"erro","mensagem"=>"Usuário não encontrado"]);
}

$stmt->close();
$conn->close();
