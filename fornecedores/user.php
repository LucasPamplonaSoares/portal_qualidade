<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();

// Inclui o arquivo de configuração para ter acesso às constantes globais
require_once __DIR__ . '/../config/config.php';

// 1. Segurança: Verifica se o usuário está logado e se é do tipo 'Fornecedor'
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 1) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

// 2. Conexão com o banco de dados
require_once ROOT_PATH . '/config/conexao.php';

// 3. Lógica para ATUALIZAR o perfil do usuário, se o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_perfil'])) {
    $nome = $_POST['nome'] ?? '';
    $sobrenome = $_POST['sobrenome'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar-senha'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Verifica se as senhas coincidem antes de fazer qualquer coisa
    if (!empty($senha) && $senha !== $confirmar_senha) {
        header("Location: " . APP_ROOT . "fornecedores/user.php?erro=" . urlencode("As novas senhas não coincidem."));
        exit;
    }

    // Atualiza nome e sobrenome
    $stmt_user = $conn->prepare("UPDATE usuario SET user_nome = ?, user_sobrenome = ? WHERE user_id = ?");
    $stmt_user->bind_param("ssi", $nome, $sobrenome, $user_id);
    $stmt_user->execute();
    $stmt_user->close();

    // Atualiza senha se uma nova foi fornecida
    if (!empty($senha)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt_pass = $conn->prepare("UPDATE usuario SET user_senha = ? WHERE user_id = ?");
        $stmt_pass->bind_param("si", $senha_hash, $user_id);
        $stmt_pass->execute();
        $stmt_pass->close();
    }

    // Atualiza os dados na sessão para refletir a mudança imediatamente
    $_SESSION['user_nome'] = $nome;
    $_SESSION['user_sobrenome'] = $sobrenome;
    
    header("Location: " . APP_ROOT . "fornecedores/user.php?sucesso=" . urlencode("Perfil atualizado com sucesso!"));
    exit;
}

// 4. Busca os dados ATUAIS do usuário e da empresa para exibir nos campos
$user_data = [];
$stmt_data = $conn->prepare("SELECT u.user_nome, u.user_sobrenome, u.user_email, e.emp_razao_social 
                             FROM usuario u 
                             LEFT JOIN empresa e ON u.emp_id = e.emp_id 
                             WHERE u.user_id = ?");
$stmt_data->bind_param("i", $_SESSION['user_id']);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
if ($result_data->num_rows > 0) {
    $user_data = $result_data->fetch_assoc();
}
$stmt_data->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu Perfil - Portal TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php 
        // Inclui o navbar LOCAL da pasta /fornecedores/
        include 'navbar.php'; 
        ?>
    </nav>
    <main class="content">
        <?php
        // Bloco de mensagens de sucesso/erro
        if (isset($_GET['erro'])) {
            echo "<p class='mensagem mensagem-erro'><b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>";
        } elseif (isset($_GET['sucesso'])) {
            echo "<p class='mensagem mensagem-sucesso'><b>SUCESSO:</b> " . htmlspecialchars($_GET['sucesso']) . "</p>";
        }
        ?>
        <header><h1>Meu Perfil</h1></header>

        <section class="form-card">
            <form method="POST" action="user.php">
                <div class="input-group two-label-input">
                    <div class="campo">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($user_data['user_nome'] ?? '') ?>" required>
                    </div>
                    <div class="campo">
                        <label for="sobrenome">Sobrenome</label>
                        <input type="text" id="sobrenome" name="sobrenome" value="<?= htmlspecialchars($user_data['user_sobrenome'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="input-group two-label-input">
                     <div class="campo">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($user_data['user_email'] ?? '') ?>" readonly disabled>
                    </div>
                    <div class="campo">
                        <label>Empresa</label>
                        <input type="text" value="<?= htmlspecialchars($user_data['emp_razao_social'] ?? 'N/A') ?>" readonly disabled>
                    </div>
                </div>
                <div class="input-group two-label-input">
                    <div class="campo">
                        <label for="senha">Nova Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="Deixe em branco para não alterar">
                    </div>
                    <div class="campo">
                        <label for="confirmar-senha">Confirmar Nova Senha</label>
                        <input type="password" id="confirmar-senha" name="confirmar-senha" placeholder="Repita a nova senha">
                    </div>
                </div>
                <button class="btn" type="submit" name="salvar_perfil">Salvar Alterações</button>
            </form>
        </section>        
    </main>
</div>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>