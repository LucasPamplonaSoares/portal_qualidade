<?php
include __DIR__.'/../../config/conexao.php';

if (isset($_GET['id'])) {
    $anx_id = $_GET['id'];

    // Primeiro, buscar o nome do arquivo no banco de dados
    $stmt = $conn->prepare("SELECT anx_arquivo FROM Anexo WHERE anx_id = ?");
    $stmt->bind_param("i", $anx_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nome_arquivo = $row['anx_arquivo'];
        $caminho_arquivo = __DIR__ . "/../uploads/" . $nome_arquivo;

        // Tentar deletar o arquivo físico
        
        if (file_exists($caminho_arquivo)) {
            if (unlink($caminho_arquivo)) {
                echo "Arquivo excluído com sucesso.";
            } else {
                echo "Erro ao excluir o arquivo.";
            }
        } else {
            echo "Arquivo não encontrado: " . $caminho_arquivo;
        }


        // Agora deletar o registro no banco de dados
        $stmt = $conn->prepare("DELETE FROM Anexo WHERE anx_id = ?");
        $stmt->bind_param("i", $anx_id);

        if ($stmt->execute()) {
            header("Location: ../user.php?sucesso=3");
            exit;
        } else {
            header("Location: ../user.php?erro=3");
            exit;
        }
    } else {
        header("Location: ../user.php?erro=3");
        exit;
    }
}
?>
