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

// --- Buscar Segmentos ---
$segmentos = [];
$sql_segmentos = "SELECT seg_id, seg_nome FROM segmento ORDER BY seg_nome ASC";
$result_segmentos = $conn->query($sql_segmentos);
if ($result_segmentos && $result_segmentos->num_rows > 0) {
    while ($row_seg = $result_segmentos->fetch_assoc()) {
        $segmentos[] = $row_seg;
    }
}

// --- PAGINAÇÃO E PESQUISA: (Toda a tua lógica de paginação e busca permanece igual) ---
$limit_options = [10, 20, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$busca_nome = trim($_GET['busca_nome'] ?? '');
$busca_tipo = trim($_GET['busca_tipo'] ?? '');
$busca_segmento = trim($_GET['seg_id'] ?? ''); 

// --- PAGINAÇÃO E PESQUISA: Construção da Consulta Base e WHERE ---
$sql_base = "FROM empresa emp"; 
$where_clauses = [];
$params_where = [];
$types_where = "";

if (!empty($busca_nome)) {
    $where_clauses[] = "emp.emp_nome_fantasia LIKE ?"; 
    $params_where[] = "%" . $busca_nome . "%";
    $types_where .= "s";
}
if ($busca_tipo !== '' && is_numeric($busca_tipo) && $busca_tipo >= 0 && $busca_tipo <= 3) {
    $where_clauses[] = "emp.emp_tipo = ?"; 
    $params_where[] = (int)$busca_tipo;
    $types_where .= "i";
}
if (!empty($busca_segmento) && is_numeric($busca_segmento)) {
    $where_clauses[] = "emp.seg_id = ?";
    $params_where[] = (int)$busca_segmento;
    $types_where .= "i";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// --- PAGINAÇÃO: Contagem Total de Itens ---
$total_items = 0; 
$sql_count = "SELECT COUNT(*) as total " . $sql_base . $where_sql;
$stmt_count = $conn->prepare($sql_count);
if (!$stmt_count) { die("Erro ao preparar contagem: " . $conn->error); }
if (!empty($params_where)) {
    $stmt_count->bind_param($types_where, ...$params_where);
}
if (!$stmt_count->execute()){ error_log("Erro ao executar contagem: " . $stmt_count->error); }
else {
    $result_count = $stmt_count->get_result();
    if ($result_count) { $row_count = $result_count->fetch_assoc(); if ($row_count) { $total_items = (int)$row_count['total']; } }
}
$stmt_count->close();

// --- PAGINAÇÃO: Cálculos ---
$total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// --- PAGINAÇÃO E PESQUISA: Consulta Principal ---
$sql_data = "SELECT emp.emp_id, emp.emp_nome_fantasia, emp.emp_cnpj, emp.emp_tipo, emp.data_criacao, emp.emp_possui_iso, seg.seg_nome "
         . $sql_base // "FROM empresa emp"
         . " LEFT JOIN segmento seg ON emp.seg_id = seg.seg_id " // JOIN
         . $where_sql
         . " ORDER BY emp.data_criacao DESC LIMIT ? OFFSET ?";

$params_data = $params_where;
$params_data[] = $limit;
$params_data[] = $offset;
$types_data = $types_where . "ii";

$stmt_data = $conn->prepare($sql_data);
if (!$stmt_data) { die("Erro ao preparar consulta de dados: " . $conn->error); }
if (!empty($types_data)) {
    $stmt_data->bind_param($types_data, ...$params_data);
}
$stmt_data->execute();
$result_empresas = $stmt_data->get_result();
if (!$result_empresas) { die("Erro ao executar consulta de dados: " . $stmt_data->error); }

$tipos_empresa = ["TEKSEA", "FORNECEDORES", "TERCEIROS", "CLIENTES"];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Empresa - Portal TekSea</title>
    <link rel="stylesheet" href="/css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style>
/* --- INÍCIO: CSS para o formulário "Buscar Empresas" --- */
.search-form-card { 
    background: #fff; 
    padding: 20px 25px; 
    border-radius: 12px; 
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08); 
    margin-bottom: 20px; 
}
.search-form-card h2 { 
    margin-bottom: 15px; 
    font-size: 1.1em; 
}
.search-form-fields { 
    display: flex; 
    gap: 15px; 
    align-items: flex-end; 
    flex-wrap: wrap; 
}
.search-form-fields .campo { 
    flex: 1; 
    min-width: 180px; 
}
.search-form-fields .campo label { 
    display: block; 
    font-size: 14px; 
    margin-bottom: 6px; 
    color: var(--cor-preto, #212322); 
    font-weight: 500; 
}
.search-form-fields input[type="text"], 
.search-form-fields select { 
    width: 100%; 
    padding: 10px 12px; 
    border: 1px solid var(--cor-cinza-borda, #ddd); 
    border-radius: 6px; 
    font-size: 15px; 
    height: 41px; 
    box-sizing: border-box; 
}
.search-form-fields button { 
    padding: 0 20px; 
    background-color: var(--cor-verde, #385856); 
    color: white; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    font-size: 15px; 
    height: 41px; 
    line-height: 41px; 
}
.search-form-fields button:hover { 
    background-color: var(--cor-verde-hover, #2c4543); 
}
.search-form-fields .campo-botao { 
    flex-shrink: 0; 
}
/* --- FIM: CSS para o formulário "Buscar Empresas" --- */

/* --- INÍCIO: CSS para a Secção de Homologação --- */
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
/* --- FIM: CSS para a Secção de Homologação --- */

/* --- INÍCIO: CSS para CORREÇÃO DOS CHECKBOXES --- */
#secaoHomologacaoTransporte .checkbox-group label[for^="check_"] {
    display: block; /* Faz cada label ocupar uma linha inteira */
    margin-bottom: 8px; /* Adiciona um espaço entre eles */
    font-weight: normal; /* Garante que o texto não fique a negrito */
}
#secaoHomologacaoTransporte .checkbox-group label[for^="check_"] input[type="checkbox"] {
    margin-right: 10px;
    vertical-align: middle; /* Alinha o checkbox com o texto */
}
/* --- FIM: CSS para CORREÇÃO DOS CHECKBOXES --- */

/* --- INÍCIO: CSS PARA PAGINAÇÃO (RESTAURADO) --- */
.pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; margin-top: 20px; border-top: 1px solid var(--cor-cinza-claro, #eee); flex-wrap: wrap; }
.pagination-limit-form { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.pagination-limit-form select { padding: 8px 10px; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 4px; font-size: 14px; height: 38px; }
.pagination { display: flex; justify-content: center; align-items: center; list-style: none; padding: 0; margin: 0; flex-grow: 1; margin-bottom: 10px; }
.pagination li { margin: 0 4px; }
.pagination a, .pagination span { display: inline-block; padding: 8px 12px; text-decoration: none; color: var(--cor-verde, #385856); background-color: #fff; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 4px; transition: all 0.2s ease; font-size: 14px; }
.pagination a:hover { background-color: var(--cor-cinza-claro, #eee); border-color: #ccc; }
.pagination .active span { background-color: var(--cor-verde, #385856); color: white; border-color: var(--cor-verde, #385856); font-weight: bold; }
.pagination .disabled span { color: #aaa; background-color: #f9f9f9; border-color: var(--cor-cinza-borda, #ddd); cursor: default; }
/* --- FIM: CSS PARA PAGINAÇÃO (RESTAURADO) --- */

/* (Outros estilos que já tinhas, como .tabela-empresas, etc., devem estar no teu /css/styles.css) */
/* Se os estilos da tabela também sumiram, adiciona-os aqui */

    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once __DIR__ . '/../navbar.php'; ?>
    </nav>
    <main class="content">
        <?php
        // Bloco de mensagens de Erro/Sucesso
        if (isset($_GET['erro'])) {
             echo "<p style='color:red; background-color:#ffebee; padding:10px; border-radius:5px; border:1px solid red; margin-bottom:20px;'>"
                   . "<b>ERRO:</b> " . htmlspecialchars($_GET['erro']) . "</p>";
        } elseif (isset($_GET['sucesso'])) {
             echo "<p style='color:green; background-color:#e8f5e9; padding:10px; border-radius:5px; border:1px solid green; margin-bottom:20px;'>"
                   . "<b>SUCESSO:</b> " . htmlspecialchars($_GET['sucesso']) . "</p>";
        }
        ?>
        <header><h1>Cadastros Empresariais</h1></header>

        <section class="form-card">
            <h2>Cadastro de Nova Empresa</h2>
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
                        <input type="file" id="cad_certificado_iso" name="certificado_iso">
                        <small>Anexar o certificado da ISO 9001 (ou similar) válido.</small>
                    </div>
                </div>

                <div id="campos_sem_iso" style="display: none;"> <h4>Anexos Obrigatórios (Sem ISO)</h4>
                    <p>Como a empresa não possui ISO, anexe os seguintes documentos para avaliação:</p>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="cad_doc_legais">Documentos Legais</label>
                            <input type="file" id="cad_doc_legais" name="documentos_legais">
                            <small>Ex: Contrato Social, Comprovante de Endereço, etc.</small>
                        </div>
                        <div class="campo">
                            <label for="cad_check_operacional">Checklist Operacional</label>
                            <input type="file" id="cad_check_operacional" name="checklist_operacional">
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
                            <input type="file" id="homolog_seguro" name="homolog_seguro" accept=".pdf">
                        </div>
                        <div class="campo">
                            <label for="homolog_ficha_emergencia">Ficha de Emergência e Envelope (NBR 7503):</label>
                            <input type="file" id="homolog_ficha_emergencia" name="homolog_ficha_emergencia" accept=".pdf">
                        </div>
                    </div>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="homolog_doc_fiscal">Documento Fiscal do produto transportado:</label>
                            <input type="file" id="homolog_doc_fiscal" name="homolog_doc_fiscal" accept=".pdf">
                        </div>
                        <div class="campo">
                            <label for="homolog_pop_condutor">POP (Proibição condutor participar carregamento):</label>
                            <input type="file" id="homolog_pop_condutor" name="homolog_pop_condutor" accept=".pdf">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <div class="campo">
                            <label for="homolog_pop_emergencia">POP (Uso de equipamentos de emergência):</label>
                            <input type="file" id="homolog_pop_emergencia" name="homolog_pop_emergencia" accept=".pdf">
                        </div>
                    </div>

                    <div class="campo checkbox-group" style="margin-top: 15px;">
                        <label style="font-weight: bold; margin-bottom: 10px;">Confirmações de Procedimentos e Equipamentos:</label>

                        <label for="check_rotulos">
                            <input type="checkbox" id="check_rotulos" name="check_rotulos" value="sim">
                            Possui Rótulos de risco e painéis de segurança correspondentes?
                        </label>
                        <label for="check_inspecao">
                            <input type="checkbox" id="check_inspecao" name="check_inspecao" value="sim">
                            Realiza a inspeção do veículo (tanque, carroceria, etc.)?
                        </label>
                        <label for="check_equipamentos">
                            <input type="checkbox" id="check_equipamentos" name="check_equipamentos" value="sim">
                            Possui todos os equipamentos necessários para emergência/avaria?
                        </label>
                        <label for="check_incompatibilidade">
                            <input type="checkbox" id="check_incompatibilidade" name="check_incompatibilidade" value="sim">
                            Realiza a verificação de incompatibilidade de cargas?
                        </label>
                        <label for="check_manutencao">
                            <input type="checkbox" id="check_manutencao" name="check_manutencao" value="sim">
                            Realiza manutenção e vistoria de segurança nos veículos e equipamentos?
                        </label>
                        <label for="check_roteirizacao">
                            <input type="checkbox" id="check_roteirizacao" name="check_roteirizacao" value="sim">
                            A roteirização evita áreas densamente povoadas/mananciais?
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn">Cadastrar</button>
            </form>
        </section>
        <hr>

        <section class="search-form-card">
            <h2>Buscar Empresas</h2>
             <form action="empresas.php" method="GET" class="search-form-fields">
                <div class="campo"><label for="busca_nome">Nome Fantasia</label><input type="text" id="busca_nome" name="busca_nome" value="<?= htmlspecialchars($busca_nome) ?>" placeholder="Digite parte do nome..."></div>
                <div class="campo"><label for="busca_tipo">Tipo</label><select id="busca_tipo" name="busca_tipo"><option value="">Todos os Tipos</option><?php foreach ($tipos_empresa as $index => $nome_tipo): ?><option value="<?= $index ?>" <?= ($busca_tipo !== '' && $busca_tipo == $index) ? 'selected' : '' ?>><?= htmlspecialchars($nome_tipo) ?></option><?php endforeach; ?></select></div>
                <div class="campo">
                    <label for="busca_segmento">Segmento</label>
                    <select id="busca_segmento" name="seg_id">
                        <option value="">Todos os Segmentos</option>
                        <?php foreach ($segmentos as $seg): ?>
                            <option value="<?= htmlspecialchars($seg['seg_id']) ?>" <?= ($busca_segmento == $seg['seg_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($seg['seg_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-botao"><button type="submit">Buscar</button></div>
                <input type="hidden" name="limit" value="<?= $limit ?>">
             </form>
        </section>

        <section class="empresas-lista card table-section">
            <h2>Empresas Cadastradas <?php if ($total_items > 0): ?><span style="font-weight: normal; font-size: 0.8em;">(Exibindo <?= $result_empresas ? $result_empresas->num_rows : 0 ?> de <?= $total_items ?> - Página <?= $page ?> de <?= $total_pages ?>)</span><?php endif; ?></h2>
            <table class="tabela-empresas">
                <thead>
                    <tr>
                        <th>Nome Fantasia</th> 
                        <th>CNPJ</th> 
                        <th>Tipo</th> 
                        <th>Segmento</th> 
                        <th>Data de Criação</th> 
                        <th>Possui ISO?</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_empresas && $result_empresas->num_rows > 0): while ($e = $result_empresas->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['emp_nome_fantasia']) ?></td>
                        <td><?= htmlspecialchars($e['emp_cnpj']) ?></td>
                        <td><?= $tipos_empresa[$e['emp_tipo']] ?? "N/A" ?></td>
                        <td><?= htmlspecialchars($e['seg_nome'] ?? 'N/A') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($e['data_criacao'])) ?></td>
                        <td><?= isset($e['emp_possui_iso']) ? ($e['emp_possui_iso'] == 1 ? 'Sim' : 'Não') : 'N/D' ?></td>
                        <td class="actions-cell"> <a href="editar_empresa.php?emp_id=<?= $e['emp_id'] ?>" class="btn-editar" title="Editar Empresa"><i class="fa-solid fa-pencil"></i></a> <a href="/teksea/exclusoes/excluir_empresa.php?emp_id=<?= $e['emp_id'] ?>" onclick="return confirmExcluir(this)" class="btn-excluir" title="Excluir Empresa"><i class="fa-solid fa-trash"></i></a> </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr> <td colspan="7" style="text-align:center; padding: 20px;">Nenhuma empresa encontrada com os filtros aplicados.</td> </tr>
                    <?php endif; if ($stmt_data) { $stmt_data->close(); } ?>
                </tbody>
            </table>

            <?php if ($total_pages > 0): ?>
                <nav aria-label="Navegação das páginas" class="pagination-container">
                    
                    <form action="empresas.php" method="GET" class="pagination-limit-form">
                        <input type="hidden" name="busca_nome" value="<?= htmlspecialchars($busca_nome) ?>"> 
                        <input type="hidden" name="busca_tipo" value="<?= htmlspecialchars($busca_tipo) ?>">
                        <input type="hidden" name="seg_id" value="<?= htmlspecialchars($busca_segmento) ?>">
                        <select id="limit_pag" name="limit" onchange="this.form.submit()">
                            <?php foreach ($limit_options as $option): ?>
                                <option value="<?= $option ?>" <?= ($limit == $option) ? 'selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size: 14px; color: #555;"> itens por página</span>
                    </form>

                    <?php if ($total_pages > 1): ?>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li><a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&busca_nome=<?= htmlspecialchars($busca_nome) ?>&busca_tipo=<?= htmlspecialchars($busca_tipo) ?>&seg_id=<?= htmlspecialchars($busca_segmento) ?>">Anterior</a></li>
                            <?php else: ?>
                                <li class="disabled"><span>Anterior</span></li>
                            <?php endif; ?>

                            <?php
                            $range = 2; // Quantos links antes e depois da página atual
                            $show_dots = false;
                            for ($i = 1; $i <= $total_pages; $i++):
                                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                    $show_dots = true; // Reseta os "..."
                            ?>
                                <?php if ($i == $page): ?>
                                    <li class="active"><span><?= $i ?></span></li>
                                <?php else: ?>
                                    <li><a href="?page=<?= $i ?>&limit=<?= $limit ?>&busca_nome=<?= htmlspecialchars($busca_nome) ?>&busca_tipo=<?= htmlspecialchars($busca_tipo) ?>&seg_id=<?= htmlspecialchars($busca_segmento) ?>"><?= $i ?></a></li>
                                <?php endif; ?>
                            <?php 
                                // Adiciona "..." se houver um salto
                                elseif ($show_dots):
                                    $show_dots = false; // Mostra "..." apenas uma vez
                            ?>
                                <li class="disabled"><span>...</span></li>
                            <?php
                                endif;
                            endfor;
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li><a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&busca_nome=<?= htmlspecialchars($busca_nome) ?>&busca_tipo=<?= htmlspecialchars($busca_tipo) ?>&seg_id=<?= htmlspecialchars($busca_segmento) ?>">Próxima</a></li>
                            <?php else: ?>
                                <li class="disabled"><span>Próxima</span></li>
                            <?php endif; ?>
                        </ul>
                    <?php else: ?>
                        <div style="flex-grow: 1;"></div> <?php endif; ?>
                </nav>
            <?php endif; ?>
            </section>
    </main>
</div>

<script src="/js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // (A sua lógica de Máscaras e ViaCEP permanece igual)
    const cad_cep = document.getElementById("cad_cep"); 
    const cad_cnpj = document.getElementById("cad_cnpj");
    if (cad_cep) Inputmask("99999-999").mask(cad_cep); 
    if (cad_cnpj) Inputmask("99.999.999/9999-99").mask(cad_cnpj);
    if (cad_cep) { cad_cep.addEventListener('blur', function () { /* ... Lógica ViaCEP ... */ }); }

    // --- LÓGICA ISO ATUALIZADA (Existente) ---
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
                cad_inputCertificado.required = true;
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
                cad_inputDocLegais.required = true;
                cad_inputChecklist.required = true;
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
        console.error("Erro: Elementos do formulário ISO (novos ou antigos) não foram encontrados. Verifique os IDs.");
    }

    // --- LÓGICA DE TRANSPORTE (Existente) ---
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
                inputsTransporteFile.forEach(input => { if(input) input.required = true; });
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

/**
 * --- Função de confirmação de exclusão ---
 * (Tua função existente, sem alterações)
 */
function confirmExcluir(linkElement) {
    const row = linkElement.closest('tr');
    let companyName = 'esta empresa';
    if (row && row.cells.length > 0) {
        companyName = row.cells[0].textContent.trim(); 
    }
    const message = "Tem a certeza que deseja excluir a empresa '" + companyName + "'?\n\n" +
                   "!!!!!!!!!!!!!!!!!! ATENÇÃO !!!!!!!!!!!!!!!!!!\n" +
                   "Esta ação é IRREVERSÍVEL.\n\n" +
                   "Ao excluir esta empresa, TODOS os utilizadores, documentos e anexos associados a ela serão PERMANENTEMENTE apagados do sistema.";
    return confirm(message);
}
</script>
<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>