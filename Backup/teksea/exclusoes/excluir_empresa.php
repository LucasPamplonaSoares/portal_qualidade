<?php
include __DIR__.'/../../config/conexao.php';

if (isset($_GET['cnpj'])) {
    $cnpj = $_GET['cnpj'];

    $stmt = $conn->prepare("DELETE FROM Empresa WHERE emp_cnpj = ?");
    if (!$stmt) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro na preparação da consulta: ' . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("s", $cnpj);

    if ($stmt->execute()) {
        header("Location: ../user.php?sucesso=1");
        exit;
    } else {
        header("Location: ../user.php?erro=1");

        exit;
    }
}
?>
