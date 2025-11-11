<?php
// PARTE 1: LÓGICA, SEGURANÇA E BUSCA DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Segurança: Garante que apenas administradores possam ver esta página.
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: /login/");
    exit;
}

// 2. Conexão com o banco
require_once __DIR__ . '/../../config/conexao.php';

// 3. Validar e buscar o usuário a ser editado
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    header("Location: usuarios.php?erro=" . urlencode("ID de usuário inválido."));
    exit;
}
$user_id_para_editar = (int)$_GET['user_id'];

// Prepara a consulta para buscar o usuário
$sql_usuario = "SELECT user_nome, user_sobrenome, user_email, emp_id FROM usuario WHERE user_id = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("i", $user_id_para_editar);
$stmt->execute();
$result_usuario = $stmt->get_result();

if ($result_usuario->num_rows === 0) {
    header("Location: usuarios.php?erro=" . urlencode("Usuário não encontrado."));
    exit;
}
// Guarda os dados do usuário em um array
$usuario = $result_usuario->fetch_assoc();
$stmt->close();


// 4. Busca das empresas para popular o dropdown
$empresas = []; 
$sql_empresas = "SELECT emp_id, emp_razao_social FROM empresa ORDER BY emp_razao_social ASC";
$result_empresas = $conn->query($sql_empresas);
if ($result_empresas && $result_empresas->num_rows > 0) {
    $empresas = $result_empresas->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Usuário - Portal TekSea</title>
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
                <a href="usuarios.php" style="text-decoration:none; color: #555; font-size: 0.9em;">&larr; Voltar para Gestão de Usuários</a>
                <h1>Editar Usuário: <?= htmlspecialchars($usuario['user_nome']) ?></h1>
            </header>

            <section class="form-card">
                <h2>Atualizar Dados do Usuário</h2>
                
                <form action="atualiza_usuario.php" method="POST">
                    
                    <input type="hidden" name="user_id" value="<?= $user_id_para_editar ?>">
                    
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="nome">Nome</label>
                            <input type="text" id="nome" name="nome" required 
                                   value="<?= htmlspecialchars($usuario['user_nome']) ?>">
                        </div>
                        <div class="campo">
                            <label for="sobrenome">Sobrenome</label>
                            <input type="text" id="sobrenome" name="sobrenome" required
                                   value="<?= htmlspecialchars($usuario['user_sobrenome']) ?>">
                        </div>
                    </div>

                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="emp_id">Empresa</label>
                            <select id="emp_id" name="emp_id" required>
                                <option value="" disabled>Selecione uma empresa</option>
                                <?php
                                if (!empty($empresas)):
                                    foreach ($empresas as $empresa):
                                        // Lógica para marcar a empresa atual do usuário como 'selected'
                                        $selecionado = ($empresa['emp_id'] == $usuario['emp_id']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($empresa['emp_id']) . "' $selecionado>" 
                                             . htmlspecialchars($empresa['emp_razao_social']) 
                                             . "</option>";
                                    endforeach;
                                else:
                                    echo "<option value='' disabled>Nenhuma empresa cadastrada</option>";
                                endif;
                                ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($usuario['user_email']) ?>">
                        </div>
                    </div>
                    
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="senha">Nova Senha</label>
                            <input type="password" id="senha" name="senha" 
                                   placeholder="Deixe em branco para não alterar">
                        </div>
                        <div class="campo">
                            <label for="confirmar-senha">Confirmar Nova Senha</label>
                            <input type="password" id="confirmar-senha" name="confirmar-senha"
                                   placeholder="Deixe em branco para não alterar">
                        </div>
                    </div>

                    <button type="submit" class="btn">Salvar Alterações</button>
                </form>
            </section>
        </main>
    </div>

    <?php
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>