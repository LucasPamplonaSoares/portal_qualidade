<?php
session_start();
require __DIR__.'/../../config/conexao.php';

// Verificar conexão
if (!$conn || $conn->connect_error) {
    die("Erro na conexão com o banco: " . $conn->connect_error);
}

// Dados da empresa
$cnpj = $_POST['cnpj'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$nome_fantasia = $_POST['nome_fantasia'] ?? '';
$insc_social = $_POST['insc_social'] ?? '';
$tipo = $_POST['tipo'] ?? '';

// Dados do endereço
$cep = $_POST['cep'] ?? '';
$rua = $_POST['rua'] ?? '';
$numero = $_POST['nmr'] ?? '';
$bairro = $_POST['bairro'] ?? '';
$cidade = $_POST['cidade'] ?? '';
$estado = $_POST['uf'] ?? '';
$pais = $_POST['pais'] ?? '';

// Validação básica
if (empty($cnpj) || empty($razao_social)) {
    echo "CNPJ e Razão Social são obrigatórios.";
    header("Location: empresas.php?error=" . urlencode("CNPJ e Razão Social são obrigatórios."));
    exit;
}

// Verificar se o endereço já existe
$stmt_verifica_end = $conn->prepare("SELECT end_id FROM Endereco WHERE end_cep = ?");
if (!$stmt_verifica_end) {
    die("Erro na preparação da consulta Endereco: " . $conn->error);
}
$stmt_verifica_end->bind_param("s", $cep);
$stmt_verifica_end->execute();
$result_end = $stmt_verifica_end->get_result();

if ($result_end->num_rows > 0) {
    $row = $result_end->fetch_assoc();
    $end_id = $row['end_id'];
} else {
    // Inserir novo endereço
    $stmt_insert_end = $conn->prepare("INSERT INTO Endereco (end_cep, end_rua, end_nmr, end_bairro, end_cidade, end_uf, end_pais) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt_insert_end) {
        die("Erro na preparação do INSERT Endereco: " . $conn->error);
    }
    $stmt_insert_end->bind_param("ssissss", $cep, $rua, $numero, $bairro, $cidade, $estado, $pais);
    if ($stmt_insert_end->execute()) {
        $end_id = $stmt_insert_end->insert_id;
    } else {
        echo "Erro ao cadastrar endereço: " . $stmt_insert_end->error;
        header("Location: empresas.php?error=" . urlencode($stmt_insert_end->error));
        exit;
    }
}

// Verificar se a empresa já existe
$stmt_verifica_emp = $conn->prepare("SELECT emp_id FROM Empresa WHERE emp_cnpj = ?");
if (!$stmt_verifica_emp) {
    die("Erro na preparação da consulta Empresa: " . $conn->error);
}
$stmt_verifica_emp->bind_param("s", $cnpj);
$stmt_verifica_emp->execute();
$result_emp = $stmt_verifica_emp->get_result();

if ($result_emp->num_rows > 0) {
    echo "Empresa já cadastrada.";
    header("Location: empresas.php?error=" . urlencode("Empresa já cadastrada."));
    exit;
}

// Inserir empresa
$stmt_insert_emp = $conn->prepare("INSERT INTO Empresa (emp_cnpj, emp_razao_social, emp_nome_fantasia, emp_insc_social, emp_tipo, end_id) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt_insert_emp) {
    die("Erro na preparação do INSERT Empresa: " . $conn->error);
}
$stmt_insert_emp->bind_param("ssssii", $cnpj, $razao_social, $nome_fantasia, $insc_social, $tipo, $end_id);

if ($stmt_insert_emp->execute()) {
    echo "Empresa cadastrada com sucesso!";
    header("Location: empresas.php");
} else {
    echo "Erro ao cadastrar empresa: " . $stmt_insert_emp->error;
    header("Location: empresas.php?error=" . urlencode($stmt_insert_emp->error));
}

// Fechar conexões
$stmt_verifica_end->close();
$stmt_verifica_emp->close();
$stmt_insert_emp->close();
$conn->close();
?>
