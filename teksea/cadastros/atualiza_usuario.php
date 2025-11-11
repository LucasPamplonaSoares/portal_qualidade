<?php
session_start();
require_once __DIR__.'/../../config/conexao.php';

// 1. Segurança: Garante que apenas administradores possam fazer atualizações.
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    die('Acesso negado.');
}

// 2. Captura dos dados do formulário
$user_id   = $_POST['user_id'] ?? null;
$nome      = trim($_POST['nome'] ?? '');
$sobrenome = trim($_POST['sobrenome'] ?? '');
$email     = trim($_POST['email'] ?? '');
$emp_id    = $_POST['emp_id'] ?? null;

// Captura dos campos de senha (podem estar vazios)
$senha           = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar-senha'] ?? '';


// 3. Validação dos dados
if (empty($user_id) || empty($nome) || empty($sobrenome) || empty($email) || empty($emp_id)) {
    // Redireciona DE VOLTA para a página de edição com o erro
    header("Location: editar_usuario.php?user_id=" . $user_id . "&erro=" . urlencode("Todos os campos (exceto senha) são obrigatórios."));
    exit;
}

// 4. Lógica da Senha
$senha_hash = null;
if (!empty($senha)) {
    // Se o campo 'senha' foi preenchido, nós DEVEMOS validar a confirmação
    if ($senha !== $confirmar_senha) {
        header("Location: editar_usuario.php?user_id=" . $user_id . "&erro=" . urlencode("As novas senhas não coincidem."));
        exit;
    }
    // Se coincidem, geramos o novo hash
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
}


// 5. Preparação da Consulta SQL (Dinâmica)

// Vamos guardar os parâmetros e os tipos dinamicamente
$params = [];
$tipos  = "";

// Começamos com a parte fixa do SQL
$sql = "UPDATE usuario SET 
            user_nome = ?, 
            user_sobrenome = ?, 
            user_email = ?, 
            emp_id = ? ";

// Adicionamos os parâmetros fixos
array_push($params, $nome, $sobrenome, $email, $emp_id);
$tipos .= "sssi"; // s = string, s, s, i = integer

// --- Parte Dinâmica da Senha ---
// Só adicionamos a atualização da senha se um novo hash foi gerado
if ($senha_hash !== null) {
    $sql .= ", user_senha = ? ";
    array_push($params, $senha_hash);
    $tipos .= "s"; // Adiciona o 's' de string para a senha
}

// --- Parte Final do SQL ---
$sql .= " WHERE user_id = ?";
array_push($params, $user_id);
$tipos .= "i"; // Adiciona o 'i' de integer para o user_id

// 6. Execução da Consulta
$stmt = $conn->prepare($sql);

if (!$stmt) {
    header("Location: editar_usuario.php?user_id=" . $user_id . "&erro=" . urlencode("Erro na preparação do SQL: " . $conn->error));
    exit;
}

// Usamos o operador '...' (splat) para "desempacotar" o array de parâmetros
$stmt->bind_param($tipos, ...$params);

if ($stmt->execute()) {
    // Sucesso! Redireciona para a lista de usuários
    header("Location: usuarios.php?sucesso=" . urlencode("Usuário atualizado com sucesso!"));
} else {
    // Erro (ex: e-mail duplicado)
    header("Location: editar_usuario.php?user_id=" . $user_id . "&erro=" . urlencode("Erro ao atualizar: " . $stmt->error));
}

$stmt->close();
$conn->close();
exit;

?>