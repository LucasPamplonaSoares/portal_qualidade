<?php
session_start();

// --- MUDANÇA 1: Usando o teu método de carregamento (igual ao home.php) ---
require_once __DIR__ . '/../config/config.php'; // Inclui o config.php primeiro
require_once ROOT_PATH . '/config/conexao.php'; // Agora usa a constante ROOT_PATH
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. VERIFICA O LOGIN DO FORNECEDOR ---
// (Assumindo que o teu home.php usa 'emp_tipo' == 1, vamos usar isso)
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 1) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}
$emp_id_fornecedor = (int)$_SESSION['emp_id'];

// --- 2. BUSCAR DADOS (CONSULTA COMPLETA) ---
// (Buscamos tudo: empresa, endereço, segmento e homologação)
$sql = "SELECT e.*, en.*, seg.seg_nome, hom.*
        FROM empresa e
        LEFT JOIN endereco en ON e.end_id = en.end_id
        LEFT JOIN segmento seg ON e.seg_id = seg.seg_id
        LEFT JOIN empresa_homologacao_transporte hom ON e.emp_id = hom.emp_id
        WHERE e.emp_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro ao preparar a consulta: " . $conn->error); // Adiciona depuração
}
$stmt->bind_param("i", $emp_id_fornecedor);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: " . APP_ROOT . "login/?erro=" . urlencode("Empresa não encontrada."));
    exit;
}
$empresa = $result->fetch_assoc();
$stmt->close();

// --- 3. FUNÇÃO AUXILIAR PARA CRIAR OS LINKS DOS DOCUMENTOS ---
function renderizaLinhaDocumento($label, $path) {
    echo '<li class="doc-item">';
    echo '<span>' . htmlspecialchars($label) . '</span>';
    if (!empty($path)) {
        $basename = basename(htmlspecialchars($path));
        // Usamos APP_ROOT para garantir que o link é absoluto
        echo '<a href="' . APP_ROOT . ltrim(htmlspecialchars($path), '/') . '" target="_blank" class="doc-link download" title="Baixar: ' . $basename . '">Baixar Documento</a>';
    } else {
        echo '<span class="doc-link-empty">(Não enviado)</span>';
    }
    echo '</li>';
}

// --- 4. LÓGICA DE VERIFICAÇÃO (PARA O HTML) ---
$isTransporte = (stripos($empresa['seg_nome'] ?? '', 'Transporte') !== false);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dados da Empresa - Portal TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style>
        /* --- 5. CSS PARA A NOVA LISTA DE DOCUMENTOS --- */
        .doc-list { list-style: none; padding: 0; margin: 0; }
        .doc-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 10px; border-bottom: 1px solid #eee;
        }
        .doc-item:last-child { border-bottom: none; }
        .doc-item span { font-size: 1.0em; color: #333; }
        .doc-link { text-decoration: none; font-weight: bold; padding: 5px 10px; border-radius: 5px; font-size: 0.9em; }
        .doc-link.download {
            background-color: #007bff; color: white;
            transition: background-color 0.2s ease;
        }
        .doc-link.download:hover { background-color: #0056b3; }
        .doc-link-empty { font-size: 0.9em; color: #999; font-style: italic; }
        
        /* Ajuste para o campo de segmento (que estava em "two-label-input" mas estava sozinho) */
        .campo-segmento { width: 49%; /* Ajuste para caber ao lado do País */ }
        @media (max-width: 768px) {
            .campo-segmento { width: 100%; /* Em ecrãs pequenos, ocupa a largura toda */ }
        }
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once ROOT_PATH . '/fornecedores/navbar.php'; ?>
    </nav>
    <main class="content">
        
        <header>
            <h1>Dados da Minha Empresa</h1>
            <p>Aqui podes consultar os teus dados cadastrais e aceder a todos os documentos enviados.</p>
        </header>

        <section class="form-card" style="margin-top: 20px;">
            <h2>Dados Cadastrais</h2>
            
            <div class="input-group two-label-input">
                <div class="campo"><label>Razão Social</label><input type="text" value="<?= htmlspecialchars($empresa['emp_razao_social']) ?>" readonly></div>
                <div class="campo"><label>Nome Fantasia</label><input type="text" value="<?= htmlspecialchars($empresa['emp_nome_fantasia']) ?>" readonly></div>
            </div>
            <div class="input-group two-label-input">
                <div class="campo"><label>CNPJ</label><input type="text" value="<?= htmlspecialchars($empresa['emp_cnpj']) ?>" readonly></div>
                <div class="campo"><label>Inscrição Social</label><input type="text" value="<?= htmlspecialchars($empresa['emp_insc_social']) ?>" readonly></div>
            </div>
            <div class="input-group two-label-input">
                <div class="campo"><label>CEP</label><input type="text" value="<?= htmlspecialchars($empresa['end_cep']) ?>" readonly></div>
                <div class="campo"><label>Rua</label><input type="text" value="<?= htmlspecialchars($empresa['end_rua']) ?>" readonly></div>
            </div>
            <div class="input-group two-label-input">
                <div class="campo"><label>Número</label><input type="text" value="<?= htmlspecialchars($empresa['end_nmr']) ?>" readonly></div>
                <div class="campo"><label>Bairro</label><input type="text" value="<?= htmlspecialchars($empresa['end_bairro']) ?>" readonly></div>
            </div>
            <div class="input-group two-label-input">
                <div class="campo"><label>Cidade</label><input type="text" value="<?= htmlspecialchars($empresa['end_cidade']) ?>" readonly></div>
                <div class="campo"><label>Estado</label><input type="text" value="<?= htmlspecialchars($empresa['end_uf']) ?>" readonly></div>
            </div>
             <div class="input-group two-label-input">
                <div class="campo"><label>País</label><input type="text" value="<?= htmlspecialchars($empresa['end_pais']) ?>" readonly></div>
                <div class="campo"><label>Segmento</label><input type="text" value="<?= htmlspecialchars($empresa['seg_nome'] ?? 'N/D') ?>" readonly></div>
            </div>
        </section>

        <section class="form-card" style="margin-top: 20px;">
            <h2>Arquivo de Documentos</h2>
            <ul class="doc-list">
                <?php
                // --- Documentos da ISO ---
                if ($empresa['emp_possui_iso'] == 1) {
                    renderizaLinhaDocumento('Certificado ISO', $empresa['emp_doc_legal_path']);
                } else {
                    renderizaLinhaDocumento('Documentos Legais (Sem ISO)', $empresa['emp_doc_legal_path']);
                    renderizaLinhaDocumento('Checklist Operacional (Sem ISO)', $empresa['emp_checklist_path']);
                }
                
                // --- Documentos de Transporte (SÓ SE FOR DE TRANSPORTE) ---
                if ($isTransporte) {
                    echo '<h3 style="margin: 15px 0 5px 0; padding-top: 10px; border-top: 1px solid #eee;">Documentos de Transporte</h3>';
                    
                    renderizaLinhaDocumento('Seguro do Contrato', $empresa['hom_seguro_path'] ?? null);
                    renderizaLinhaDocumento('Ficha de Emergência e Envelope', $empresa['hom_ficha_emergencia_path'] ?? null);
                    renderizaLinhaDocumento('Documento Fiscal do Produto', $empresa['hom_doc_fiscal_path'] ?? null);
                    renderizaLinhaDocumento('POP (Proibição Condutor)', $empresa['hom_pop_condutor_path'] ?? null);
                    renderizaLinhaDocumento('POP (Uso de Equipamentos Emerg.)', $empresa['hom_pop_emergencia_path'] ?? null);
                }
                ?>
            </ul>
        </section>
        
    </main>
</div>

<?php 
// --- MUDANÇA 4: Usando o teu script (igual ao home.php) ---
if (defined('ROOT_PATH')) {
    @include_once ROOT_PATH . '/includes/scripts.php';
}
if (isset($conn)) { $conn->close(); } 
?>
</body>
</html>