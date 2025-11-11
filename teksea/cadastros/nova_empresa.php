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

// --- MUDANÇA: Precisamos buscar os segmentos para o dropdown ---
$segmentos = [];
$sql_segmentos = "SELECT seg_id, seg_nome FROM segmento ORDER BY seg_nome ASC";
$result_segmentos = $conn->query($sql_segmentos);
if ($result_segmentos && $result_segmentos->num_rows > 0) {
    while ($row_seg = $result_segmentos->fetch_assoc()) {
        $segmentos[] = $row_seg;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Empresa - Portal TekSea</title>
    <link rel="stylesheet" href="/css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style>
        /* (Teu CSS permanece o mesmo) */
        #campos_sem_iso { display: none; width: 100%; padding: 15px; background-color: #f9f9f9; border: 1px dashed #ccc; border-radius: 5px; margin-top: 15px; }
        #campos_sem_iso .campo { width: 100%; }
        #campos_sem_iso small { font-size: 0.8em; color: #555; margin-top: 5px; display: block; }
        #secaoHomologacaoTransporte { display: none; width: 100%; padding: 20px; background-color: #f8faff; border: 2px dashed #007bff; border-radius: 8px; margin-top: 20px; margin-bottom: 20px; }
        #secaoHomologacaoTransporte h3 { color: #007bff; margin-top: 0; margin-bottom: 10px; }
        #secaoHomologacaoTransporte small { font-size: 0.8em; color: #555; margin-top: 5px; display: block; }
        #secaoHomologacaoTransporte .checkbox-group label[for^="check_"] { display: block; margin-bottom: 8px; font-weight: normal; }
        #secaoHomologacaoTransporte .checkbox-group label[for^="check_"] input[type="checkbox"] { margin-right: 10px; vertical-align: middle; }
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
            <h1>Cadastro de Nova Empresa</h1>
        </header>
        
        <?php
        // Bloco de Erro (Mantido)
        if (isset($_GET['erro'])) {
             echo "<p style='color:red; background-color:#ffebee; padding:10px; border-radius:5px; border:1px solid red; margin-bottom:20px; font-weight: bold;'>"
                   . "<b>ERRO AO SALVAR:</b> " . htmlspecialchars($_GET['erro']) . "</p>";
        }
        ?>
        <section class="form-card" style="margin-top: 20px;">
            <form action="processa_empresa.php" method="POST" enctype="multipart/form-data">
                
                <div class="input-group two-label-input"> <div class="campo"><label for="cad_razao_social">Razão Social</label><input type="text" id="cad_razao_social" name="emp_razao_social" required></div> <div class="campo"><label for="cad_nome_fantasia">Nome Fantasia</label><input type="text" id="cad_nome_fantasia" name="emp_nome_fantasia" required></div> </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="cad_cnpj">CNPJ</label><input type="text" id="cad_cnpj" name="emp_cnpj" required></div> <div class="campo"><label for="cad_insc_social">Inscrição Social</label><input type="text" id="cad_insc_social" name="emp_insc_social" required></div> </div>
                <div class="input-group two-label-input"> 
                    <div class="campo">
                        <label for="cad_tipo">Tipo</label>
                        <select id="cad_tipo" name="emp_tipo" required>
                            <option value="0">TEKSEA</option>
                            <option value="1">FORNECEDORES</option>
                            <option value="2">TERCEIROS</option>
                            <option value="3">CLIENTES</option>
                        </select>
                    </div> 
                    <div class="campo">
                        <label for="cad_segmento">Segmento de Atuação</label>
                        <select id="cad_segmento" name="seg_id" required>
                            <option value="" disabled selected>Selecione um segmento</option>
                            <?php foreach ($segmentos as $seg): ?>
                                <?php
                                    $seg_nome = htmlspecialchars($seg['seg_nome']);
                                    $seg_id = htmlspecialchars($seg['seg_id']);
                                    $data_attr = '';
                                    if (stripos($seg_nome, 'Transporte') !== false) {
                                        $data_attr = ' data-segmento-transporte="true"';
                                    }
                                ?>
                                <option value="<?= $seg_id ?>"<?= $data_attr ?>>
                                    <?= $seg_nome ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div> 
                </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="cad_cep">CEP</label><input type="text" id="cad_cep" name="cep" required></div> <div class="campo"><label for="cad_rua">Rua</label><input type="text" id="cad_rua" name="rua" required></div> </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="cad_nmr">Número</label><input type="text" id="cad_nmr" name="nmr" required></div> <div class="campo"><label for="cad_bairro">Bairro</label><input type="text" id="cad_bairro" name="bairro" required></div> </div>
                <div class="input-group two-label-input"> <div class="campo"><label for="cad_cidade">Cidade</label><input type="text" id="cad_cidade" name="cidade" required></div> <div class="campo"><label for="cad_uf">Estado</label><input type="text" id="cad_uf" name="uf" required></div> </div>
                <div class="input-group two-label-input"> 
                    <div class="campo"><label for="cad_pais">País</label><input type="text" id="cad_pais" name="pais" required></div> 
                    <div class="campo">
                        <label for="cad_possui_iso">Possui Certificação ISO?</label>
                        <select id="cad_possui_iso" name="emp_possui_iso" required>
                            <option value="">Selecione...</option>
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </select>
                    </div> 
                </div>

                <div id="campo_com_iso" style="display: none; width: 100%; padding: 10px 0;">
                    <div class="campo">
                        <label for="cad_certificado_iso">Anexar Certificado ISO</label>
                        <input type="file" id="cad_certificado_iso" name="certificado_iso" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                        <small>Anexar o certificado da ISO 9001 (ou similar) válido.</small>
                    </div>
                </div>
                <div id="campos_sem_iso" style="display: none;"> <h4>Anexos Obrigatórios (Sem ISO)</h4>
                    <p>Como a empresa não possui ISO, anexe os seguintes documentos para avaliação:</p>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="cad_doc_legais">Documentos Legais</label>
                            <input type="file" id="cad_doc_legais" name="documentos_legais" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                            <small>Ex: Contrato Social, Comprovante de Endereço, etc.</small>
                        </div>
                        <div class="campo">
                            <label for="cad_check_operacional">Checklist Operacional</label>
                            <input type="file" id="cad_check_operacional" name="checklist_operacional" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                            <small>Anexar o checklist de avaliação operacional preenchido.</small>
                        </div>
                    </div>
                </div>

                <div id="secaoHomologacaoTransporte">
                    <h3>Homologação: Transporte de Produtos Perigosos</h3>
                    <p>Este segmento requer o preenchimento e anexo dos seguintes itens:</p>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="homolog_seguro">Seguro do contrato de transporte:</label>
                            <input type="file" id="homolog_seguro" name="homolog_seguro" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                        </div>
                        <div class="campo">
                            <label for="homolog_ficha_emergencia">Ficha de Emergência e Envelope (NBR 7503):</label>
                            <input type="file" id="homolog_ficha_emergencia" name="homolog_ficha_emergencia" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                        </div>
                    </div>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="homolog_doc_fiscal">Documento Fiscal do produto transportado:</label>
                            <input type="file" id="homolog_doc_fiscal" name="homolog_doc_fiscal" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                        </div>
                        <div class="campo">
                            <label for="homolog_pop_condutor">POP (Proibição condutor participar carregamento):</label>
                            <input type="file" id="homolog_pop_condutor" name="homolog_pop_condutor" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                        </div>
                    </div>
                    <div class="input-group">
                        <div class="campo">
                            <label for="homolog_pop_emergencia">POP (Uso de equipamentos de emergência):</label>
                            <input type="file" id="homolog_pop_emergencia" name="homolog_pop_emergencia" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
                        </div>
                    </div>
                    
                    <div class="campo checkbox-group" style="margin-top: 15px;">
                        <label style="font-weight: bold; margin-bottom: 10px;">Confirmações de Procedimentos e Equipamentos:</label>
                        <label for="check_rotulos"><input type="checkbox" id="check_rotulos" name="check_rotulos" value="sim"> Possui Rótulos de risco e painéis de segurança correspondentes?</label>
                        <label for="check_inspecao"><input type="checkbox" id="check_inspecao" name="check_inspecao" value="sim"> Realiza a inspeção do veículo (tanque, carroceria, etc.)?</label>
                        <label for="check_equipamentos"><input type="checkbox" id="check_equipamentos" name="check_equipamentos" value="sim"> Possui todos os equipamentos necessários para emergência/avaria?</label>
                        <label for="check_incompatibilidade"><input type="checkbox" id="check_incompatibilidade" name="check_incompatibilidade" value="sim"> Realiza a verificação de incompatibilidade de cargas?</label>
                        <label for="check_manutencao"><input type="checkbox" id="check_manutencao" name="check_manutencao" value="sim"> Realiza manutenção e vistoria de segurança nos veículos e equipamentos?</label>
                        <label for="check_roteirizacao"><input type="checkbox" id="check_roteirizacao" name="check_roteirizacao" value="sim"> A roteirização evita áreas densamente povoadas/mananciais?</label>
                    </div>
                </div>

                <button type="submit" class="btn">Cadastrar</button>
            </form>
        </section>
    </main>
</div>

<script src="/js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    
    // --- LÓGICA DE MÁSCARAS E ViaCEP ---
    const cad_cep = document.getElementById("cad_cep"); 
    const cad_cnpj = document.getElementById("cad_cnpj");
    if (cad_cep) Inputmask("99999-999").mask(cad_cep); 
    if (cad_cnpj) Inputmask("99.999.999/9999-99").mask(cad_cnpj);
    if (cad_cep) { 
        cad_cep.addEventListener('blur', function () { 
            // ... (Tua lógica ViaCEP entra aqui) ... 
        }); 
    }

    // --- LÓGICA ISO (ATUALIZADA) ---
    const cad_selectIso = document.getElementById('cad_possui_iso');
    const cad_bloco_COM_iso = document.getElementById('campo_com_iso');
    const cad_inputCertificado = document.getElementById('cad_certificado_iso');
    const cad_bloco_SEM_iso = document.getElementById('campos_sem_iso');
    const cad_inputDocLegais = document.getElementById('cad_doc_legais');
    const cad_inputChecklist = document.getElementById('cad_check_operacional');

    if (cad_selectIso && cad_bloco_COM_iso && cad_inputCertificado && cad_bloco_SEM_iso && cad_inputDocLegais && cad_inputChecklist) {
        function toggleIsoFields() {
            const valor = cad_selectIso.value;
            if (valor === '1') { 
                cad_bloco_COM_iso.style.display = 'block';
                // cad_inputCertificado.required = true; // <-- REMOVIDO
                cad_bloco_SEM_iso.style.display = 'none';
                cad_inputDocLegais.required = false;
                cad_inputChecklist.required = false;
                cad_inputDocLegais.value = ''; 
                cad_inputChecklist.value = '';
            } else if (valor === '0') { 
                cad_bloco_COM_iso.style.display = 'none';
                cad_inputCertificado.required = false;
                cad_inputCertificado.value = '';
                cad_bloco_SEM_iso.style.display = 'block';
                // cad_inputDocLegais.required = true; // <-- REMOVIDO
                // cad_inputChecklist.required = true; // <-- REMOVIDO
            } else { 
                cad_bloco_COM_iso.style.display = 'none';
                cad_inputCertificado.required = false;
                cad_inputCertificado.value = '';
                cad_bloco_SEM_iso.style.display = 'none';
                cad_inputDocLegais.required = false;
                cad_inputChecklist.required = false;
                cad_inputDocLegais.value = '';
                cad_inputChecklist.value = '';
            }
        }
        cad_selectIso.addEventListener('change', toggleIsoFields);
        toggleIsoFields(); 
    } else {
        console.error("Erro: Elementos do formulário ISO não foram encontrados. Verifique os IDs.");
    }

    // --- LÓGICA DE TRANSPORTE (ATUALIZADA) ---
    const cad_selectSegmento = document.getElementById('cad_segmento');
    const cad_blocoTransporte = document.getElementById('secaoHomologacaoTransporte');
    const inputsTransporteFile = [
        document.getElementById('homolog_seguro'),
        document.getElementById('homolog_ficha_emergencia'),
        document.getElementById('homolog_doc_fiscal'),
        document.getElementById('homolog_pop_condutor'),
        document.getElementById('homolog_pop_emergencia')
    ];
    const inputsTransporteCheck = [
         document.getElementById('check_rotulos'),
         document.getElementById('check_inspecao'),
         document.getElementById('check_equipamentos'),
         document.getElementById('check_incompatibilidade'),
         document.getElementById('check_manutencao'),
         document.getElementById('check_roteirizacao')
    ];

    if (cad_selectSegmento && cad_blocoTransporte) {
        function toggleTransporteFields() {
            const selectedOption = cad_selectSegmento.options[cad_selectSegmento.selectedIndex];
            const isTransporte = selectedOption.getAttribute('data-segmento-transporte') === 'true';
            if (isTransporte) {
                cad_blocoTransporte.style.display = 'block';
                // inputsTransporteFile.forEach(input => { if(input) input.required = true; }); // <-- REMOVIDO
            } else {
                cad_blocoTransporte.style.display = 'none';
                inputsTransporteFile.forEach(input => {
                    if(input) { input.required = false; input.value = ''; }
                });
                inputsTransporteCheck.forEach(input => {
                    if(input) { input.required = false; input.checked = false; }
                });
            }
        }
        cad_selectSegmento.addEventListener('change', toggleTransporteFields);
        toggleTransporteFields(); 
    } else {
         console.error("Erro: Não foi possível encontrar 'cad_segmento' ou 'secaoHomologacaoTransporte'. Verifique os IDs.");
    }
});
</script>
<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>