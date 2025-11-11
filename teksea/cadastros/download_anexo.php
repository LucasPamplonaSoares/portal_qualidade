<?php
session_start();

// --- MODIFICADO: Incluindo o config.php ---
// Precisamos disto para ter acesso à constante ROOT_PATH
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/conexao.php';

// 1. Segurança: Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    die("Acesso negado. Você precisa estar logado para baixar arquivos.");
}

// 2. Verifica se um ID de anexo foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de anexo inválido.");
}

$anx_id = intval($_GET['id']);

// 3. Busca as informações do anexo no banco de dados
$stmt = $conn->prepare("SELECT anx_nome, anx_arquivo FROM anexo WHERE anx_id = ?");
if (!$stmt) {
    die("Erro ao preparar a consulta: " . $conn->error);
}

$stmt->bind_param("i", $anx_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Anexo não encontrado ou você não tem permissão para acessá-lo.");
}

$anexo = $result->fetch_assoc();
$nome_display = $anexo['anx_nome']; 

// Este é o CAMINHO RELATIVO salvo no banco 
// (ex: '/uploads/anexos/arquivo.pdf' ou '/uploads/arquivo_iso.pdf')
$caminho_relativo_banco = $anexo['anx_arquivo'];


// --- Lógica para montar o nome final (O seu código já estava correto) ---
$extensao = pathinfo($caminho_relativo_banco, PATHINFO_EXTENSION);
// Se o nome salvo (ex: "Contrato Social") não tiver a extensão, nós a adicionamos
if (!empty($extensao) && !preg_match('/\.' . preg_quote($extensao, '/') . '$/i', $nome_display)) {
    $nome_final_download = $nome_display . "." . $extensao;
} else {
    $nome_final_download = $nome_display; // O nome já contém a extensão
}
// --- FIM DA LÓGICA DO NOME ---


// 4. Monta o caminho completo para o arquivo no servidor

// --- MODIFICADO: Esta é a correção principal ---
// Usamos a constante ROOT_PATH (do config.php) e concatenamos com o
// caminho relativo que está guardado no banco de dados.
$caminho_arquivo = ROOT_PATH . $caminho_relativo_banco;

// (O seu código antigo era: $caminho_arquivo = realpath(__DIR__ . '/../uploads/') . '/' . $nome_no_servidor;)
// --- FIM DA MODIFICAÇÃO ---


// 5. Verifica se o arquivo físico existe
if (!file_exists($caminho_arquivo)) {
    // A sua mensagem de erro original
    die("O arquivo físico não foi encontrado no servidor.");
    
    // (Para depuração, caso ainda falhe, use a linha abaixo no lugar da de cima)
    // die("O arquivo físico não foi encontrado no servidor. Caminho verificado: " . htmlspecialchars($caminho_arquivo));
}

// 6. Prepara os cabeçalhos para forçar o download (usando o novo nome final)
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($nome_final_download) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($caminho_arquivo));

// Limpa o buffer de saída para evitar corrompimento do arquivo
flush(); 

// 7. Lê o arquivo e o envia para o navegador
readfile($caminho_arquivo);

$stmt->close();
$conn->close();
exit;
?>