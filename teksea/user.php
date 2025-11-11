<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();
require_once __DIR__ . '/../config/config.php';

// 1. Segurança: Apenas administradores
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

require_once ROOT_PATH . '/config/conexao.php';

// 2. Lógica de ATUALIZAÇÃO do perfil (se o formulário for enviado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_perfil'])) {
    $nome = $_POST['nome'] ?? '';
    $sobrenome = $_POST['sobrenome'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar-senha'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (!empty($senha) && $senha !== $confirmar_senha) {
        header("Location: " . APP_ROOT . "teksea/user.php?erro=" . urlencode("As novas senhas não coincidem."));
        exit;
    }

    $stmt_user = $conn->prepare("UPDATE usuario SET user_nome = ?, user_sobrenome = ? WHERE user_id = ?");
    $stmt_user->bind_param("ssi", $nome, $sobrenome, $user_id);
    $stmt_user->execute();
    $stmt_user->close();

    if (!empty($senha)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt_pass = $conn->prepare("UPDATE usuario SET user_senha = ? WHERE user_id = ?");
        $stmt_pass->bind_param("si", $senha_hash, $user_id);
        $stmt_pass->execute();
        $stmt_pass->close();
    }

    $_SESSION['user_nome'] = $nome;
    $_SESSION['user_sobrenome'] = $sobrenome;
    header("Location: " . APP_ROOT . "teksea/user.php?sucesso=" . urlencode("Perfil atualizado com sucesso!"));
    exit;
}

// 3. Busca os dados ATUAIS do usuário e da empresa para exibir nos campos
$user_data = [];
$stmt_data = $conn->prepare("SELECT u.user_nome, u.user_sobrenome, u.user_email, e.emp_razao_social FROM usuario u LEFT JOIN empresa e ON u.emp_id = e.emp_id WHERE u.user_id = ?");
$stmt_data->bind_param("i", $_SESSION['user_id']);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
if ($result_data->num_rows > 0) { $user_data = $result_data->fetch_assoc(); }
$stmt_data->close();

// 4. Busca as categorias de documentos existentes
$categorias = [];
$sql_cat = "SELECT tipo_id, tipo_descricao, tipo_acao FROM tipo ORDER BY tipo_descricao ASC";
if ($result_cat = $conn->query($sql_cat)) { while ($row = $result_cat->fetch_assoc()) { $categorias[] = $row; } }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurações - Portal TekSea</title>
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
        <?php
        if (isset($_GET['erro'])) { echo "<p class='mensagem mensagem-erro'><b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>"; }
        if (isset($_GET['sucesso'])) { echo "<p class='mensagem mensagem-sucesso'><b>SUCESSO:</b> " . htmlspecialchars($_GET['sucesso']) . "</p>"; }
        ?>
        <header><h1>Configurações</h1></header>

        <section class="form-card">
            <h2>Perfil do Usuário</h2>
            <form method="POST" action="user.php">
                <div class="input-group two-label-input">
                    <div class="campo"><label for="nome">Nome</label><input type="text" id="nome" name="nome" value="<?= htmlspecialchars($user_data['user_nome'] ?? '') ?>" required></div>
                    <div class="campo"><label for="sobrenome">Sobrenome</label><input type="text" id="sobrenome" name="sobrenome" value="<?= htmlspecialchars($user_data['user_sobrenome'] ?? '') ?>" required></div>
                </div>
                <div class="input-group two-label-input">
                     <div class="campo"><label>Email</label><input type="email" value="<?= htmlspecialchars($user_data['user_email'] ?? '') ?>" readonly disabled></div>
                    <div class="campo"><label>Empresa</label><input type="text" value="<?= htmlspecialchars($user_data['emp_razao_social'] ?? 'N/A') ?>" readonly disabled></div>
                </div>
                <div class="input-group two-label-input">
                    <div class="campo"><label for="senha">Nova Senha</label><input type="password" id="senha" name="senha" placeholder="Deixe em branco para não alterar"></div>
                    <div class="campo"><label for="confirmar-senha">Confirmar Nova Senha</label><input type="password" id="confirmar-senha" name="confirmar-senha" placeholder="Repita a nova senha"></div>
                </div>
                <button class="btn" type="submit" name="salvar_perfil">Salvar Alterações</button>
            </form>
        </section>
        
        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #ddd;">

        <section class="card table-section">
            <h3>Categorias de Documentos</h3>
            <form action="<?= APP_ROOT ?>teksea/cadastros/processa_tipo.php" method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Ação do Tipo</th>
                            <th class="actions-cell" style="width: 50px;">Ações</th>
                            <th style="width: 50px;">
                                <button type="button" id="addRow" class="action-link download" title="Adicionar Linha"><i class="fa-solid fa-plus"></i></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tipoBody">
                        <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['tipo_descricao']) ?></td>
                            <td>
                                <?php 
                                if ($cat["tipo_acao"] == 1) { echo "Download Obrigatório"; }
                                elseif ($cat["tipo_acao"] == 2) { echo "Aceite"; }
                                else { echo "Indefinido"; }
                                ?>
                            </td>
                            <td class="actions-cell">
                                <a href="<?= APP_ROOT ?>teksea/exclusoes/excluir_tipo.php?id=<?= $cat['tipo_id'] ?>" class="action-link delete" title="Excluir" onclick="return confirm('Tem certeza?')"><i class="fa-solid fa-trash"></i></a>
                            </td>
                            <td></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </section>
    </main>
</div>

<?php require_once ROOT_PATH . '/includes/scripts.php'; ?>
<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>