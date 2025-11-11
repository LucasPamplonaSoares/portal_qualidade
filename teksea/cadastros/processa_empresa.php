<?php
session_start();
require __DIR__ . '/../../config/conexao.php';

// --- Definições de Upload (Mantido) ---
define('UPLOAD_DIR_SERVIDOR', __DIR__ . '/../../uploads/'); 
define('UPLOAD_DIR_BANCO', '/uploads/'); 

error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1); 

// --- Gestor de Erros "À Prova de Falhas" (Mantido) ---
$arquivos_carregados_global = [];
function handle_fatal_error_and_redirect($conn_global, $e_message) {
    global $arquivos_carregados_global; 
    if ($conn_global && $conn_global->connect_errno === 0) {
        @$conn_global->rollback(); 
    }
    foreach ($arquivos_carregados_global as $arquivo) {
        if (file_exists($arquivo)) {
            @unlink($arquivo);
        }
    }
    if (!headers_sent()) {
         ob_clean();
        header("Location: nova_empresa.php?erro=" . urlencode("Erro Fatal: " . $e_message));
        exit;
    } else {
        echo "Erro fatal e não foi possível redirecionar. Mensagem: " . htmlspecialchars($e_message);
        exit;
    }
}
set_exception_handler(function($e) use (&$conn) {
    handle_fatal_error_and_redirect($conn, $e->getMessage());
});
register_shutdown_function(function() use (&$conn) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        handle_fatal_error_and_redirect($conn, $error['message']);
    }
});
// --- FIM DO GESTOR DE ERROS ---


// --- 
// FUNÇÃO AUXILIAR DE UPLOAD (ATUALIZADA)
// ---
function processarUpload($fileKey, $cnpjLimpo, $prefixo, $is_required) {
    global $arquivos_carregados_global; 
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] != UPLOAD_ERR_OK) {
        if ($is_required) {
            throw new Exception("O ficheiro obrigatório '" . htmlspecialchars($fileKey) . "' não foi enviado ou falhou.");
        }
        return null; 
    }
    $arquivo_tmp = $_FILES[$fileKey]['tmp_name'];
    $nome_original = basename($_FILES[$fileKey]['name']);
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    
    // Lista de extensões permitidas
    if (!in_array($extensao, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'rar'])) {
         throw new Exception("Tipo de ficheiro inválido para: " . htmlspecialchars($fileKey));
    }

    $nome_final = "CNPJ_" . $cnpjLimpo . "_" . $prefixo . "_" . time() . "." . $extensao;
    $caminho_destino_servidor = UPLOAD_DIR_SERVIDOR . $nome_final; 

    if (!move_uploaded_file($arquivo_tmp, $caminho_destino_servidor)) {
        throw new Exception("Falha ao salvar o ficheiro: " . htmlspecialchars($fileKey));
    }
    $arquivos_carregados_global[] = $caminho_destino_servidor; 
    return UPLOAD_DIR_BANCO . $nome_final;
}

// --- 
// FUNÇÃO 'salvarAnexo' (Corrigida)
// ---
function salvarAnexo($conn, $emp_id, $tipo_id, $nome_documento, $caminho_arquivo, $user_id) {
    if (empty($caminho_arquivo)) {
        // --- MUDANÇA: Se o ficheiro é opcional, CRIAMOS o registo como Pendente ---
        $status_inicial = 0; // 0 = Pendente
        $caminho_arquivo = null; // Garante que é NULL
    } else {
        $status_inicial = 0; // 0 = Pendente (mesmo que tenha enviado, o admin tem de aprovar)
    }
    
    $vitalicio = 0; // 0 = Não
    $data_vencimento = null; 

    $stmt = $conn->prepare("
        INSERT INTO anexo 
            (emp_id, tipo_id, anx_nome, anx_status, anx_data_vencimento, 
             anx_vitalicio, anx_arquivo, anx_criado_por_id, anx_data_criacao)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if ($stmt === false) {
         throw new Exception("Erro no prepare() do salvarAnexo: " . $conn->error);
    }
    
    // bind_param (iisisiis)
    $stmt->bind_param("iisisiis", 
        $emp_id, $tipo_id, $nome_documento, $status_inicial, 
        $data_vencimento, $vitalicio, $caminho_arquivo, $user_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao salvar anexo (Tipo ID: $tipo_id): " . $stmt->error);
    }
    $stmt->close();
}

// --- 
// FUNÇÃO 'salvarConfirmacao' (Checkboxes) (Mantida)
// ---
function salvarConfirmacao($conn, $emp_id, $tipo_id, $nome_documento, $user_id) {
    $status_aprovado = 2; $vitalicio = 1; $data_vencimento = null; $caminho_arquivo = null; 
    $stmt = $conn->prepare("
        INSERT INTO anexo 
            (emp_id, tipo_id, anx_nome, anx_status, anx_data_vencimento, 
             anx_vitalicio, anx_arquivo, anx_criado_por_id, anx_data_criacao)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if ($stmt === false) { throw new Exception("Erro no prepare() do salvarConfirmacao: " . $conn->error); }
    $stmt->bind_param("iisisiis", 
        $emp_id, $tipo_id, $nome_documento, $status_aprovado, 
        $data_vencimento, $vitalicio, $caminho_arquivo, $user_id
    );
    if (!$stmt->execute()) { throw new Exception("Erro ao salvar confirmação (Tipo ID: $tipo_id): " . $stmt->error); }
    $stmt->close();
}


// --- Captura de dados (Mantida) ---
$cnpj           = trim($_POST['emp_cnpj'] ?? '');
$razao_social   = trim($_POST['emp_razao_social'] ?? '');
$nome_fantasia  = trim($_POST['emp_nome_fantasia'] ?? '');
$insc_social    = trim($_POST['emp_insc_social'] ?? '');
$tipo           = trim($_POST['emp_tipo'] ?? '');
$seg_id         = trim($_POST['seg_id'] ?? ''); 
$possui_iso     = trim($_POST['emp_possui_iso'] ?? ''); 
$cep    = trim($_POST['cep'] ?? '');
$rua    = trim($_POST['rua'] ?? '');
$numero = trim($_POST['nmr'] ?? '');
$bairro = trim($_POST['bairro'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = trim($_POST['uf'] ?? '');
$pais   = trim($_POST['pais'] ?? '');

// Captura o ID do Admin
if (!isset($_SESSION['user_id'])) {
    $admin_user_id = 1; // Placeholder
} else {
    $admin_user_id = (int)$_SESSION['user_id'];
}

// --- Validação básica (Mantida) ---
if (empty($cnpj) || empty($razao_social) || $possui_iso === '' || empty($seg_id)) {
    header("Location: nova_empresa.php?erro=" . urlencode("CNPJ, Razão Social, 'Possui ISO?' e 'Segmento' são obrigatórios."));
    exit;
}

$cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);


// --- 'try'
try {
    // Começa a transação
    $conn->begin_transaction();

    // 1️⃣ Endereço (Mantido)
    $end_id = null;
    if (!empty($cep)) {
        // (Tua lógica de verificar/inserir endereço)
        $stmt_verifica_end = $conn->prepare("SELECT end_id FROM endereco WHERE end_cep = ?");
        $stmt_verifica_end->bind_param("s", $cep); $stmt_verifica_end->execute();
        $result_end = $stmt_verifica_end->get_result();
        if ($result_end->num_rows > 0) {
            $end_id = $result_end->fetch_assoc()['end_id'];
        } else {
            $stmt_insert_end = $conn->prepare("INSERT INTO endereco (end_cep, end_rua, end_nmr, end_bairro, end_cidade, end_uf, end_pais) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert_end->bind_param("sssssss", $cep, $rua, $numero, $bairro, $cidade, $estado, $pais);
            $stmt_insert_end->execute();
            $end_id = $stmt_insert_end->insert_id;
            $stmt_insert_end->close();
        }
        $stmt_verifica_end->close();
    }

    // 2️⃣ Verifica Empresa (Mantido)
    $stmt_verifica_emp = $conn->prepare("SELECT emp_id FROM empresa WHERE emp_cnpj = ?");
    $stmt_verifica_emp->bind_param("s", $cnpj);
    $stmt_verifica_emp->execute();
    if ($stmt_verifica_emp->get_result()->num_rows > 0) {
        throw new Exception("Empresa já cadastrada com este CNPJ.");
    }
    $stmt_verifica_emp->close();

    
    // 3️⃣ Processa os Uploads (ATUALIZADO para 'false')
    $path_iso = null; $path_doc_legal = null; $path_checklist = null;
    if ($possui_iso == 1) {
        $path_iso = processarUpload('certificado_iso', $cnpj_limpo, 'CERT_ISO', false);
    } elseif ($possui_iso == 0) {
        $path_doc_legal = processarUpload('documentos_legais', $cnpj_limpo, 'DOCLEGAL', false);
        $path_checklist = processarUpload('checklist_operacional', $cnpj_limpo, 'CHECKLIST', false);
    }
    
    $path_hom_seguro = null; $path_hom_ficha = null; $path_hom_fiscal = null;
    $path_hom_pop_cond = null; $path_hom_pop_emerg = null;
    
    $isTransporte = false;
    $stmt_seg = $conn->prepare("SELECT seg_nome FROM segmento WHERE seg_id = ?");
    $stmt_seg->bind_param("i", $seg_id); $stmt_seg->execute();
    $result_seg = $stmt_seg->get_result();
    if ($result_seg->num_rows > 0) {
        if (stripos($result_seg->fetch_assoc()['seg_nome'], 'Transporte') !== false) {
            $isTransporte = true;
        }
    }
    $stmt_seg->close();
    
    if ($isTransporte) {
        $path_hom_seguro = processarUpload('homolog_seguro', $cnpj_limpo, 'HOM_SEGURO', false);
        $path_hom_ficha = processarUpload('homolog_ficha_emergencia', $cnpj_limpo, 'HOM_FICHA', false);
        $path_hom_fiscal = processarUpload('homolog_doc_fiscal', $cnpj_limpo, 'HOM_FISCAL', false);
        $path_hom_pop_cond = processarUpload('homolog_pop_condutor', $cnpj_limpo, 'HOM_POP_COND', false);
        $path_hom_pop_emerg = processarUpload('homolog_pop_emergencia', $cnpj_limpo, 'HOM_POP_EMERG', false);
    }

    // 4️⃣ INSERT NA EMPRESA (Mantido)
    $stmt_insert_emp = $conn->prepare("
        INSERT INTO empresa (
            emp_cnpj, emp_razao_social, emp_nome_fantasia, emp_insc_social, emp_tipo, 
            seg_id, end_id, emp_possui_iso
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt_insert_emp->bind_param("ssssiiii", 
        $cnpj, $razao_social, $nome_fantasia, $insc_social, 
        $tipo, $seg_id, $end_id, $possui_iso
    );
    
    $stmt_insert_emp->execute();
    $emp_id_novo = $stmt_insert_emp->insert_id; 
    $stmt_insert_emp->close();

    // 5️⃣ INSERT DOS DOCUMENTOS NA TABELA 'anexo' (ATUALIZADO)
    
    if ($possui_iso == 1) {
        salvarAnexo($conn, $emp_id_novo, 31, 'Certificado ISO', $path_iso, $admin_user_id); 
    } else {
        salvarAnexo($conn, $emp_id_novo, 32, 'Documentos Legais (Sem ISO)', $path_doc_legal, $admin_user_id);
        salvarAnexo($conn, $emp_id_novo, 33, 'Checklist Operacional (Sem ISO)', $path_checklist, $admin_user_id);
    }
    
    if ($isTransporte) {
        salvarAnexo($conn, $emp_id_novo, 34, 'Seguro de Transporte', $path_hom_seguro, $admin_user_id);
        salvarAnexo($conn, $emp_id_novo, 35, 'Ficha de Emergência', $path_hom_ficha, $admin_user_id);
        salvarAnexo($conn, $emp_id_novo, 36, 'Documento Fiscal de Produto', $path_hom_fiscal, $admin_user_id);
        salvarAnexo($conn, $emp_id_novo, 37, 'POP - Proibição Condutor', $path_hom_pop_cond, $admin_user_id);
        salvarAnexo($conn, $emp_id_novo, 38, 'POP - Equipamentos Emergência', $path_hom_pop_emerg, $admin_user_id);

        if (isset($_POST['check_rotulos'])) {
            salvarConfirmacao($conn, $emp_id_novo, 39, 'Rótulos de Risco', $admin_user_id);
        } else {
            salvarAnexo($conn, $emp_id_novo, 39, 'Rótulos de Risco', null, $admin_user_id);
        }
        
        if (isset($_POST['check_inspecao'])) {
            salvarConfirmacao($conn, $emp_id_novo, 40, 'Inspeção do Veículo', $admin_user_id);
        } else {
            salvarAnexo($conn, $emp_id_novo, 40, 'Inspeção do Veículo', null, $admin_user_id);
        }

        if (isset($_POST['check_equipamentos'])) {
            salvarConfirmacao($conn, $emp_id_novo, 41, 'Equipamentos de Emergência', $admin_user_id);
        } else {
            salvarAnexo($conn, $emp_id_novo, 41, 'Equipamentos de Emergência', null, $admin_user_id);
        }
        
        if (isset($_POST['check_incompatibilidade'])) {
            salvarConfirmacao($conn, $emp_id_novo, 42, 'Verificação de Incompatibilidade', $admin_user_id);
        } else {
            salvarAnexo($conn, $emp_id_novo, 42, 'Verificação de Incompatibilidade', null, $admin_user_id);
        }
        
        if (isset($_POST['check_manutencao'])) {
            salvarConfirmacao($conn, $emp_id_novo, 43, 'Manutenção e Vistoria', $admin_user_id);
        } else {
            salvarAnexo($conn, $emp_id_novo, 43, 'Manutenção e Vistoria', null, $admin_user_id);
        }
        
        if (isset($_POST['check_roteirizacao'])) {
            salvarConfirmacao($conn, $emp_id_novo, 44, 'Roteirização', $admin_user_id);
        } else {
            salvarAnexo($conn, $emp_id_novo, 44, 'Roteirização', null, $admin_user_id);
        }
    }
    
    // 6️⃣ ============ MUDANÇA: Notificação para o Fornecedor (DESATIVADO TEMPORARIAMENTE) ============
    //
    // $user_id_do_fornecedor = null;
    // $stmt_user = $conn->prepare("SELECT user_id FROM usuario WHERE emp_id = ? AND ??? = 1 LIMIT 1"); // <-- PRECISAMOS DO NOME DA COLUNA
    // if ($stmt_user) {
    //     $stmt_user->bind_param("i", $emp_id_novo);
    //     $stmt_user->execute();
    //     $result_user = $stmt_user->get_result();
    //     if ($result_user->num_rows > 0) {
    //         $user_id_do_fornecedor = $result_user->fetch_assoc()['user_id'];
    //     }
    //     $stmt_user->close();
    // }
    
    // if ($user_id_do_fornecedor) {
    //     $mensagem = "Bem-vindo! A sua conta foi criada e os seus documentos iniciais estão em análise.";
    //     $link = "fornecedores/home.php"; 
        
    //     $sql_not = "INSERT INTO notificacoes (user_id_destino, mensagem, link, lida, data_criacao) VALUES (?, ?, ?, 0, NOW())";
    //     $stmt_not = $conn->prepare($sql_not);
    //     $stmt_not->bind_param("iss", $user_id_do_fornecedor, $mensagem, $link);
    //     $stmt_not->execute();
    //     $stmt_not->close();
    // }
    // --- FIM DA MUDANÇA ---


    // ✅ Tudo certo, comete a transação
    $conn->commit();
    header("Location: empresas.php?sucesso=" . urlencode("Empresa cadastrada com sucesso!"));
    exit;

} catch (Throwable $e) { 
    handle_fatal_error_and_redirect($conn, $e->getMessage());
}
?>