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

// --- Buscar Segmentos (Ainda necessário para o filtro de BUSCA) ---
$segmentos = [];
$sql_segmentos = "SELECT seg_id, seg_nome FROM segmento ORDER BY seg_nome ASC";
$result_segmentos = $conn->query($sql_segmentos);
if ($result_segmentos && $result_segmentos->num_rows > 0) {
    while ($row_seg = $result_segmentos->fetch_assoc()) {
        $segmentos[] = $row_seg;
    }
}

// --- Lógica de Paginação e Busca (Tudo igual) ---
$limit_options = [10, 20, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$busca_nome = trim($_GET['busca_nome'] ?? '');
$busca_tipo = trim($_GET['busca_tipo'] ?? '');
$busca_segmento = trim($_GET['seg_id'] ?? ''); 

// (O resto da tua lógica PHP de WHERE, COUNT, etc., permanece igual)
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

// --- Contagem Total de Itens ---
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

// --- Cálculos de Paginação ---
$total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// ---
// MUDANÇA: Adicionado 'hom.hom_id' ao SELECT e o 'LEFT JOIN' para a tabela de homologação
// ---
$sql_data = "SELECT emp.emp_id, emp.emp_nome_fantasia, emp.emp_cnpj, emp.emp_tipo, emp.data_criacao, emp.emp_possui_iso, seg.seg_nome, hom.hom_id "
         . $sql_base // "FROM empresa emp"
         . " LEFT JOIN segmento seg ON emp.seg_id = seg.seg_id " // JOIN
         . " LEFT JOIN empresa_homologacao_transporte hom ON emp.emp_id = hom.emp_id " // NOVO JOIN
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
/* --- CSS para o formulário "Buscar Empresas" --- */
.search-form-card { background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0px 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
.search-form-card h2 { margin-bottom: 15px; font-size: 1.1em; }
.search-form-fields { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
.search-form-fields .campo { flex: 1; min-width: 180px; }
.search-form-fields .campo label { display: block; font-size: 14px; margin-bottom: 6px; color: var(--cor-preto, #212322); font-weight: 500; }
.search-form-fields input[type="text"], 
.search-form-fields select { width: 100%; padding: 10px 12px; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 6px; font-size: 15px; height: 41px; box-sizing: border-box; }
.search-form-fields button { padding: 0 20px; background-color: var(--cor-verde, #385856); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; height: 41px; line-height: 41px; }
.search-form-fields button:hover { background-color: var(--cor-verde-hover, #2c4543); }
.search-form-fields .campo-botao { flex-shrink: 0; }

/* --- CSS PARA PAGINAÇÃO --- */
.pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; margin-top: 20px; border-top: 1px solid var(--cor-cinza-claro, #eee); flex-wrap: wrap; }
.pagination-limit-form { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.pagination-limit-form select { padding: 8px 10px; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 4px; font-size: 14px; height: 38px; }
.pagination { display: flex; justify-content: center; align-items: center; list-style: none; padding: 0; margin: 0; flex-grow: 1; margin-bottom: 10px; }
.pagination li { margin: 0 4px; }
.pagination a, .pagination span { display: inline-block; padding: 8px 12px; text-decoration: none; color: var(--cor-verde, #385856); background-color: #fff; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 4px; transition: all 0.2s ease; font-size: 14px; }
.pagination a:hover { background-color: var(--cor-cinza-claro, #eee); border-color: #ccc; }
.pagination .active span { background-color: var(--cor-verde, #385856); color: white; border-color: var(--cor-verde, #385856); font-weight: bold; }
.pagination .disabled span { color: #aaa; background-color: #f9f9f9; border-color: var(--cor-cinza-borda, #ddd); cursor: default; }

/* --- MUDANÇA: Estilo para o novo botão de cadastro --- */
.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px; /* Adiciona espaço antes da próxima secção */
}
.btn-primary {
    background-color: var(--cor-verde, #385856);
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
    font-size: 15px;
}
.btn-primary:hover {
    background-color: var(--cor-verde-hover, #2c4543);
}
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
        
        <header class="header-actions">
            <h1>Cadastros Empresariais</h1>
            <a href="nova_empresa.php" class="btn-primary">➕ Cadastrar Nova Empresa</a>
        </header>

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
                        <th>Homologação</th>
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
                        
                        <td>
                            <?php
                            $isTransporte = (stripos($e['seg_nome'] ?? '', 'Transporte') !== false);
                            if ($isTransporte) {
                                if (!empty($e['hom_id'])) {
                                    echo '<span style="color:green; font-weight:bold;">✅ Completo</span>';
                                } else {
                                    echo '<span style="color:orange; font-weight:bold;">⚠️ Pendente</span>';
                                }
                            } else {
                                echo '<span style="color:#999;">N/A</span>';
                            }
                            ?>
                        </td>

                        <td class="actions-cell"> <a href="editar_empresa.php?emp_id=<?= $e['emp_id'] ?>" class="btn-editar" title="Editar Empresa"><i class="fa-solid fa-pencil"></i></a> <a href="/teksea/exclusoes/excluir_empresa.php?emp_id=<?= $e['emp_id'] ?>" onclick="return confirmExcluir(this)" class="btn-excluir" title="Excluir Empresa"><i class="fa-solid fa-trash"></i></a> </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr> <td colspan="8" style="text-align:center; padding: 20px;">Nenhuma empresa encontrada com os filtros aplicados.</td> </tr>
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
                            $range = 2; 
                            $show_dots = false;
                            for ($i = 1; $i <= $total_pages; $i++):
                                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                    $show_dots = true;
                            ?>
                                <?php if ($i == $page): ?>
                                    <li class="active"><span><?= $i ?></span></li>
                                <?php else: ?>
                                    <li><a href="?page=<?= $i ?>&limit=<?= $limit ?>&busca_nome=<?= htmlspecialchars($busca_nome) ?>&busca_tipo=<?= htmlspecialchars($busca_tipo) ?>&seg_id=<?= htmlspecialchars($busca_segmento) ?>"><?= $i ?></a></li>
                                <?php endif; ?>
                            <?php 
                                elseif ($show_dots):
                                    $show_dots = false;
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
                        <div style="flex-grow: 1;"></div> 
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>
</div>

<script src="/js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // ---
    // MUDANÇA: Todo o JavaScript do formulário de cadastro (ISO, Transporte, ViaCEP) foi REMOVIDO daqui.
    // ---

    // A única função necessária nesta página é a de exclusão.
});

/**
 * --- Função de confirmação de exclusão ---
 * (Permanece igual)
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