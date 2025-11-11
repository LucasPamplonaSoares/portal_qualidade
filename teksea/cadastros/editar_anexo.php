<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Segurança
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: /login/");
    exit;
}

// 2. Conexão
require_once __DIR__ . '/../../config/conexao.php';

// 3. Validar e buscar o ANEXO a ser editado
if (!isset($_GET['anx_id']) || !is_numeric($_GET['anx_id'])) {
    header("Location: upload_anexo.php?erro=" . urlencode("ID de anexo inválido."));
    exit;
}
$anx_id_para_editar = (int)$_GET['anx_id'];

// Prepara a consulta para buscar o anexo
$sql_anexo = "SELECT anx_id, anx_nome, anx_arquivo, tipo_id, emp_id, anx_status 
              FROM anexo 
              WHERE anx_id = ?";
$stmt = $conn->prepare($sql_anexo);
$stmt->bind_param("i", $anx_id_para_editar);
$stmt->execute();
$result_anexo = $stmt->get_result();

if ($result_anexo->num_rows === 0) {
    header("Location: upload_anexo.php?erro=" . urlencode("Anexo não encontrado."));
    exit;
}
$anexo = $result_anexo->fetch_assoc();
$stmt->close();


// 4. Busca das categorias para o dropdown
$tipos_documento = [];
$sql_tipos = "SELECT tipo_id, tipo_descricao FROM tipo ORDER BY tipo_descricao ASC";
$result_tipos = $conn->query($sql_tipos);
if ($result_tipos && $result_tipos->num_rows > 0) {
    $tipos_documento = $result_tipos->fetch_all(MYSQLI_ASSOC);
}

// 5. Busca das empresas para o dropdown
$empresas = [];
$sql_empresas = "SELECT emp_id, emp_razao_social FROM empresa ORDER BY emp_razao_social ASC";
$result_empresas = $conn->query($sql_empresas);
if ($result_empresas && $result_empresas->num_rows > 0) {
    $empresas = $result_empresas->fetch_all(MYSQLI_ASSOC);
}

// 6. Mapa de Status
$status_map = [
    0 => 'Pendente',
    1 => 'Aguardando Aprovação',
    2 => 'Aprovado',
    3 => 'Recusado'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Documento - Portal TekSea</title>
    <link rel="stylesheet" href="/css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container">
        <nav class="sidebar">
            <?php require_once __DIR__ . '/../navbar.php'; ?>
        </nav>

        <main class="content">
            <header>
                <a href="upload_anexo.php" style="text-decoration:none; color: #555; font-size: 0.9em;">&larr; Voltar para Documentos</a>
                <h1>Editar Documento</h1>
            </header>
            
            <?php
            if (isset($_GET['erro'])) {
                echo "<p style='color:red; background-color:#ffebee; padding:10px; border-radius:5px; border:1px solid red; margin-bottom:20px;'>"
                     . "<b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>";
            }
            ?>

            <section class="form-card">
                <h2>Revisar e Aprovar Documento</h2>
                
                <form action="atualiza_anexo.php" method="POST">
                    
                    <input type="hidden" name="anx_id" value="<?= $anexo['anx_id'] ?>">
                    
                    <div class="campo">
                        <label>Arquivo Atual</label>
                        <p style="padding: 10px; background-color: #f4f4f4; border-radius: 5px;">
                            <a href="/teksea/cadastros/download_anexo.php?id=<?= $anexo['anx_id'] ?>" title="Baixar">
                                <i class="fa-solid fa-download"></i>
                                <?= htmlspecialchars(basename($anexo['anx_arquivo'])) ?>
                            </a>
                        </p>
                    </div>

                    <div class="input-group">
                        <div class="campo">
                            <label for="anx_nome">Nome do Documento</label>
                            <input type="text" id="anx_nome" name="anx_nome" required 
                                   value="<?= htmlspecialchars($anexo['anx_nome']) ?>">
                        </div>
                    </div>
                    
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="tipo_id">Categoria</label>
                            <select id="tipo_id" name="tipo_id" required>
                                <?php foreach ($tipos_documento as $tipo): ?>
                                    <option value="<?= $tipo['tipo_id'] ?>" <?= ($tipo['tipo_id'] == $anexo['tipo_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['tipo_descricao']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="emp_id">Empresa</label>
                            <select id="emp_id" name="emp_id" required>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= $empresa['emp_id'] ?>" <?= ($empresa['emp_id'] == $anexo['emp_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($empresa['emp_razao_social']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <div class="campo">
                            <label for="anx_status">Status do Documento</label>
                            <select id="anx_status" name="anx_status" required style="border: 2px solid #0056b3;">
                                <?php foreach ($status_map as $id_status => $texto_status): ?>
                                    <option value="<?= $id_status ?>" <?= ($id_status == $anexo['anx_status']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($texto_status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn">Salvar Alterações</button>
                </form>
            </section>
        </main>
    </div>
    <?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>