<?php
include __DIR__.'/../../config/conexao.php';

if (isset($_GET['id'])) {
    $tipo_id = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM Tipo WHERE tipo_id = ?");
    if (!$stmt) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Erro na preparação da consulta: ' . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("i", $tipo_id);

    if ($stmt->execute()) {
        header("Location: ../user.php?sucesso=6");
        exit;
    } else {
        header("Location: ../user.php?erro=6");

        exit;
    }
}
?>