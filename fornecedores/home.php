<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();

require_once __DIR__ . '/../config/config.php'; // Inclui o config.php primeiro

// 1. Segurança: Verifica se o usuário está logado e se é do tipo 'Fornecedor' (ex: tipo 1)
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 1) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

// 2. Conexão com o banco
require_once ROOT_PATH . '/config/conexao.php';

$emp_id_fornecedor = (int)$_SESSION['emp_id'];

// ---
// MUDANÇA: QUERIES DO DASHBOARD SEPARADAS
// ---

// 1. Total Pendentes / Recusados
// (Junta status 0 = Pendente e 3 = Recusado)
$stmt_pend = $conn->prepare("
    SELECT COUNT(*) 
    FROM anexo 
    WHERE emp_id = ? AND (anx_status = 0 OR anx_status = 3)
");
$stmt_pend->bind_param("i", $emp_id_fornecedor); 
$stmt_pend->execute();
$total_pendentes = $stmt_pend->get_result()->fetch_row()[0] ?? 0;
$stmt_pend->close();

// 2. Total Em Análise
// (Usa o teu status 1 = Aguardando Aprovação)
$stmt_analise = $conn->prepare("
    SELECT COUNT(*) 
    FROM anexo 
    WHERE emp_id = ? AND anx_status = 1
");
$stmt_analise->bind_param("i", $emp_id_fornecedor); 
$stmt_analise->execute();
$total_em_analise = $stmt_analise->get_result()->fetch_row()[0] ?? 0;
$stmt_analise->close();


// 3. Total Aprovado
// (Usa o teu status 2 = Aprovado)
$stmt_apr = $conn->prepare("
    SELECT COUNT(*) 
    FROM anexo 
    WHERE emp_id = ? AND anx_status = 2
");
$stmt_apr->bind_param("i", $emp_id_fornecedor); 
$stmt_apr->execute();
$total_aprovados = $stmt_apr->get_result()->fetch_row()[0] ?? 0;
$stmt_apr->close();

// 4. Alertas de Vencimento (Documentos APROVADOS que vencem nos próximos 30 dias)
$stmt_venc = $conn->prepare("
    SELECT a.anx_nome, a.anx_data_vencimento, t.tipo_descricao 
    FROM anexo a
    JOIN tipo t ON a.tipo_id = t.tipo_id
    WHERE a.emp_id = ? 
    AND a.anx_status = 2 -- Aprovado
    AND a.anx_vitalicio = 0 -- Não é vitalício
    AND a.anx_data_vencimento BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY
    ORDER BY a.anx_data_vencimento ASC
");
$stmt_venc->bind_param("i", $emp_id_fornecedor);
$stmt_venc->execute();
$documentos_a_vencer = $stmt_venc->get_result()->fetch_all(MYSQLI_ASSOC); // Lista de documentos
$total_vencendo_30d = count($documentos_a_vencer); // O número total
$stmt_venc->close();

// 5. --- MUDANÇA: CORRIGIDA A QUERY DA LISTA DE ALERTAS ---
// (Agora inclui status 0, 1 e 3)
$stmt_lista_pend = $conn->prepare("
    SELECT a.anx_nome, a.anx_status, t.tipo_descricao
    FROM anexo a
    JOIN tipo t ON a.tipo_id = t.tipo_id
    WHERE a.emp_id = ? AND (a.anx_status = 0 OR a.anx_status = 1 OR a.anx_status = 3) -- Pendente, Em Análise ou Recusado
    ORDER BY a.anx_data_atualizacao DESC
");
$stmt_lista_pend->bind_param("i", $emp_id_fornecedor);
$stmt_lista_pend->execute();
$documentos_pendentes = $stmt_lista_pend->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_lista_pend->close();

// O teu mapa de status (copiado da tua homologacao.php)
$status_map = [
    0 => 'Pendente',
    1 => 'Aguardando Aprovação',
    2 => 'Aprovado',
    3 => 'Recusado'
];

// PARTE 2: APRESENTAÇÃO (HTML)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal do Fornecedor - TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    
    <style> 
        /* ... (O teu CSS do modal e dashboard-grid) ... */
        .dashboard-grid {
            display: grid;
            /* MUDANÇA: 4 colunas */
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-card {
            background-color: #fff; padding: 20px; border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-left: 5px solid #ccc;
        }
        .dashboard-card h3 { margin: 0; font-size: 2.5em; color: #333; }
        .dashboard-card p { margin: 5px 0 0 0; font-size: 1.1em; font-weight: 500; color: #555; }
        .dashboard-card.pendente { border-color: #f0ad4e; } /* Laranja */
        .dashboard-card.pendente h3 { color: #f0ad4e; }
        .dashboard-card.analise { border-color: #0275d8; } /* Azul */
        .dashboard-card.analise h3 { color: #0275d8; }
        .dashboard-card.aprovado { border-color: #5cb85c; } /* Verde */
        .dashboard-card.aprovado h3 { color: #5cb85c; }
        .dashboard-card.alerta { border-color: #d9534f; } /* Vermelho */
        .dashboard-card.alerta h3 { color: #d9534f; }

        /* Estilos da Lista de Alertas (Mantido) */
        .alert-list-card {
            background-color: #fff; padding: 20px 25px; border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px;
        }
        .alert-list-card h2 { margin-top: 0; margin-bottom: 15px; color: #333; }
        .alert-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 15px 10px; border-bottom: 1px solid #f0f0f0;
        }
        .alert-item:last-child { border-bottom: none; }
        
        /* --- MUDANÇA: CSS de Centralização e Ícones --- */
        .alert-item .icon {
            font-size: 1.5em;
            margin-right: 15px;
            /* Adiciona uma largura fixa e centraliza o ícone nela */
            width: 2em;
            text-align: center; 
        }
        .alert-item .icon.vencendo { color: #d9534f; } 
        .alert-item .icon.pendente { color: #f0ad4e; }
        .alert-item .icon.analise { color: #0275d8; } /* NOVO: Ícone Azul */
        .alert-item .icon.recusado { color: #d9534f; }
        
        .alert-item .alert-text {
            /* Faz o bloco de texto crescer para preencher o espaço */
            flex-grow: 1; 
        }
        /* --- FIM DA MUDANÇA --- */
        
        .alert-item .alert-text strong { display: block; font-size: 1.1em; color: #333; }
        .alert-item .alert-text span { font-size: 0.9em; color: #777; }
        .alert-item .btn-atualizar {
            background-color: var(--cor-verde, #385856); color: white; padding: 8px 12px;
            text-decoration: none; border-radius: 6px; font-weight: 500;
            font-size: 0.9em; white-space: nowrap; 
            margin-left: 15px; /* Adiciona espaço entre o texto e o botão */
        }
        .alert-item .btn-atualizar:hover { background-color: var(--cor-verde-hover, #2c4543); }
        .alert-list-empty { text-align: center; padding: 20px; color: #888; font-size: 1.1em; }
        
        /* --- Estilos do Modal de Boas-Vindas (Mantido) --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.7); display: none;
            justify-content: center; align-items: center; z-index: 1050;
            opacity: 0; transition: opacity 0.3s ease-in-out;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background-color: #fff; padding: 25px 35px; border-radius: 8px;
            width: 90%; max-width: 650px; max-height: 80vh; overflow-y: auto;
            position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transform: scale(0.9); transition: transform 0.3s ease-in-out;
        }
         .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-close {
            position: absolute; top: 10px; right: 15px; font-size: 28px;
            font-weight: bold; color: #aaa; cursor: pointer;
            border: none; background: none; line-height: 1;
        }
         .modal-close:hover { color: #333; }
        .modal-content h2 { margin-top: 0; margin-bottom: 20px; color: var(--cor-verde); text-align: center; font-size: 1.5em; }
        .modal-content h3 { margin-top: 20px; margin-bottom: 10px; font-size: 1.1em; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .modal-content p, .modal-content li { font-size: 0.95em; line-height: 1.6; color: #444; margin-bottom: 10px; }
         .modal-content ul { padding-left: 20px; margin-bottom: 15px; }
         .modal-content strong { color: var(--cor-preto); font-weight: 600; }
         .modal-content .modal-footer { text-align: right; margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; }
         .modal-content .btn-fechar { padding: 10px 20px; background-color: var(--cor-verde); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9em; }
           .modal-content .btn-fechar:hover { background-color: var(--cor-verde-hover); }
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once ROOT_PATH . '/fornecedores/navbar.php'; ?>
    </nav>

    <main class="content">
        <header>
            <h1>Bem-vindo ao Portal TekSea, <?= htmlspecialchars($_SESSION['user_nome']); ?>!</h1>
        </header>

        <section class="dashboard-grid">
            <div class="dashboard-card pendente">
                <h3><?= $total_pendentes ?></h3>
                <p>Pendentes / Recusados</p>
            </div>
            <div class="dashboard-card analise">
                <h3><?= $total_em_analise ?></h3>
                <p>Em Análise</p>
            </div>
            <div class="dashboard-card aprovado">
                <h3><?= $total_aprovados ?></h3>
                <p>Documentos Aprovados</p>
            </div>
            <div class="dashboard-card alerta">
                <h3><?= $total_vencendo_30d ?></h3>
                <p>Documentos a Vencer (30 dias)</p>
            </div>
        </section>

        <section class="alert-list-card">
            <h2>Alertas Importantes</h2>
            
            <?php if (empty($documentos_a_vencer) && empty($documentos_pendentes)): ?>
                <div class="alert-list-empty">
                    <i class="fa-solid fa-check-circle" style="font-size: 2em; color: #5cb85c; margin-bottom: 10px;"></i>
                    <p>Ótimo! Não há documentos pendentes ou vencendo em breve.</p>
                </div>

            <?php else: ?>
                <div class="alert-list">

                    <?php foreach ($documentos_a_vencer as $doc): ?>
                        <div class="alert-item">
                            <div class="icon vencendo">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div class="alert-text">
                                <strong><?= htmlspecialchars($doc['anx_nome']) ?></strong>
                                <span>
                                    (Categoria: <?= htmlspecialchars($doc['tipo_descricao']) ?>)
                                    <br>
                                    Vence em: 
                                    <strong><?= date('d/m/Y', strtotime($doc['anx_data_vencimento'])) ?></strong>
                                </span>
                            </div>
                            <a href="<?= APP_ROOT ?>fornecedores/minha_homologacao.php" class="btn-atualizar">
                                Atualizar Agora
                            </a>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($documentos_pendentes as $doc): ?>
                        <?php
                            // --- MUDANÇA: Lógica de ícone/cor corrigida ---
                            $status_id = $doc['anx_status'];
                            $icon_class = 'pendente'; // Default: Laranja
                            $icon_fa = 'fa-file-circle-exclamation'; // Default: Pendente
                            $status_cor = '#f0ad4e'; // Default: Laranja
                            
                            if ($status_id == 1) { // Em Análise
                                $icon_class = 'analise';
                                $icon_fa = 'fa-file-circle-question'; // Ícone Azul
                                $status_cor = '#0275d8'; // Azul
                            } elseif ($status_id == 3) { // Recusado
                                $icon_class = 'recusado';
                                $icon_fa = 'fa-file-circle-xmark'; // Ícone Vermelho
                                $status_cor = '#d9534f'; // Vermelho
                            }
                        ?>
                        <div class="alert-item">
                            <div class="icon <?= $icon_class ?>">
                                <i class="fa-solid <?= $icon_fa ?>"></i>
                            </div>
                            <div class="alert-text">
                                <strong><?= htmlspecialchars($doc['anx_nome']) ?></strong>
                                <span>
                                    (Categoria: <?= htmlspecialchars($doc['tipo_descricao']) ?>)
                                    <br>
                                    Status: 
                                    <strong style="color: <?= $status_cor ?>;">
                                        <?= htmlspecialchars($status_map[$status_id]) ?>
                                    </strong>
                                </span>
                            </div>
                            <a href="<?= APP_ROOT ?>fornecedores/minha_homologacao.php" class="btn-atualizar">
                                Verificar
                            </a>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </section>
        
    </main>
</div>

<div id="welcomeModal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close" onclick="closeWelcomeModal()">&times;</button>
        <h2>Aviso Importante aos Fornecedores</h2>
        <p>Prezado(a) fornecedor(a),<br>Bem-vindo(a)!</p>
        <p>Para seleção e contratação de fornecedores, a <strong>Teksea Sistemas de Energia Ltda</strong> adota critérios baseados em seu Código de Ética e Conduta para Fornecedores, considerando:</p>
        <ul><li>Qualidade</li><li>Preço</li><li>Prazo de entrega</li><li>Atendimento</li></ul>
        <p>Todo fornecimento de materiais deve estar vinculado ao <strong>cadastro atualizado</strong> da empresa e à concordância com o <strong>Pedido de Compra</strong>. A ausência desses requisitos será tratada como não conformidade, podendo resultar em:</p>
        <ul><li>Recusa de materiais e/ou notas fiscais;</li><li>Falta de pagamento;</li><li>Bloqueio do cadastro no banco de dados Teksea.</li></ul>
        <h3>Qualificação</h3>
        <p>Para fornecimento de materiais críticos (definidos pela Teksea considerando critérios de certificação de qualidade, prazo de entrega e valor), será necessário concluir o <strong>Processo de Qualificação</strong>.</p>
        <p>Após finalizar todas as etapas neste portal, o cadastro será analisado pelo Time de Qualidade. A aprovação será comunicada via e-mail. A manutenção e atualização dos dados e documentos é de <strong>responsabilidade do fornecedor</strong>.</p>
        <p><strong>Fique atento às datas de validade no Portal e mantenha suas informações sempre atualizadas.</strong></p>
        <h3>Validade do Cadastro e Armazenamento de Dados</h3>
        <p>O cadastro de fornecedores terá validade de <strong>2 (dois) anos</strong> a partir da data de aprovação.</p>
        <p>Após esse período, o cadastro será transferido para o acervo da Teksea, permanecendo disponível apenas para consulta interna.</p>
        <p>Os dados pessoais coletados durante o processo serão armazenados de forma segura e criptografada, conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</p>
        <p>O fornecedor poderá, a qualquer momento, solicitar atualização, anonimização ou exclusão de seus dados pessoais conforme previsto na legislação vigente.</p>
        <h3>Suporte e Contato</h3>
        <p>Em caso de dúvidas, entre em contato com o Departamento de Suprimentos: <strong>compras@teksea.net</strong></p>
        <div class="modal-footer">
             <button class="btn-fechar" onclick="closeWelcomeModal()">Entendido</button>
        </div>
    </div>
</div>

<?php 
require_once ROOT_PATH . '/includes/scripts.php'; // Seus scripts gerais
if (isset($conn)) { $conn->close(); }
?>

<script>
    // --- Lógica do Modal (Mantida) ---
    const welcomeModal = document.getElementById('welcomeModal');
    function showWelcomeModal() { if (welcomeModal) { welcomeModal.classList.add('active'); } }
    function closeWelcomeModal() { if (welcomeModal) { welcomeModal.classList.remove('active'); } }
    document.addEventListener('DOMContentLoaded', function() { setTimeout(showWelcomeModal, 500); });
    if (welcomeModal) { welcomeModal.addEventListener('click', function(event) { if (event.target === welcomeModal) { closeWelcomeModal(); } }); }
</script>
</body>
</html>