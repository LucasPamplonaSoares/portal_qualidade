<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php'; 
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 2) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}
require_once ROOT_PATH . '/config/conexao.php';

$anexos = [];
$emp_id = $_SESSION['emp_id'];

// --- SQL SIMPLIFICADO ---
// (Removemos os JOINS de 'usuario' para evitar erros fatais)
$sql = "SELECT 
            a.anx_id, a.anx_nome, a.anx_status,
            a.anx_data_criacao, a.anx_data_atualizacao, t.tipo_descricao
        FROM anexo a
        LEFT JOIN tipo t ON a.tipo_id = t.tipo_id
        WHERE a.emp_id = ?
        ORDER BY a.anx_id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Erro fatal ao preparar SQL (home.php): " . $conn->error); }
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) { die("Erro fatal ao executar SQL (home.php): " . $stmt->error); }

while ($row = $result->fetch_assoc()) {
    $anexos[] = $row;
}
$stmt->close();

$status_map = [
    0 => 'Pendente', 1 => 'Aguardando Aprovação',
    2 => 'Aprovado', 3 => 'Recusado'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal de Terceiros - TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    
    <style> 
        /* ... (seus estilos de submenu, status, etc. ... ) ... */
        .status { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: #fff; text-align: center; }
        .status-pendente { background-color: #f0ad4e; }
        .status-aguardando { background-color: #0275d8; }
        .status-aprovado { background-color: #5cb85c; }
        .status-reprovado { background-color: #d9534f; }
        .btn-atualizar { display: inline-block; padding: 8px 10px; background-color: #5cb85c; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em; margin-right: 5px; vertical-align: middle; }
        .btn-atualizar:hover { background-color: #4a9c4a; }
        .action-link.download { padding: 8px 10px; vertical-align: middle; display: inline-block; }
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once ROOT_PATH . '/teksea/navbar.php'; ?>
    </nav>

    <main class="content">
        <header>
            <h1>Bem-vindo ao Portal TekSea, <?= htmlspecialchars($_SESSION['user_nome']); ?>!</h1>
        </header>
        
        <?php
        if (isset($_GET['sucesso'])) {
            echo "<p style='color:green; background-color:#e8f5e9; padding:10px; border-radius:5px; border:1px solid green; margin-bottom:20px;'>"
                 . "<b>SUCESSO:</b> " . htmlspecialchars($_GET['sucesso']) . "</p>";
        } elseif (isset($_GET['erro'])) {
             echo "<p style='color:red; background-color:#ffebee; padding:10px; border-radius:5px; border:1px solid red; margin-bottom:20px;'>"
                 . "<b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>";
        }
        ?>

        <section class="card table-section">
            <h3>Termos, Políticas e Formulários</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nome do Documento</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Data de Envio</th>
                        <th>Última Atualização</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                
                <tbody>
                    <?php if (!empty($anexos)): ?>
                        <?php foreach ($anexos as $anexo): ?>
                            <?php
                                // Lógica de Status (simplificada para segurança)
                                $status_id = $anexo['anx_status'] ?? -1;
                                $status_texto = $status_map[$status_id] ?? 'Desconhecido';
                                $status_class = 'status-default';
                                if ($status_id == 0) $status_class = 'status-pendente';
                                if ($status_id == 1) $status_class = 'status-aguardando';
                                if ($status_id == 2) $status_class = 'status-aprovado';
                                if ($status_id == 3) $status_class = 'status-reprovado';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($anexo['anx_nome']) ?></td>
                                <td><?= htmlspecialchars($anexo['tipo_descricao'] ?? 'N/A') ?></td>
                                <td><span class="status <?= $status_class ?>"><?= $status_texto ?></span></td>
                                <td><?= !empty($anexo['anx_data_criacao']) ? date('d/m/Y H:i', strtotime($anexo['anx_data_criacao'])) : 'N/A' ?></td>
                                <td><?= !empty($anexo['anx_data_atualizacao']) ? date('d/m/Y H:i', strtotime($anexo['anx_data_atualizacao'])) : 'N/A' ?></td>
                                
                                <td class="actions-cell">
                                    <a href="atualizar_anexo.php?id=<?= $anexo['anx_id'] ?>" 
                                       class="btn-atualizar" 
                                       title="Enviar nova versão">
                                        <i class="fa-solid fa-upload"></i> Atualizar
                                    </a>
                                    <a href="<?= APP_ROOT ?>teksea/cadastros/download_anexo.php?id=<?= $anexo['anx_id'] ?>" class="action-link download" title="Baixar">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr style="text-align: center;"><td colspan="6">Nenhum documento encontrado para sua empresa.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
<?php 
require_once ROOT_PATH . '/includes/scripts.php';
if (isset($conn)) { $conn->close(); }
?>
</body>
</html>