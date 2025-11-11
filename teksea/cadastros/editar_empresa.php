<?php
session_start();
require __DIR__ . '/../../config/conexao.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se usuário é admin
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: /login/");
    exit;
}

// 1. Capturar e Validar o ID da empresa
if (!isset($_GET['emp_id']) || !is_numeric($_GET['emp_id'])) {
    header("Location: empresas.php?erro=" . urlencode("ID da empresa inválido."));
    exit;
}
$emp_id = (int)$_GET['emp_id'];

// --- Buscar Segmentos ---
$segmentos = [];
$sql_segmentos = "SELECT seg_id, seg_nome FROM segmento ORDER BY seg_nome ASC";
$result_segmentos = $conn->query($sql_segmentos);
if ($result_segmentos && $result_segmentos->num_rows > 0) {
    while ($row_seg = $result_segmentos->fetch_assoc()) {
        $segmentos[] = $row_seg;
    }
}

// 2. Buscar TODOS os dados da empresa e seu endereço
// --- MUDANÇA: Adicionado LEFT JOIN para a tabela de homologação ---
$sql = "SELECT e.*, en.*, hom.* FROM empresa e
        LEFT JOIN endereco en ON e.end_id = en.end_id
        LEFT JOIN empresa_homologacao_transporte hom ON e.emp_id = hom.emp_id
        WHERE e.emp_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: empresas.php?erro=" . urlencode("Empresa não encontrada."));
    exit;
}
$empresa = $result->fetch_assoc();
$stmt->close();

// --- MUDANÇA: Função auxiliar para mostrar o ficheiro atual ---
// (Vamos usar isto para TODOS os campos de upload)
function exibirArquivoAtual($path) {
    if (!empty($path)) {
        $basename = basename(htmlspecialchars($path));
        echo '<span class="arquivo-atual">';
        echo 'Atual: <a href="' . htmlspecialchars($path) . '" target="_blank">' . $basename . '</a>';
        echo '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Empresa - Portal TekSea</title>
    <link rel="stylesheet" href="/css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style>
        #campos_sem_iso { display: none; width: 100%; padding: 15px; background-color: #f9f9f9; border: 1px dashed #ccc; border-radius: 5px; margin-top: 15px; }
        #campos_sem_iso .campo { width: 100%; }
        #campos_sem_iso small { font-size: 0.8em; color: #555; margin-top: 5px; display: block; }
        .arquivo-atual { font-size: 0.9em; color: #0056b3; background-color: #e7f3ff; padding: 5px; border-radius: 4px; display: inline-block; margin-top: 5px; }

        /* --- MUDANÇA: CSS Adicionado para Homologação --- */
        #secaoHomologacaoTransporte {
            display: none; /* Escondido por defeito */
            width: 100%;
            padding: 20px;
            background-color: #f8faff;
            border: 2px dashed #007bff;
            border-radius: 8px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        #secaoHomologacaoTransporte h3 {
            color: #007bff;
            margin-top: 0;
            margin-bottom: 10px;
        }
        #secaoHomologacaoTransporte small {
             font-size: 0.8em; color: #555; margin-top: 5px; display: block; 
        }
        /* Correção dos Checkboxes */
        #secaoHomologacaoTransporte .checkbox-group label[for^="check_"] {
            display: block; 
            margin-bottom: 8px;
            font-weight: normal; 
        }
        #secaoHomologacaoTransporte .checkbox-group label[for^="check_"] input[type="checkbox"] {
            margin-right: 10px;
            vertical-align: middle; 
        }
        /* --- FIM MUDANÇA CSS --- */
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once __DIR__ . '/../navbar.php'; ?>
    </nav>
    <main class="content">
        <header>
            <a href="empresas.php" style="text-decoration:none; color: #555; font-size: 0.9em;">&larr; Voltar para Cadastros</a>
            <h1>Editar Empresa: <?= htmlspecialchars($empresa['emp_nome_fantasia']) ?></h1>
        </header>

        <?php
        // (O seu bloco de mensagens permanece igual)
        if (isset($_GET['erro'])) {
             echo "<p style='color:red; background-color:#ffebee; padding:10px; border-radius:5px; border:1px solid red; margin-bottom:20px;'>"
                   . "<b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>";
        } elseif (isset($_GET['sucesso'])) {
             echo "<p style='color:green; background-color:#e8f5e9; padding:10px; border-radius:5px; border:1px solid green; margin-bottom:20px;'>"
                   . "<b>SUCESSO:</b> " . htmlspecialchars($_GET['sucesso']) . "</p>";
        }
        ?>
        <section class="form-card">
            <h2>Atualizar Dados da Empresa</h2>
            
            <form action="atualiza_empresa.php" method="POST" enctype="multipart/form-data">
                
                <input type="hidden" name="emp_id" value="<?= $empresa['emp_id'] ?>">
                <input type="hidden" name="end_id" value="<?= $empresa['end_id'] ?>">
                
                <input type="hidden" name="doc_legal_atual" value="<?= htmlspecialchars($empresa['emp_doc_legal_path'] ?? '') ?>">
                <input type="hidden" name="checklist_atual" value="<?= htmlspecialchars($empresa['emp_checklist_path'] ?? '') ?>">
                <input type="hidden" name="certificado_iso_atual" value="<?= ($empresa['emp_possui_iso'] == 1) ? htmlspecialchars($empresa['emp_doc_legal_path'] ?? '') : '' ?>">
                
                <input type="hidden" name="homolog_seguro_atual" value="<?= htmlspecialchars($empresa['hom_seguro_path'] ?? '') ?>">
                <input type="hidden" name="homolog_ficha_emergencia_atual" value="<?= htmlspecialchars($empresa['hom_ficha_emergencia_path'] ?? '') ?>">
                <input type="hidden" name="homolog_doc_fiscal_atual" value="<?= htmlspecialchars($empresa['hom_doc_fiscal_path'] ?? '') ?>">
                <input type="hidden" name="homolog_pop_condutor_atual" value="<?= htmlspecialchars($empresa['hom_pop_condutor_path'] ?? '') ?>">
                <input type="hidden" name="homolog_pop_emergencia_atual" value="<?= htmlspecialchars($empresa['hom_pop_emergencia_path'] ?? '') ?>">


                <div class="input-group two-label-input"> <div class="campo"><label for="razao_social">Razão Social</label><input type="text" id="razao_social" name="emp_razao_social" required value="<?= htmlspecialchars($empresa['emp_razao_social']) ?>"></div> <div class="campo"><label for="nome_fantasia">Nome Fantasia</label><input type="text" id="nome_fantasia" name="emp_nome_fantasia" required value="<?= htmlspecialchars($empresa['emp_nome_fantasia']) ?>"></div> </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="cnpj">CNPJ</label><input type="text" id="cnpj" name="emp_cnpj" required value="<?= htmlspecialchars($empresa['emp_cnpj']) ?>"></div> <div class="campo"><label for="insc_social">Inscrição Social</label><input type="text" id="insc_social" name="emp_insc_social" required value="<?= htmlspecialchars($empresa['emp_insc_social']) ?>"></div> </div>
                <div class="input-group two-label-input"> 
                    <div class="campo"><label for="tipo">Tipo</label>
                        <select id="tipo" name="emp_tipo" required>
                            <option value="0" <?= $empresa['emp_tipo'] == 0 ? 'selected' : '' ?>>TEKSEA</option>
                            <option value="1" <?= $empresa['emp_tipo'] == 1 ? 'selected' : '' ?>>FORNECEDORES</option>
                            <option value="2" <?= $empresa['emp_tipo'] == 2 ? 'selected' : '' ?>>TERCEIROS</option>
                            <option value="3" <?= $empresa['emp_tipo'] == 3 ? 'selected' : '' ?>>CLIENTES</option>
                        </select>
                    </div> 
                
                    <div class="campo">
                        <label for="segmento">Segmento de Atuação</label>
                        <select id="segmento" name="seg_id" required>
                            <option value="" disabled>Selecione um segmento</option>
                            <?php foreach ($segmentos as $seg): ?>
                                <?php
                                    $seg_nome = htmlspecialchars($seg['seg_nome']);
                                    $seg_id = htmlspecialchars($seg['seg_id']);
                                    
                                    // --- MUDANÇA: Adicionando o 'data-attr' ---
                                    $data_attr = '';
                                    if (stripos($seg_nome, 'Transporte') !== false) {
                                        $data_attr = ' data-segmento-transporte="true"';
                                    }
                                ?>
                                <option 
                                    value="<?= $seg_id ?>"
                                    <?= ($empresa['seg_id'] == $seg['seg_id']) ? 'selected' : '' ?>
                                    <?= $data_attr ?>>
                                    <?= $seg_nome ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="input-group two-label-input"> <div class="campo"><label for="cep">CEP</label><input type="text" id="cep" name="cep" required value="<?= htmlspecialchars($empresa['end_cep']) ?>"></div> <div class="campo"><label for="rua">Rua</label><input type="text" id="rua" name="rua" required value="<?= htmlspecialchars($empresa['end_rua']) ?>"></div> </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="nmr">Número</label><input type="text" id="nmr" name="nmr" required value="<?= htmlspecialchars($empresa['end_nmr']) ?>"></div> <div class="campo"><label for="bairro">Bairro</label><input type="text" id="bairro" name="bairro" required value="<?= htmlspecialchars($empresa['end_bairro']) ?>"></div> </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="cidade">Cidade</label><input type="text" id="cidade" name="cidade" required value="<?= htmlspecialchars($empresa['end_cidade']) ?>"></div> <div class="campo"><label for="uf">Estado (UF)</label><input type="text" id="uf" name="uf" required value="<?= htmlspecialchars($empresa['end_uf']) ?>"></div> </div>
                
                <div class="input-group two-label-input">
                    <div class="campo">
                        <label for="pais">País</label>
                        <input type="text" id="pais" name="pais" required value="<?= htmlspecialchars($empresa['end_pais']) ?>">
                    </div>
                    <div class="campo">
                        <label for="possui_iso">Possui Certificação ISO?</label>
                        <select id="possui_iso" name="emp_possui_iso" required>
                            <option value="">Selecione...</option>
                            <option value="1" <?= $empresa['emp_possui_iso'] == 1 ? 'selected' : '' ?>>Sim</option>
                            <option value="0" <?= $empresa['emp_possui_iso'] == 0 ? 'selected' : '' ?>>Não</option>
                        </select>
                    </div>
                </div>

                <div id="campo_com_iso" style="display: none; width: 100%; padding: 10px 0;">
                    <div class="campo">
                        <label for="cad_certificado_iso">Anexar Certificado ISO</label>
                        <?php 
                        // Se tem ISO, o 'emp_doc_legal_path' é o certificado
                        if($empresa['emp_possui_iso'] == 1) {
                            exibirArquivoAtual($empresa['emp_doc_legal_path']);
                        } 
                        ?>
                        <input type="file" id="cad_certificado_iso" name="certificado_iso">
                        <small>Enviar novo arquivo (substitui o atual, se houver).</small>
                    </div>
                </div>

                <div id="campos_sem_iso">
                    <h4>Anexos Obrigatórios (Sem ISO)</h4>
                    <p>Substitua os anexos atuais ou envie novos.</p>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="doc_legais">Documentos Legais</label>
                            <?php 
                            // Se NÃO tem ISO, mostramos o doc legal
                            if($empresa['emp_possui_iso'] == 0) {
                                exibirArquivoAtual($empresa['emp_doc_legal_path']);
                            } 
                            ?>
                            <input type="file" id="doc_legais" name="documentos_legais">
                            <small>Enviar novo arquivo (substitui o atual, se houver).</small>
                        </div>
                        <div class="campo">
                            <label for="check_operacional">Checklist Operacional</label>
                            <?php 
                            // O checklist só existe se NÃO tiver ISO
                            if($empresa['emp_possui_iso'] == 0) {
                                exibirArquivoAtual($empresa['emp_checklist_path']);
                            } 
                            ?>
                            <input type="file" id="check_operacional" name="checklist_operacional">
                            <small>Enviar novo arquivo (substitui o atual, se houver).</small>
                        </div>
                    </div>
                </div>

                <div id="secaoHomologacaoTransporte">
                    <h3>Homologação: Transporte de Produtos Perigosos</h3>
                    <p>Substitua os anexos atuais ou envie novos.</p>

                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="homolog_seguro">Seguro do contrato de transporte:</label>
                            <?php exibirArquivoAtual($empresa['hom_seguro_path'] ?? null); ?>
                            <input type="file" id="homolog_seguro" name="homolog_seguro" accept=".pdf">
                        </div>
                        <div class="campo">
                            <label for="homolog_ficha_emergencia">Ficha de Emergência e Envelope (NBR 7503):</label>
                            <?php exibirArquivoAtual($empresa['hom_ficha_emergencia_path'] ?? null); ?>
                            <input type="file" id="homolog_ficha_emergencia" name="homolog_ficha_emergencia" accept=".pdf">
                        </div>
                    </div>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="homolog_doc_fiscal">Documento Fiscal do produto transportado:</label>
                            <?php exibirArquivoAtual($empresa['hom_doc_fiscal_path'] ?? null); ?>
                            <input type="file" id="homolog_doc_fiscal" name="homolog_doc_fiscal" accept=".pdf">
                        </div>
                        <div class="campo">
                            <label for="homolog_pop_condutor">POP (Proibição condutor participar carregamento):</label>
                            <?php exibirArquivoAtual($empresa['hom_pop_condutor_path'] ?? null); ?>
                            <input type="file" id="homolog_pop_condutor" name="homolog_pop_condutor" accept=".pdf">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <div class="campo">
                            <label for="homolog_pop_emergencia">POP (Uso de equipamentos de emergência):</label>
                            <?php exibirArquivoAtual($empresa['hom_pop_emergencia_path'] ?? null); ?>
                            <input type="file" id="homolog_pop_emergencia" name="homolog_pop_emergencia" accept=".pdf">
                        </div>
                    </div>

                    <div class="campo checkbox-group" style="margin-top: 15px;">
                        <label style="font-weight: bold; margin-bottom: 10px;">Confirmações de Procedimentos e Equipamentos:</label>

                        <label for="check_rotulos">
                            <input type="checkbox" id="check_rotulos" name="check_rotulos" value="1" <?= !empty($empresa['hom_check_rotulos']) ? 'checked' : '' ?>>
                            Possui Rótulos de risco e painéis de segurança correspondentes?
                        </label>
                        <label for="check_inspecao">
                            <input type="checkbox" id="check_inspecao" name="check_inspecao" value="1" <?= !empty($empresa['hom_check_inspecao']) ? 'checked' : '' ?>>
                            Realiza a inspeção do veículo (tanque, carroceria, etc.)?
                        </label>
                        <label for="check_equipamentos">
                            <input type="checkbox" id="check_equipamentos" name="check_equipamentos" value="1" <?= !empty($empresa['hom_check_equipamentos']) ? 'checked' : '' ?>>
                            Possui todos os equipamentos necessários para emergência/avaria?
                        </label>
                        <label for="check_incompatibilidade">
                            <input type="checkbox" id="check_incompatibilidade" name="check_incompatibilidade" value="1" <?= !empty($empresa['hom_check_incompatibilidade']) ? 'checked' : '' ?>>
                            Realiza a verificação de incompatibilidade de cargas?
                        </label>
                        <label for="check_manutencao">
                            <input type="checkbox" id="check_manutencao" name="check_manutencao" value="1" <?= !empty($empresa['hom_check_manutencao']) ? 'checked' : '' ?>>
                            Realiza manutenção e vistoria de segurança nos veículos e equipamentos?
                        </label>
                        <label for="check_roteirizacao">
                            <input type="checkbox" id="check_roteirizacao" name="check_roteirizacao" value="1" <?= !empty($empresa['hom_check_roteirizacao']) ? 'checked' : '' ?>>
                            A roteirização evita áreas densamente povoadas/mananciais?
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn">Salvar Alterações</button>
            </form>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>

<script>
// (A sua função 'toggleCamposIso' permanece igual)
function toggleCamposIso(valorSelecionado) {
    const camposAnexo = document.getElementById('campos_sem_iso');
    const inputDocLegais = document.getElementById('doc_legais');
    const inputChecklist = document.getElementById('check_operacional');
    
    // --- MUDANÇA: Adicionado o campo de Certificado ISO ---
    const campoComIso = document.getElementById('campo_com_iso');
    const inputCertificado = document.getElementById('cad_certificado_iso');
    
    // Caminhos atuais (para lógica do 'required')
    const docAtual = "<?= !empty($empresa['emp_doc_legal_path']) ? '1' : '0' ?>";
    const checkAtual = "<?= !empty($empresa['emp_checklist_path']) ? '1' : '0' ?>";
    const isoAtual = "<?= ($empresa['emp_possui_iso'] == 1 && !empty($empresa['emp_doc_legal_path'])) ? '1' : '0' ?>";

    if (valorSelecionado === '0') { // NÃO POSSUI ISO
        camposAnexo.style.display = 'block';
        campoComIso.style.display = 'none';
        
        // Só é obrigatório se NÃO houver um ficheiro atual
        inputDocLegais.required = (docAtual === '0');
        inputChecklist.required = (checkAtual === '0');
        inputCertificado.required = false;

    } else if (valorSelecionado === '1') { // POSSUI ISO
        camposAnexo.style.display = 'none';
        campoComIso.style.display = 'block';
        
        inputDocLegais.required = false;
        inputChecklist.required = false;
        // Só é obrigatório se NÃO houver um ficheiro atual
        inputCertificado.required = (isoAtual === '0');

    } else { // Vazio (Selecione...)
        camposAnexo.style.display = 'none';
        campoComIso.style.display = 'none';
        inputDocLegais.required = false;
        inputChecklist.required = false;
        inputCertificado.required = false;
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // (A sua lógica de Máscara e ViaCEP)
    Inputmask("99999-999").mask(document.getElementById("cep"));
    Inputmask("99.999.999/9999-99").mask(document.getElementById("cnpj"));
    
    document.getElementById('cep').addEventListener('blur', function () {
        // ... (código do ViaCEP) ...
    });
    
    // Lógica ISO (existente)
    const selectIso = document.getElementById('possui_iso');
    selectIso.addEventListener('change', function () {
        toggleCamposIso(this.value);
    });
    // Dispara a função no carregamento da página
    toggleCamposIso(selectIso.value);


    // --- 
    // MUDANÇA: INÍCIO DA LÓGICA DE TRANSPORTE
    // ---
    
    // 1. Captura os elementos
    // --- MUDANÇA: O ID aqui é 'segmento' ---
    const cad_selectSegmento = document.getElementById('segmento'); 
    const cad_blocoTransporte = document.getElementById('secaoHomologacaoTransporte');
    
    // Captura os inputs de ficheiro (para lógica do 'required')
    const inputsTransporteFile = [
        { el: document.getElementById('homolog_seguro'), path: "<?= !empty($empresa['hom_seguro_path']) ? '1' : '0' ?>" },
        { el: document.getElementById('homolog_ficha_emergencia'), path: "<?= !empty($empresa['hom_ficha_emergencia_path']) ? '1' : '0' ?>" },
        { el: document.getElementById('homolog_doc_fiscal'), path: "<?= !empty($empresa['hom_doc_fiscal_path']) ? '1' : '0' ?>" },
        { el: document.getElementById('homolog_pop_condutor'), path: "<?= !empty($empresa['hom_pop_condutor_path']) ? '1' : '0' ?>" },
        { el: document.getElementById('homolog_pop_emergencia'), path: "<?= !empty($empresa['hom_pop_emergencia_path']) ? '1' : '0' ?>" }
    ];
    // (Não vamos tornar os checkboxes 'required' na edição, é muito complexo)

    // 2. Verifica se os elementos principais existem
    if (cad_selectSegmento && cad_blocoTransporte) {

        // 3. A função que mostra/esconde
        function toggleTransporteFields() {
            const selectedOption = cad_selectSegmento.options[cad_selectSegmento.selectedIndex];
            const isTransporte = selectedOption.getAttribute('data-segmento-transporte') === 'true';

            if (isTransporte) {
                cad_blocoTransporte.style.display = 'block';
                // Define 'required' APENAS se não houver um ficheiro atual
                inputsTransporteFile.forEach(item => {
                    if(item.el) {
                        item.el.required = (item.path === '0'); // Só é obrigatório se não houver ficheiro
                    }
                });
            } else {
                cad_blocoTransporte.style.display = 'none';
                // Remove 'required' de todos
                inputsTransporteFile.forEach(item => {
                    if(item.el) {
                        item.el.required = false;
                        // Não limpamos o valor, pois o formulário pode ser escondido e mostrado
                    }
                });
            }
        }

        // 4. Adiciona o "ouvinte" e chama a função no carregamento
        cad_selectSegmento.addEventListener('change', toggleTransporteFields);
        toggleTransporteFields(); // Executa ao carregar a página

    } else {
         console.error("Erro: Não foi possível encontrar 'segmento' ou 'secaoHomologacaoTransporte'. Verifique os IDs.");
    }
    // --- FIM DA LÓGICA DE TRANSPORTE ---


    // --- LÓGICA DE AVISO DE MUDANÇA DE SEGMENTO (Existente) ---
    const originalSegmentId = '<?= $empresa['seg_id'] ?? '' ?>';
    const editForm = document.querySelector('form[action="atualiza_empresa.php"]');
    // const segmentSelect = document.getElementById('segmento'); // Já capturado acima
    
    if (editForm && cad_selectSegmento) {
        editForm.addEventListener('submit', function(event) {
            const currentSegmentId = cad_selectSegmento.value;
            if (originalSegmentId !== currentSegmentId && originalSegmentId !== '') {
                const message = "ATENÇÃO!\n\n" +
                                "Você alterou o Segmento de Atuação desta empresa.\n\n" +
                                "Isto fará com que a lista de documentos obrigatórios (requisitos) para esta empresa seja alterada, e pode fazer com que documentos já enviados não sejam mais necessários.\n\n" +
                                "Deseja continuar e salvar esta alteração?";
                
                if (!confirm(message)) {
                    event.preventDefault(); // Bloqueia o envio
                }
            }
        });
    }
});
</script>
</body>
</html>