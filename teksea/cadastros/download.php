<?php
$arquivo = $_GET['arquivo'] ?? '';

$diretorio = realpath(__DIR__ . '/../../uploads/');
$caminho = realpath($diretorio . '/' . $arquivo);

// Proteção contra acesso indevido
if ($arquivo && $caminho && str_starts_with($caminho, $diretorio) && file_exists($caminho)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($arquivo) . '"');
    header('Content-Length: ' . filesize($caminho));
    readfile($caminho);
    exit;
} else {
    echo "❌ Arquivo não encontrado ou acesso inválido.";
}
?>
