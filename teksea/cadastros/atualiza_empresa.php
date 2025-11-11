<?php
session_start();
require __DIR__ . '/../../config/conexao.php';

// --- Verifica se o usuário está logado ---
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    die('Acesso negado.');
}

// --- Definições de Upload ---
define('UPLOAD_DIR_SERVIDOR', __DIR__ . '/../../uploads/'); 
define('UPLOAD_DIR_BANCO', '/uploads/'); 

// --- Captura e sanitiza os dados da empresa ---
$emp_id        = trim($_POST['emp_id'] ?? ''); 
$end_id        = trim($_POST['end_id'] ?? ''); 
$cnpj          = trim($_POST['emp_cnpj'] ?? '');
$razao_social  = trim($_POST['emp_razao_social'] ?? '');
$nome_fantasia = trim($_POST['emp_nome_fantasia'] ?? '');
$insc_social   = trim($_POST['emp_insc_social'] ?? '');
$tipo          = trim($_POST['emp_tipo'] ?? '');
$seg_id        = trim($_POST['seg_id'] ?? ''); // Campo Segmento
$possui_iso    = trim($_POST['emp_possui_iso'] ?? '');
// ... (restante da captura de dados de endereço e paths) ...
$cep     = trim($_POST['cep'] ?? '');
$rua     = trim($_POST['rua'] ?? '');
$numero  = trim($_POST['nmr'] ?? '');
$bairro  = trim($_POST['bairro'] ?? '');
$cidade  = trim($_POST['cidade'] ?? '');
$estado  = trim($_POST['uf'] ?? '');
$pais    = trim($_POST['pais'] ?? '');
$doc_legal_atual = trim($_POST['doc_legal_atual'] ?? '');
$checklist_atual = trim($_POST['checklist_atual'] ?? '');


// --- Validação básica ---
if (empty($emp_id) || empty($cnpj) || empty($razao_social) || $possui_iso === '' || empty($seg_id)) {
    header("Location: editar_empresa.php?emp_id=" . $emp_id . "&erro=" . urlencode("Dados obrigatórios ausentes (ex: CNPJ, Razão Social, ISO ou Segmento)."));
    exit;
}
$cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);


// --- Inicia a transação ---
$conn->begin_transaction();

try {
    // 1️⃣ Atualiza o Endereço
    if (!empty($end_id)) {
        $stmt_update_end = $conn->prepare("
            UPDATE endereco SET 
                end_cep = ?, end_rua = ?, end_nmr = ?, end_bairro = ?, 
                end_cidade = ?, end_uf = ?, end_pais = ?
            WHERE end_id = ?
        ");
        $stmt_update_end->bind_param("sssssssi", $cep, $rua, $numero, $bairro, $cidade, $estado, $pais, $end_id);
        $stmt_update_end->execute();
        $stmt_update_end->close();
    } 

    
    // --- Lógica de Upload (Substituição) ---
    $doc_legal_path = $doc_legal_atual; 
    $checklist_path = $checklist_atual; 

    function deletarArquivoAntigo($caminhoRelativo) {
        if (!empty($caminhoRelativo)) {
            $caminhoLimpo = str_replace(UPLOAD_DIR_BANCO, '', $caminhoRelativo);
            $caminhoAbsoluto = UPLOAD_DIR_SERVIDOR . basename($caminhoLimpo); 
            if (file_exists($caminhoAbsoluto)) {
                unlink($caminhoAbsoluto);
            }
        }
    }

    // (Toda a sua lógica de IF $possui_iso == 0 ou == 1 continua aqui...)
    if ($possui_iso == 0) {
        if (isset($_FILES['documentos_legais']) && $_FILES['documentos_legais']['error'] == UPLOAD_ERR_OK) {
            deletarArquivoAntigo($doc_legal_atual); 
            $arquivo_tmp = $_FILES['documentos_legais']['tmp_name'];
            $extensao = strtolower(pathinfo($_FILES['documentos_legais']['name'], PATHINFO_EXTENSION));
            $nome_final_doc = "CNPJ_" . $cnpj_limpo . "_DOCLEGAL_" . time() . "." . $extensao;
            if (!move_uploaded_file($arquivo_tmp, UPLOAD_DIR_SERVIDOR . $nome_final_doc)) {
                throw new Exception("Falha ao salvar os novos Documentos Legais.");
            }
            $doc_legal_path = UPLOAD_DIR_BANCO . $nome_final_doc; 
        }
        if (isset($_FILES['checklist_operacional']) && $_FILES['checklist_operacional']['error'] == UPLOAD_ERR_OK) {
            deletarArquivoAntigo($checklist_atual); 
            $arquivo_tmp = $_FILES['checklist_operacional']['tmp_name'];
            $extensao = strtolower(pathinfo($_FILES['checklist_operacional']['name'], PATHINFO_EXTENSION));
            $nome_final_check = "CNPJ_" . $cnpj_limpo . "_CHECKLIST_" . time() . "." . $extensao;
            if (!move_uploaded_file($arquivo_tmp, UPLOAD_DIR_SERVIDOR . $nome_final_check)) {
                throw new Exception("Falha ao salvar o novo Checklist Operacional.");
            }
            $checklist_path = UPLOAD_DIR_BANCO . $nome_final_check; 
        }
    } else if ($possui_iso == 1) {
        deletarArquivoAntigo($doc_legal_atual);
        deletarArquivoAntigo($checklist_atual);
        $doc_legal_path = null;
        $checklist_path = null;
    }


    // 2️⃣ Atualiza a Empresa (com o seg_id)
    $stmt_update_emp = $conn->prepare("
        UPDATE empresa SET
            emp_cnpj = ?, 
            emp_razao_social = ?, 
            emp_nome_fantasia = ?, 
            emp_insc_social = ?, 
            emp_tipo = ?, 
            seg_id = ?,          -- (Campo segmento)
            emp_possui_iso = ?, 
            emp_doc_legal_path = ?, 
            emp_checklist_path = ?
        WHERE emp_id = ?
    ");
    
    $stmt_update_emp->bind_param("ssssiiissi", 
        $cnpj, 
        $razao_social, 
        $nome_fantasia, 
        $insc_social, 
        $tipo,
        $seg_id,          
        $possui_iso, 
        $doc_legal_path, 
        $checklist_path,
        $emp_id
    );
    $stmt_update_emp->execute();
    $stmt_update_emp->close();


    // ✅ Tudo certo
    $conn->commit();
    
    // --- NOVO: Redirecionamento de Sucesso ---
    // Alterado de volta para 'empresas.php' (a tela inicial / lista)
    header("Location: empresas.php?sucesso=" . urlencode("Empresa atualizada com sucesso!"));
    exit;

} catch (Exception $e) {
    // ❌ Algo deu errado
    $conn->rollback();
    // (Mantém o redirecionamento de ERRO para a página de edição)
    header("Location: editar_empresa.php?emp_id=" . $emp_id . "&erro=" . urlencode($e->getMessage()));
    exit;
}
?>