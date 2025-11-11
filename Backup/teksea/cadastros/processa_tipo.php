<?php
include __DIR__.'/../../config/conexao.php';

if (!empty($_POST['nova_descricao'])) {
    foreach ($_POST['nova_descricao'] as $descricao) {
        if (!empty(trim($descricao))) {
            foreach ($_POST['tipo_acao'] as $tipo_acao) {
                if (!empty(trim($tipo_acao))) {
                    $stmt = $conn->prepare("INSERT INTO Tipo (tipo_descricao, tipo_acao) VALUES (?, ?)");
                    $stmt->bind_param("si", $descricao, $tipo_acao);
                    $stmt->execute();
                }
            }
        }
    }
    header("Location: ../user.php?sucesso=5");
    exit;
}
?>