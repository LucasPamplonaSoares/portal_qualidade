<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';

// 1. Segurança
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 1 || !isset($_SESSION['emp_id'])) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

// 2. Conexão
require_once ROOT_PATH . '/config/conexao.php';

// --- Mapa de Status ---
$status_map = [
    0 => 'Pendente',
    1 => 'Aguardando Aprovação',
    2 => 'Aprovado',
    3 => 'Recusado'
];

// 3. Busca de dados
$emp_id = $_SESSION['emp_id'];
$categorias_com_docs = [];

// --- Passo 1 - Descobrir o Segmento da Empresa Logada ---
$seg_id = null;
$sql_segmento = "SELECT seg_id FROM empresa WHERE emp_id = ?";
$stmt_seg = $conn->prepare($sql_segmento);

if (!$stmt_seg) {
     die("Erro ao preparar consulta de segmento: " . $conn->error);
}

$stmt_seg->bind_param("i", $emp_id);
$stmt_seg->execute();
$result_seg = $stmt_seg->get_result();

if ($result_seg->num_rows > 0) {
    $seg_id = $result_seg->fetch_assoc()['seg_id'];
}
$stmt_seg->close();

if (empty($seg_id)) {
    // (Lógica de empresa sem segmento)
}

// --- Passo 2 - Buscar Tipos de Documento com base nas REGRAS ---
$sql_tipos = "SELECT t.tipo_id, t.tipo_descricao 
              FROM tipo t
              JOIN requisito_segmento r ON t.tipo_id = r.tipo_id
              WHERE r.seg_id = ?
              ORDER BY t.tipo_descricao ASC";

$stmt_tipos = $conn->prepare($sql_tipos);

if (!$stmt_tipos) { 
    die("Erro ao buscar tipos de documento: " . $conn->error);
}

$stmt_tipos->bind_param("i", $seg_id); 
$stmt_tipos->execute();
$result_tipos = $stmt_tipos->get_result();


if ($result_tipos && $result_tipos->num_rows > 0) {
    while ($tipo = $result_tipos->fetch_assoc()) {
        $tipo_id = $tipo['tipo_id'];
        $descricao = $tipo['tipo_descricao'];
        
        $categorias_com_docs[$tipo_id] = [
            'descricao' => $descricao,
            'anexos' => []
        ];

        // SQL de Anexos busca 'anx_vitalicio'
        $sql_anexos = "SELECT 
                            a.anx_id, a.anx_nome, a.anx_status,
                            a.anx_data_vencimento, 
                            a.anx_vitalicio, 
                            a.anx_data_criacao, a.anx_data_atualizacao,
                            criador.user_nome AS criador_nome,
                            atualizador.user_nome AS atualizador_nome
                        FROM anexo a
                        LEFT JOIN usuario criador ON a.anx_criado_por_id = criador.user_id
                        LEFT JOIN usuario atualizador ON a.user_id_atualizacao = atualizador.user_id
                        WHERE a.tipo_id = ? AND a.emp_id = ?
                        ORDER BY a.anx_nome ASC";
                        
        $stmt_anexos = $conn->prepare($sql_anexos);
        if (!$stmt_anexos) { 
             error_log("Erro ao preparar SQL para tipo $tipo_id: " . $conn->error);
             continue; 
        }
        
        $stmt_anexos->bind_param("ii", $tipo_id, $emp_id);
        if (!$stmt_anexos->execute()) { 
             error_log("Erro ao executar SQL para tipo $tipo_id: " . $stmt_anexos->error);
             $stmt_anexos->close();
             continue;
        }

        $result_anexos = $stmt_anexos->get_result();
        if ($result_anexos) {
            while ($anexo = $result_anexos->fetch_assoc()) {
                $categorias_com_docs[$tipo_id]['anexos'][] = $anexo;
            }
        }
        $stmt_anexos->close();
    }
}
$stmt_tipos->close(); 

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Homologação de Documentos - TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css"> <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style>
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; position: relative; }
        .modal-close { position: absolute; top: 10px; right: 15px; font-size: 24px; font-weight: bold; color: #aaa; cursor: pointer; border: none; background: none; }
        .modal-content h3 { margin-top: 0; margin-bottom: 20px; }
        .status { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: #fff; text-align: center; display: inline-block; }
        .status-pendente { background-color: #f0ad4e; }
        .status-aguardando { background-color: #0275d8; }
        .status-aprovado { background-color: #5cb85c; }
        .status-reprovado { background-color: #d9534f; }
        .status-default { background-color: #777; }
        main.content .table-section table th, 
        main.content .table-section table td { padding-top: 15px !important; padding-bottom: 15px !important; vertical-align: middle !important; padding-left: 10px !important; padding-right: 10px !important; }
        main.content .table-section table .actions-cell { text-align: right !important; white-space: nowrap !important; width: 1% !important; }
        main.content .table-section table .actions-cell .action-link,
        main.content .table-section table .actions-cell .btn-atualizar { display: inline-flex !important; align-items: center !important; justify-content: center !important; text-decoration: none !important; padding: 8px 12px !important; border-radius: 5px !important; font-size: 14px !important; font-weight: 500 !important; margin-left: 8px !important; border: none !important; cursor: pointer !important; transition: all 0.2s ease !important; vertical-align: middle !important; }
        main.content .table-section table .actions-cell .action-link i,
        main.content .table-section table .actions-cell .btn-atualizar i { margin-right: 6px !important; font-size: 1em !important; }
        main.content .action-link.download { background-color: #eee !important; color: #333 !important; border: 1px solid #ccc !important; }
        main.content .action-link.download:hover { background-color: #ddd !important; }
        main.content .btn-atualizar { background-color: #5cb85c !important; color: white !important; border: 1px solid #4cae4c !important; }
        main.content .btn-atualizar:hover { background-color: #4a9c4a !important; }
        .category-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .category-header h3 { margin: 0; }
        .btn-anexar { background-color: #0275d8 !important; color: white !important; border: 1px solid #025aa5 !important; padding: 8px 12px !important; border-radius: 5px !important; font-size: 14px !important; font-weight: 500 !important; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; }
        .btn-anexar:hover { background-color: #025aa5 !important; }
        .btn-anexar i { margin-right: 6px; }
        .vencimento-info { display: block; line-height: 1.4; white-space: nowrap; }
        .vencimento-duracao { font-size: 0.85em; color: #555; font-style: italic; }
        .vencimento-vencido { color: #d9534f; font-weight: bold; }
        .vencimento-hoje { color: #f0ad4e; font-weight: bold; }
        .vencimento-vitalicio { font-weight: bold; color: var(--cor-verde, #385856); }

        
        /* === CSS ADICIONADO PARA CAMPOS DE TEXTO E DATA === */
        .modal-content .campo {
            margin-bottom: 15px; 
        }
        .modal-content .campo label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }
        .modal-content .campo input[type="text"],
        .modal-content .campo input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--cor-cinza-borda, #ddd);
            border-radius: 6px; 
            font-size: 15px;
            font-family: inherit; 
            box-sizing: border-box; 
            height: 41px; 
        }
        /* === FIM DO CSS ADICIONADO === */

        /* === INÍCIO CSS MODIFICADO: Estilo do Botão "Selecionar Arquivo" === */
        .modal-content .upload-wrapper .btn-upload {
            background-color: var(--cor-verde, #385856) !important;
            color: white !important; /* <-- SUA ALTERAÇÃO: TEXTO BRANCO */
            border: none !important;
            
            padding: 8px 12px !important;
            border-radius: 5px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }
        .modal-content .upload-wrapper .btn-upload:hover {
            background-color: var(--cor-verde-hover, #2c4543) !important;
        }
        .modal-content .upload-wrapper .btn-upload i {
             margin-right: 6px;
        }
        /* === FIM CSS MODIFICADO === */

        /* === CSS DO SELETOR SIM/NÃO === */
        .grupo-vitalicio {
            margin-top: 15px; 
        }
        .etiqueta-grupo {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #333; 
        }
        .opcoes-vitalicio {
            display: flex;
            width: 100%;
        }
        .opcoes-vitalicio input[type="radio"] {
            display: none;
        }
        .opcoes-vitalicio label {
            padding: 10px 12px;
            border: 1px solid var(--cor-cinza-borda, #ddd); 
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
            font-size: 15px;
            color: #555;
            background-color: #f9f9f9;
            text-align: center;
            flex-grow: 1;
            flex-basis: 0;
        }
        /* IDs do Modal de Criação */
        .opcoes-vitalicio label[for="modal-create-vitalicio-sim"] {
            border-top-left-radius: 6px;
            border-bottom-left-radius: 6px;
            border-right: none;
        }
        .opcoes-vitalicio label[for="modal-create-vitalicio-nao"] {
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
        }
        /* --- NOVO: IDs do Modal de ATUALIZAÇÃO --- */
        .opcoes-vitalicio label[for="modal-update-vitalicio-sim"] {
            border-top-left-radius: 6px;
            border-bottom-left-radius: 6px;
            border-right: none;
        }
        .opcoes-vitalicio label[for="modal-update-vitalicio-nao"] {
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
        }
        /* --- FIM NOVO --- */
        .opcoes-vitalicio input[type="radio"]:checked + label {
            background-color: var(--cor-verde, #385856);
            color: #ffffff;
            border-color: var(--cor-verde, #385856);
            font-weight: bold;
        }
        /* === FIM DO CSS DO SELETOR === */
        
    </style>
</head>
<body>
    <div class="container">
        <nav class="sidebar">
            <?php require_once __DIR__ . '/navbar.php'; ?>
        </nav>
        <main class="content">
            <header><h1>Homologação de Documentos</h1></header>
             <?php
             if (isset($_GET['erro'])) { echo "<p class='mensagem mensagem-erro'><b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>"; }
             if (isset($_GET['sucesso'])) { echo "<p class='mensagem mensagem-sucesso'><b>SUCESSO:</b> " . htmlspecialchars($_GET['sucesso']) . "</p>"; }
             
             if (empty($categorias_com_docs) && !empty($seg_id)) {
                 echo "<p class='mensagem mensagem-info'>Ainda não há requisitos de documentos definidos para o segmento da sua empresa.</p>";
             } elseif (empty($seg_id)) {
                 echo "<p class='mensagem mensagem-erro'><b>Atenção:</b> O cadastro da sua empresa está incompleto. Não foi possível identificar o seu segmento de atuação.</p>";
             }
             ?>

            <?php foreach ($categorias_com_docs as $tipo_id => $categoria_info): ?>
                <section class="card table-section">
                    
                    <div class="category-header">
                        <h3><?= htmlspecialchars($categoria_info['descricao']) ?></h3>
                        <button class="btn-anexar" 
                                onclick="openCreateModal(<?= $tipo_id ?>, '<?= htmlspecialchars(addslashes($categoria_info['descricao'])) ?>')">
                            <i class="fa-solid fa-plus"></i> Anexar Documento
                        </button>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Nome do Documento</th>
                                <th>Status</th>
                                <th>Vencimento / Duração</th>
                                <th>Data de Envio</th>
                                <th>Enviado por</th>
                                <th>Última Atualização</th>
                                <th>Atualizado por</th>
                                <th class="actions-cell">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categoria_info['anexos'])): foreach ($categoria_info['anexos'] as $anexo): ?>
                                <?php
                                    // Lógica de Status
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
                                    <td><span class="status <?= $status_class ?>"><?= $status_texto ?></span></td>
                                    
                                    <td>
                                        <?php
                                        if (!empty($anexo['anx_vitalicio']) && $anexo['anx_vitalicio'] == 1) {
                                            echo '<span class="vencimento-info vencimento-vitalicio">Vitalício</span>';
                                        } elseif (empty($anexo['anx_data_vencimento'])) {
                                            echo '-';
                                        } else {
                                            try {
                                                $hoje = new DateTime(); $hoje->setTime(0, 0, 0);
                                                $vencimento = new DateTime($anexo['anx_data_vencimento']);
                                                $data_formatada = $vencimento->format('d/m/Y');
                                                echo '<span class="vencimento-info">' . $data_formatada;
                                                $diff = $hoje->diff($vencimento);
                                                $diff_dias_total = (int)$diff->format('%R%a'); 
                                                if ($diff_dias_total < 0) {
                                                    echo '<br><span class="vencimento-duracao vencimento-vencido">(Vencido há ' . abs($diff_dias_total) . ' dias)</span>';
                                                } elseif ($diff_dias_total == 0) {
                                                    echo '<br><span class="vencimento-duracao vencimento-hoje">(Vence hoje)</span>';
                                                } else {
                                                    $duracao_str = 'Vence em ';
                                                    if ($diff->y > 0) { $duracao_str .= $diff->y . ($diff->y > 1 ? ' anos' : ' ano'); }
                                                    elseif ($diff->m > 0) { $duracao_str .= $diff->m . ($diff->m > 1 ? ' meses' : ' mês'); }
                                                    else { $duracao_str .= $diff->d . ($diff->d > 1 ? ' dias' : ' dia'); }
                                                    echo '<br><span class="vencimento-duracao">(' . $duracao_str . ')</span>';
                                                }
                                                echo '</span>';
                                            } catch (Exception $e) { echo 'Data inválida'; }
                                        }
                                        ?>
                                    </td>

                                    <td><?= !empty($anexo['anx_data_criacao']) ? date('d/m/Y H:i', strtotime($anexo['anx_data_criacao'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($anexo['criador_nome'] ?? 'N/A') ?></td>
                                    <td><?= !empty($anexo['anx_data_atualizacao']) ? date('d/m/Y H:i', strtotime($anexo['anx_data_atualizacao'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($anexo['atualizador_nome'] ?? 'N/A') ?></td>
                                    
                                    <td class="actions-cell">
                                        
                                        <button class="btn-atualizar" title="Atualizar" 
                                            onclick="openUpdateModal(
                                                <?= $anexo['anx_id'] ?>, 
                                                '<?= htmlspecialchars(addslashes($anexo['anx_nome'])) ?>',
                                                '<?= htmlspecialchars($anexo['anx_data_vencimento'] ?? '') ?>',
                                                <?= (int)($anexo['anx_vitalicio'] ?? 0) ?>
                                            )">
                                            <i class="fa-solid fa-upload"></i> Atualizar
                                        </button>
                                        <a href="<?= APP_ROOT ?>teksea/cadastros/download_anexo.php?id=<?= $anexo['anx_id'] ?>" 
                                           class="action-link download" title="Baixar">
                                            <i class="fa-solid fa-download"></i> Baixar
                                        </a>
                                    </td>
                                    </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="8">Nenhum documento enviado para esta categoria ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        </main>
    </div>

    <div id="updateModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeUpdateModal()">&times;</button>
            <h3 id="modal-title">Atualizar Documento</h3>
            
            <form action="<?= APP_ROOT ?>fornecedores/processa_atualizacao_fornecedor.php" method="POST" enctype="multipart/form-data"> 
                <input type="hidden" name="anx_id" id="modal-update-anx-id">
                
                <div class="campo">
                    <label for="modal-update-vencimento">Data de Vencimento (Opcional)</label>
                    <input type="date" name="anx_data_vencimento" id="modal-update-vencimento">
                </div>
                
                <div class="campo">
                     <div class="grupo-vitalicio">
                        <span class="etiqueta-grupo">Documento Vitalício?</span>
                        <div class="opcoes-vitalicio">
                            <input type="radio" id="modal-update-vitalicio-sim" name="anx_vitalicio" value="1">
                            <label for="modal-update-vitalicio-sim">Sim</label>

                            <input type="radio" id="modal-update-vitalicio-nao" name="anx_vitalicio" value="0">
                            <label for="modal-update-vitalicio-nao">Não</label>
                        </div>
                    </div>
                </div>
                <div class="campo">
                    <label for="modal-novo-anexo">Selecionar Novo Arquivo (Opcional)</label>
                    <div class="upload-wrapper">
                        <input type="file" name="novo_anexo" id="modal-novo-anexo" hidden>
                        <label for="modal-novo-anexo" class="btn-upload">
                             <i class="fa-solid fa-upload"></i> Selecionar Arquivo
                        </label>
                        <span id="modal-file-name" class="file-name">Nenhum arquivo selecionado</span>
                    </div>
                </div>
                
                <button type="submit" class="btn" style="margin-top: 20px;">Enviar Nova Versão</button>
            </form>
        </div>
    </div>
    <div id="createModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeCreateModal()">&times;</button>
            <h3 id="modal-create-title">Anexar Novo Documento</h3>
            <form action="<?= APP_ROOT ?>fornecedores/processa_envio_fornecedor.php" method="POST" enctype="multipart/form-data"> 
                <input type="hidden" name="tipo_id" id="modal-create-tipo-id">
                
                <div class="campo">
                    <label for="modal-create-nome">Nome do Documento</label>
                    <input type="text" name="anx_nome" id="modal-create-nome" required>
                </div>
                
                <div class="campo">
                    <label for="modal-create-vencimento">Data de Vencimento (Opcional)</label>
                    <input type="date" name="anx_data_vencimento" id="modal-create-vencimento">
                </div>
                
                <div class="campo">
                     <div class="grupo-vitalicio">
                        <span class="etiqueta-grupo">Documento Vitalício?</span>
                        <div class="opcoes-vitalicio">
                            <input type="radio" id="modal-create-vitalicio-sim" name="anx_vitalicio" value="1">
                            <label for="modal-create-vitalicio-sim">Sim</label>

                            <input type="radio" id="modal-create-vitalicio-nao" name="anx_vitalicio" value="0" checked>
                            <label for="modal-create-vitalicio-nao">Não</label>
                        </div>
                    </div>
                </div>

                <div class="upload-wrapper">
                    <input type="file" name="anx_arquivo" id="modal-create-novo-anexo" required hidden>
                    <label for="modal-create-novo-anexo" class="btn-upload">
                        <i class="fa-solid fa-upload"></i> Selecionar Arquivo
                    </label>
                    <span id="modal-create-file-name" class="file-name">Nenhum arquivo selecionado</span>
                </div>
                <button type="submit" class="btn" style="margin-top: 20px;">Enviar Documento</button>
            </form>
        </div>
    </div>

    <?php if (isset($conn)) { $conn->close(); } ?>
    
    <script>
        // --- Lógica do Modal de Atualização (MODIFICADA) ---
        const updateModal = document.getElementById('updateModal');
        const modalTitle = document.getElementById('modal-title'); 
        const modalAnxId = document.getElementById('modal-update-anx-id'); // ID Corrigido
        const modalFileInput = document.getElementById('modal-novo-anexo');
        const modalFileName = document.getElementById('modal-file-name');
        
        // NOVOS ELEMENTOS DO MODAL DE ATUALIZAÇÃO
        const modalUpdateVencimento = document.getElementById('modal-update-vencimento'); 
        const modalUpdateRadioSim = document.getElementById('modal-update-vitalicio-sim');
        const modalUpdateRadioNao = document.getElementById('modal-update-vitalicio-nao');

        /**
         * Abre o modal de ATUALIZAÇÃO de arquivo
         * (Assinatura da função atualizada)
         */
        function openUpdateModal(anexoId, anexoNome, dataVencimento, isVitalicio) {
            if (updateModal) {
                modalTitle.textContent = 'Atualizar: ' + anexoNome;
                modalAnxId.value = anexoId;
                
                // Limpa o campo de arquivo
                modalFileName.textContent = 'Nenhum arquivo selecionado';
                modalFileInput.value = ''; 
                
                // Preenche os campos de data e vitalício
                if (isVitalicio == 1) {
                    modalUpdateRadioSim.checked = true;
                    modalUpdateRadioNao.checked = false;
                    modalUpdateVencimento.value = '';
                    modalUpdateVencimento.disabled = true;
                } else {
                    modalUpdateRadioSim.checked = false;
                    modalUpdateRadioNao.checked = true;
                    // Define a data (formato AAAA-MM-DD que o input[type=date] espera)
                    modalUpdateVencimento.value = dataVencimento ? dataVencimento.split(' ')[0] : '';
                    modalUpdateVencimento.disabled = false;
                }
                
                updateModal.style.display = 'flex';
            }
        }

        function closeUpdateModal() {
             if (updateModal) {
                 updateModal.style.display = 'none';
             }
        }
        
        if (modalFileInput) {
             modalFileInput.addEventListener('change', function() {
                 if (this.files.length > 0) {
                     modalFileName.textContent = this.files[0].name;
                 } else {
                     modalFileName.textContent = 'Nenhum arquivo selecionado';
                 }
             });
        }
        
        // --- NOVA LÓGICA: Toggle para o modal de ATUALIZAÇÃO ---
        function toggleUpdateModalData(isVitalicio) {
            if (isVitalicio) {
                modalUpdateVencimento.disabled = true;
                modalUpdateVencimento.value = '';
            } else {
                modalUpdateVencimento.disabled = false;
            }
        }

        if (modalUpdateRadioSim && modalUpdateRadioNao && modalUpdateVencimento) {
            modalUpdateRadioSim.addEventListener('change', () => toggleUpdateModalData(true));
            modalUpdateRadioNao.addEventListener('change', () => toggleUpdateModalData(false));
        }


        // --- Lógica do Modal de Criação (Sem mudanças) ---
        const createModal = document.getElementById('createModal');
        const modalCreateTitle = document.getElementById('modal-create-title');
        const modalCreateTipoId = document.getElementById('modal-create-tipo-id');
        const modalCreateNome = document.getElementById('modal-create-nome');
        const modalCreateFileInput = document.getElementById('modal-create-novo-anexo');
        const modalCreateFileName = document.getElementById('modal-create-file-name');
        
        const modalCreateVencimento = document.getElementById('modal-create-vencimento'); 
        const modalRadioSim = document.getElementById('modal-create-vitalicio-sim');
        const modalRadioNao = document.getElementById('modal-create-vitalicio-nao');

        function openCreateModal(tipoId, tipoDescricao) {
            if (createModal) {
                modalCreateTitle.textContent = 'Anexar em: ' + tipoDescricao;
                modalCreateTipoId.value = tipoId;
                
                modalCreateNome.value = '';
                modalCreateVencimento.value = ''; 
                modalCreateVencimento.disabled = false;
                modalRadioNao.checked = true;
                modalRadioSim.checked = false;

                modalCreateFileInput.value = '';
                modalCreateFileName.textContent = 'Nenhum arquivo selecionado';
                createModal.style.display = 'flex';
            }
        }
        function closeCreateModal() {
            if (createModal) {
                createModal.style.display = 'none';
            }
        }
        
        if (modalCreateFileInput) {
             modalCreateFileInput.addEventListener('change', function() {
                 if (this.files.length > 0) {
                     modalCreateFileName.textContent = this.files[0].name;
                 } else {
                     modalCreateFileName.textContent = 'Nenhum arquivo selecionado';
                 }
             });
        }

        function toggleCreateModalData(isVitalicio) { // Renomeado para clareza
            if (isVitalicio) {
                modalCreateVencimento.disabled = true;
                modalCreateVencimento.value = '';
                modalCreateVencimento.required = false;
            } else {
                modalCreateVencimento.disabled = false;
            }
        }

        if (modalRadioSim && modalRadioNao && modalCreateVencimento) {
            modalRadioSim.addEventListener('change', () => toggleCreateModalData(true));
            modalRadioNao.addEventListener('change', () => toggleCreateModalData(false));
            toggleCreateModalData(modalRadioSim.checked);
        }
    </script>
    </body>
</html>