<?php
// PARTE 1: LÓGICA, SEGURANÇA E BUSCA DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Segurança
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: /login/");
    exit;
}

// 2. Conexão
require_once __DIR__ . '/../../config/conexao.php';

// 3. Busca das empresas para os dropdowns (Cadastro e Pesquisa)
$empresas_dropdown = []; // Renomeado para clareza
$sql_empresas_dd = "SELECT emp_id, emp_razao_social FROM empresa ORDER BY emp_razao_social ASC";
$result_empresas_dd = $conn->query($sql_empresas_dd);
if ($result_empresas_dd && $result_empresas_dd->num_rows > 0) {
    $empresas_dropdown = $result_empresas_dd->fetch_all(MYSQLI_ASSOC);
}

// --- PAGINAÇÃO E PESQUISA: Parâmetros ---
$limit_options = [10, 20, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$busca_nome_usr = trim($_GET['busca_nome_usr'] ?? ''); // Nome diferente para evitar conflito com empresas.php
$busca_empresa_id = trim($_GET['busca_empresa_id'] ?? ''); // Busca por ID da empresa

// --- PAGINAÇÃO E PESQUISA: Construção da Consulta Base e WHERE ---
$sql_base = "FROM usuario AS u LEFT JOIN empresa AS e ON u.emp_id = e.emp_id"; // JOIN necessário
$where_clauses = [];
$params_where = []; // Parâmetros apenas para o WHERE
$types_where = ""; // Tipos apenas para o WHERE

// Filtro por nome de usuário (nome OU sobrenome)
if (!empty($busca_nome_usr)) {
    // Usamos CONCAT para buscar em nome completo, ou buscar separadamente
    // Buscar separadamente pode ser mais performático em tabelas grandes se houver índices
    $where_clauses[] = "(u.user_nome LIKE ? OR u.user_sobrenome LIKE ?)";
    $params_where[] = "%" . $busca_nome_usr . "%";
    $params_where[] = "%" . $busca_nome_usr . "%";
    $types_where .= "ss";
}
// Filtro por ID da empresa
if ($busca_empresa_id !== '' && is_numeric($busca_empresa_id)) {
    $where_clauses[] = "u.emp_id = ?";
    $params_where[] = (int)$busca_empresa_id;
    $types_where .= "i";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// --- PAGINAÇÃO: Contagem Total de Itens ---
$total_items = 0; // Inicializa a variável
$sql_count = "SELECT COUNT(u.user_id) as total " . $sql_base . $where_sql; // Conta user_id
$stmt_count = $conn->prepare($sql_count);
if (!$stmt_count) { die("Erro ao preparar contagem de usuários: " . $conn->error); }
if (!empty($params_where)) { // Usa os parâmetros do WHERE
    $stmt_count->bind_param($types_where, ...$params_where);
}
if (!$stmt_count->execute()){
     error_log("Erro ao executar contagem de usuários: " . $stmt_count->error);
} else {
    $result_count = $stmt_count->get_result();
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        if ($row_count) {
             $total_items = (int)$row_count['total']; // Converte para int
        }
    }
}
$stmt_count->close();

// --- PAGINAÇÃO: Cálculos ---
$total_pages = ($limit > 0 && $total_items > 0) ? ceil($total_items / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// --- PAGINAÇÃO E PESQUISA: Consulta Principal ---
$sql_data = "SELECT u.user_id, u.user_nome, u.user_sobrenome, u.user_email, e.emp_nome_fantasia "
          . $sql_base . $where_sql
          . " ORDER BY u.user_nome ASC, u.user_sobrenome ASC LIMIT ? OFFSET ?"; // Ordena por nome e sobrenome

$params_data = $params_where; // Copia os parâmetros de busca
$params_data[] = $limit;
$params_data[] = $offset;
$types_data = $types_where . "ii"; // Adiciona 'ii' para LIMIT e OFFSET

$stmt_data = $conn->prepare($sql_data);
if (!$stmt_data) { die("Erro ao preparar consulta de usuários: " . $conn->error); }
// Faz o bind SEMPRE por causa do LIMIT/OFFSET
$stmt_data->bind_param($types_data, ...$params_data);

$stmt_data->execute();
$result_usuarios = $stmt_data->get_result(); // Resultado final para a tabela
if (!$result_usuarios) { die("Erro ao executar consulta de usuários: " . $stmt_data->error); }

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestão de Usuários - Portal TekSea</title>
    <link rel="stylesheet" href="/css/styles.css"> <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>

    <style>
        /* --- ESTILOS FORMULÁRIO DE PESQUISA (copiado de empresas.php) --- */
        .search-form-card { background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0px 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .search-form-card h2 { margin-bottom: 15px; font-size: 1.1em; }
        .search-form-fields { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .search-form-fields .campo { flex: 1; min-width: 180px; }
        .search-form-fields .campo label { display: block; font-size: 14px; margin-bottom: 6px; color: var(--cor-preto, #212322); font-weight: 500; }
        .search-form-fields input[type="text"], .search-form-fields select { width: 100%; padding: 10px 12px; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 6px; font-size: 15px; height: 41px; box-sizing: border-box; }
        .search-form-fields button { padding: 0 20px; background-color: var(--cor-verde, #385856); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; height: 41px; line-height: 41px; }
        .search-form-fields button:hover { background-color: var(--cor-verde-hover, #2c4543); }
        .search-form-fields .campo-botao { flex-shrink: 0; }

        /* --- ESTILOS BOTÕES DE AÇÃO NA TABELA (adaptado para .tabela-usuarios) --- */
        .tabela-usuarios .actions-cell a { display: inline-flex !important; align-items: center !important; justify-content: center !important; text-decoration: none !important; width: 36px !important; height: 36px !important; border-radius: 5px !important; margin-left: 6px !important; font-size: 16px !important; color: white !important; transition: all 0.2s ease !important; border: none !important; cursor: pointer !important; vertical-align: middle; }
        .tabela-usuarios .actions-cell a.btn-editar { background-color: #ffc107 !important; color: #333 !important; }
        .tabela-usuarios .actions-cell a.btn-editar:hover { background-color: #e0a800 !important; }
        .tabela-usuarios .actions-cell a.btn-excluir { background-color: #d9534f !important; color: white !important; padding: 0 !important; margin-right: 0 !important; }
        .tabela-usuarios .actions-cell a.btn-excluir:hover { background-color: #c9302c !important; }
        .tabela-usuarios .actions-cell { text-align: right !important; white-space: nowrap !important; width: auto !important; min-width: 90px !important; vertical-align: middle !important; }

        /* --- ESTILOS PARA PAGINAÇÃO (copiado de empresas.php) --- */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; margin-top: 20px; border-top: 1px solid var(--cor-cinza-claro, #eee); flex-wrap: wrap; }
        .pagination-limit-form { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .pagination-limit-form select { padding: 8px 10px; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 4px; font-size: 14px; height: 38px; }
        .pagination { display: flex; justify-content: center; align-items: center; list-style: none; padding: 0; margin: 0; flex-grow: 1; margin-bottom: 10px; }
        .pagination li { margin: 0 4px; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; text-decoration: none; color: var(--cor-verde, #385856); background-color: #fff; border: 1px solid var(--cor-cinza-borda, #ddd); border-radius: 4px; transition: all 0.2s ease; font-size: 14px; }
        .pagination a:hover { background-color: var(--cor-cinza-claro, #eee); border-color: #ccc; }
        .pagination .active span { background-color: var(--cor-verde, #385856); color: white; border-color: var(--cor-verde, #385856); font-weight: bold; }
        .pagination .disabled span { color: #aaa; background-color: #f9f9f9; border-color: var(--cor-cinza-borda, #ddd); cursor: default; }

        /* Ajuste geral do padding da tabela */
         .table-section table th,
         .table-section table td {
             padding-top: 12px;
             padding-bottom: 12px;
             vertical-align: middle;
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
            <header><h1>Gestão de Usuários</h1></header>

            <section class="form-card">
                <h2>Cadastro de Novo Usuário</h2>
                <form action="processa_usuario.php" method="POST" id="form-cadastro-usuario">
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="cad_nome">Nome</label>
                            <input type="text" id="cad_nome" name="nome" required>
                        </div>
                        <div class="campo">
                            <label for="cad_sobrenome">Sobrenome</label>
                            <input type="text" id="cad_sobrenome" name="sobrenome" required>
                        </div>
                    </div>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="cad_emp_id">Empresa</label>
                            <select id="cad_emp_id" name="emp_id" required>
                                <option value="" disabled selected>Selecione uma empresa</option>
                                <?php
                                if (!empty($empresas_dropdown)):
                                    foreach ($empresas_dropdown as $empresa):
                                        echo "<option value='" . htmlspecialchars($empresa['emp_id']) . "'>"
                                             . htmlspecialchars($empresa['emp_razao_social']) . "</option>";
                                    endforeach;
                                else: echo "<option value='' disabled>Nenhuma empresa</option>"; endif;
                                ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="cad_email">E-mail</label>
                            <input type="email" id="cad_email" name="email" required>
                        </div>
                    </div>
                    <div class="input-group two-label-input">
                        <div class="campo">
                            <label for="cad_senha">Senha</label>
                            <input type="password" id="cad_senha" name="senha" required>
                        </div>
                        <div class="campo">
                            <label for="cad_confirmar_senha">Confirmar Senha</label>
                            <input type="password" id="cad_confirmar_senha" name="confirmar-senha" required>
                        </div>
                    </div>
                    <button type="submit" class="btn">Cadastrar</button>
                </form>
            </section>
            <hr>

            <section class="search-form-card">
                <h2>Buscar Usuários</h2>
                <form action="usuarios.php" method="GET" class="search-form-fields">
                    <div class="campo">
                        <label for="busca_nome_usr">Nome do Usuário</label>
                        <input type="text" id="busca_nome_usr" name="busca_nome_usr" value="<?= htmlspecialchars($busca_nome_usr) ?>" placeholder="Digite nome ou sobrenome...">
                    </div>
                    <div class="campo">
                        <label for="busca_empresa_id">Empresa</label>
                        <select id="busca_empresa_id" name="busca_empresa_id">
                            <option value="">Todas as Empresas</option>
                            <?php foreach ($empresas_dropdown as $empresa): ?>
                                <option value="<?= $empresa['emp_id'] ?>" <?= ($busca_empresa_id !== '' && $busca_empresa_id == $empresa['emp_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($empresa['emp_razao_social']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo-botao">
                        <button type="submit">Buscar</button>
                    </div>
                    <input type="hidden" name="limit" value="<?= $limit ?>">
                </form>
            </section>

            <section class="usuarios-lista card table-section">
                <h2>
                    Usuários Cadastrados
                    <?php if ($total_items > 0): ?>
                        <span style="font-weight: normal; font-size: 0.8em;">(Exibindo <?= $result_usuarios->num_rows ?> de <?= $total_items ?> - Página <?= $page ?> de <?= $total_pages ?>)</span>
                    <?php endif; ?>
                </h2>
                <table class="tabela-usuarios">
                    <thead>
                        <tr>
                            <th>Nome do Usuário</th>
                            <th>E-mail</th>
                            <th>Empresa</th>
                            <th class="actions-cell">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_usuarios->num_rows > 0): ?>
                            <?php while ($usuario = $result_usuarios->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($usuario['user_nome'] . ' ' . $usuario['user_sobrenome']) ?></td>
                                <td><?= htmlspecialchars($usuario['user_email']) ?></td>
                                <td><?= htmlspecialchars($usuario['emp_nome_fantasia'] ?? 'N/A') ?></td>
                                <td class="actions-cell">
                                    <a href="editar_usuario.php?user_id=<?= $usuario['user_id'] ?>" class="btn-editar" title="Editar Usuário"><i class="fa-solid fa-pencil"></i></a>
                                    <a href="/teksea/exclusoes/excluir_usuario.php?user_id=<?= $usuario['user_id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este usuário?')" class="btn-excluir" title="Excluir Usuário"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr> <td colspan="4" style="text-align:center; padding: 20px;">Nenhum usuário encontrado com os filtros aplicados.</td> </tr>
                        <?php endif; ?>
                        <?php $stmt_data->close(); ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 0): ?>
                    <nav aria-label="Navegação das páginas" class="pagination-container">
                        <form action="usuarios.php" method="GET" class="pagination-limit-form">
                            <input type="hidden" name="busca_nome_usr" value="<?= htmlspecialchars($busca_nome_usr) ?>">
                            <input type="hidden" name="busca_empresa_id" value="<?= htmlspecialchars($busca_empresa_id) ?>">
                            <select id="limit_pag" name="limit" onchange="this.form.submit()">
                                <?php foreach ($limit_options as $option): ?>
                                    <option value="<?= $option ?>" <?= ($limit == $option) ? 'selected' : '' ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span style="font-size: 14px; color: #555;"> itens por página</span>
                        </form>
                        <?php if ($total_pages > 1): ?>
                            <ul class="pagination">
                                <?php
                                $query_params = ['limit' => $limit, 'busca_nome_usr' => $busca_nome_usr, 'busca_empresa_id' => $busca_empresa_id];
                                // Botão Anterior
                                if ($page > 1): $prev_page_params = $query_params + ['page' => $page - 1]; ?>
                                <li class="page-item"><a class="page-link" href="?<?= http_build_query($prev_page_params) ?>">Anterior</a></li>
                                <?php else: ?> <li class="page-item disabled"><span class="page-link">Anterior</span></li> <?php endif; ?>
                                <?php // Links das Páginas (com range)
                                $range = 2; $start = max(1, $page - $range); $end = min($total_pages, $page + $range);
                                if ($start > 1) { $first_page_params = $query_params + ['page' => 1]; echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($first_page_params) . '">1</a></li>'; if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } }
                                for ($i = $start; $i <= $end; $i++): $current_page_params = $query_params + ['page' => $i]; ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <?php if ($i == $page): ?> <span class="page-link"><?= $i ?></span>
                                    <?php else: ?> <a class="page-link" href="?<?= http_build_query($current_page_params) ?>"><?= $i ?></a> <?php endif; ?>
                                    </li>
                                <?php endfor;
                                if ($end < $total_pages) { if ($end < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } $last_page_params = $query_params + ['page' => $total_pages]; echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($last_page_params) . '">' . $total_pages . '</a></li>'; }
                                // Botão Próximo
                                if ($page < $total_pages): $next_page_params = $query_params + ['page' => $page + 1]; ?>
                                <li class="page-item"><a class="page-link" href="?<?= http_build_query($next_page_params) ?>">Próximo</a></li>
                                <?php else: ?> <li class="page-item disabled"><span class="page-link">Próximo</span></li> <?php endif; ?>
                            </ul>
                        <?php else: ?> <div style="flex-grow: 1;"></div> <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="/js/script.js"></script>
    <script>
        // JS específico para validação de senha no cadastro
        const cad_senha = document.getElementById('cad_senha');
        const cad_confirmar_senha = document.getElementById('cad_confirmar_senha');
        const form_cadastro = document.getElementById('form-cadastro-usuario');

        if (form_cadastro && cad_senha && cad_confirmar_senha) {
            form_cadastro.addEventListener('submit', function(event) {
                if (cad_senha.value !== cad_confirmar_senha.value) {
                    alert('As senhas digitadas no cadastro não coincidem!');
                    event.preventDefault(); // Impede o envio
                    cad_confirmar_senha.focus();
                }
            });
        }

         // JS para confirmação de exclusão (se não estiver no script.js global)
         function confirmExcluirUsuario(link) {
            if (confirm('Tem certeza que deseja excluir este usuário?')) {
                return true;
            }
            return false;
         }
         // Certifique-se que o link de excluir chama a função correta:
         // onclick="return confirmExcluirUsuario(this)"

    </script>
    <?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>