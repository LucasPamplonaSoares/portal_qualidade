<?php
include __DIR__.'/../../config/conexao.php';

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM Usuario WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        header("Location: ../user.php?sucesso=2");
        exit;
    } else {
        header("Location: ../user.php?erro=2");

        exit;
    }
}
?>
