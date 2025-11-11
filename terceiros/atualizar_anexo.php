<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/config/conexao.php';

// Habilitar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Diretórios
define('UPLOAD_DIR_SERVIDOR', ROOT_PATH . '/uploads/anexos/'); 
define('UPLOAD_DIR_BANCO', '/uploads/anexos/'); 

// Variáveis de controle
$mensagem_erro = null;
$anx_id = $_GET['id'] ?? $_POST['anx_id'] ?? null; // Pega o ID da URL (GET) ou do formulário (POST)

// --- 1. VERIFICAÇÃO DE SEGURANÇA BÁSICA ---
if (!isset($_SESSION['logado']) || $_SESSION['emp_tipo'] != 2 || !isset($_SESSION['emp_id']) || !isset($_SESSION['user_id'])) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}
$emp_id_session = $_SESSION['emp_id'];
$user_id_session = $_SESSION['user_id'];

// --- 2. LÓGICA DE PROCESSAMENTO (SE FOR UM POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Captura de Dados (POST e FILES)
        $anx_id_post = $_POST['anx_id'] ?? null;
        $caminho_arquivo_antigo_relativo = $_POST['caminho_arquivo_antigo'] ?? '';
        $novo_arquivo = $_FILES['novo_arquivo'] ?? null;

        // Validação
        if (empty($anx_id_post) || $novo_arquivo === null || $novo_arquivo['error'] != UPLOAD_ERR_OK) {
            throw new Exception("Nenhum arquivo enviado ou erro no upload.");
        }
        
        // Processamento do Arquivo
        if (!is_dir(UPLOAD_DIR_SERVIDOR)) {
            if (!mkdir(UPLOAD_DIR_SERVIDOR, 0775, true)) {
                 throw new Exception("Falha ao criar diretório de upload. Verifique permissões.");
            }
        }
        
        $nome_original = basename($novo_arquivo['name']);
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
        $nome_seguro = preg_replace("/[^a-zA-Z0-9_.-]/", "", pathinfo($nome_original, PATHINFO_FILENAME));
        $nome_final = "emp" . $emp_id_session . "_user" . $user_id_session . "_UPDATE_" . time() . "_" . $nome_seguro . "." . $extensao;
        $destino_servidor = UPLOAD_DIR_SERVIDOR . $nome_final;
        $destino_banco = UPLOAD_DIR_BANCO . $nome_final; 

        if (!move_uploaded_file($novo_arquivo['tmp_name'], $destino_servidor)) {
            throw new Exception("Falha ao mover o novo arquivo.");
        }

        // ATUALIZAÇÃO NO BANCO DE DADOS
        $novo_status = 1; // 1 = "Aguardando Aprovação"
        
        $sql_update = "UPDATE anexo SET 
                            anx_arquivo = ?,
                            anx_status = ?,
                            anx_data_atualizacao = NOW(),
                            user_id_atualizacao = ?
                       WHERE 
                            anx_id = ? AND emp_id = ?"; // Dupla checagem de segurança
                           
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) { throw new Exception("Erro de SQL: " . $conn->error); }
        $stmt_update->bind_param("siiii", $destino_banco, $novo_status, $user_id_session, $anx_id_post, $emp_id_session);

        if (!$stmt_update->execute()) {
            if (file_exists($destino_servidor)) { unlink($destino_servidor); }
            throw new Exception("Falha ao atualizar o banco de dados: " . $stmt_update->error);
        }
        $stmt_update->close();
        
        // Limpeza (Deletar o arquivo antigo)
        if (!empty($caminho_arquivo_antigo_relativo)) {
            $caminho_antigo_absoluto = ROOT_PATH . $caminho_arquivo_antigo_relativo;
            if (file_exists($caminho_antigo_absoluto)) {
                unlink($caminho_antigo_absoluto);
            }
        }

        // Sucesso! Redireciona para a home com a mensagem
        $conn->close();
        header("Location: home.php?sucesso=" . urlencode("Documento atualizado e enviado para aprovação!"));
        exit;

    } catch (Exception $e) {
        // --- O ERRO ACONTECEU ---
        // Em vez de redirecionar, apenas definimos a mensagem de erro
        $mensagem_erro = $e->getMessage();
        // O ID (anx_id) já foi definido no início do script (linha 16)
        // O script vai continuar a execução e mostrar o formulário HTML abaixo
    }
}

// --- 3. LÓGICA PARA MOSTRAR O FORMULÁRIO (SE FOR GET ou SE POST FALHOU) ---

if (empty($anx_id) || !is_numeric($anx_id)) {
    // Se não temos um ID válido, não podemos mostrar o formulário.
    header("Location: home.php?erro=" . urlencode("ID de anexo inválido."));
    exit;
}

// Buscar dados do anexo e VERIFICAR SE PERTENCE A ESTA EMPRESA
$sql_anexo = "SELECT anx_id, anx_nome, anx_arquivo FROM anexo WHERE anx_id = ? AND emp_id = ?";
$stmt = $conn->prepare($sql_anexo);
$stmt->bind_param("ii", $anx_id, $emp_id_session);
$stmt->execute();
$result_anexo = $stmt->get_result();

if ($result_anexo->num_rows === 0) {
    header("Location: home.php?erro=" . urlencode("Anexo não encontrado ou acesso negado."));
    exit;
}
$anexo = $result_anexo->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atualizar Documento - Portal TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style> .submenu{display:none;} .submenu.active{display:block;} .arrow{transition:transform .3s ease;margin-left:auto;} .arrow.active{transform:rotate(180deg);} </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once ROOT_PATH . '/teksea/navbar.php'; ?>
    </nav>

    <main class="content">
        <header>
            <a href="home.php" style="text-decoration:none; color: #555; font-size: 0.9em;">&larr; Voltar para Documentos</a>
            <h1>Atualizar Documento</h1>
        </header>

        <?php
        if (!empty($mensagem_erro)) {
            echo "<p style='color:red; background-color:#ffebee; padding:10px; border-radius:5px; border:1px solid red; margin-bottom:20px;'>"
                 . "<b>ERRO:</b> " . htmlspecialchars($mensagem_erro) . "</p>";
        }
        ?>

        <section class="form-card">
            <h2>Enviar Nova Versão</h2>
            <p>Você está atualizando o documento: <strong><?= htmlspecialchars($anexo['anx_nome']) ?></strong></p>
            <p>O arquivo atual (<a href="<?= APP_ROOT ?>teksea/cadastros/download_anexo.php?id=<?= $anexo['anx_id'] ?>">Baixar</a>) será substituído.</p>
            
            <form action="atualizar_anexo.php" method="POST" enctype="multipart/form-data">
                
                <input type="hidden" name="anx_id" value="<?= $anexo['anx_id'] ?>">
                <input type="hidden" name="caminho_arquivo_antigo" value="<?= htmlspecialchars($anexo['anx_arquivo']) ?>">
                
                <div class="input-group">
                    <div class="campo">
                        <label for="novo_arquivo">Selecione o novo arquivo</label>
                        <div class="upload-wrapper">
                             <input type="file" id="novo_arquivo" name="novo_arquivo" required hidden>
                             <label for="novo_arquivo" class="btn-upload"><i class="fas fa-upload"></i> Selecionar Arquivo</label>
                             <span id="nome-arquivo" class="file-name">Nenhum arquivo selecionado</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn">Enviar para Aprovação</button>
            </form>
        </section>
    </main>
</div>

<?php 
require_once ROOT_PATH . '/includes/scripts.php';
?>
<script>
// Script para mostrar o nome do arquivo
document.addEventListener('DOMContentLoaded', function() {
    const inputArquivo = document.getElementById('novo_arquivo');
    const nomeArquivoSpan = document.getElementById('nome-arquivo');
    if (inputArquivo) {
        inputArquivo.addEventListener('change', function () {
            nomeArquivoSpan.textContent = this.files.length > 0 ? this.files[0].name : 'Nenhum arquivo selecionado';
        });
    }
});
</script>
</body>
</html>