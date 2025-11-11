<?php
// Conexão com o banco

$host = "br606.hostgator.com.br";
$usuario = "teksea22_lucas";
$senha = "&6IdI,A#BZ,6";
$banco = "teksea22_portal_fornecedores";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
