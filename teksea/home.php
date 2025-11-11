<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';

// 1. Segurança: Verifica se é ADMIN (emp_id 11)
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

// 2. Conexão
require_once ROOT_PATH . '/config/conexao.php';

// --- Consultas para os Cards de Estatísticas (O seu código original, mantido) ---

// 1. Contagem de Usuários
$total_usuarios = 0;
$sql_count_users = "SELECT COUNT(*) as total FROM usuario";
$result_count_users = $conn->query($sql_count_users);
if ($result_count_users) {
    $total_usuarios = $result_count_users->fetch_assoc()['total'] ?? 0;
}

// 2. Contagem de Empresas (Total e por Tipo)
$total_empresas = 0;
$empresas_por_tipo = [
    'Fornecedores' => 0,
    'Terceiros' => 0,
    'Clientes' => 0
];
$sql_count_empresas = "SELECT emp_tipo, COUNT(*) as total FROM empresa GROUP BY emp_tipo";
$result_count_empresas = $conn->query($sql_count_empresas);
if ($result_count_empresas) {
    while ($row = $result_count_empresas->fetch_assoc()) {
        $total_empresas += $row['total'];
        if ($row['emp_tipo'] == 1) $empresas_por_tipo['Fornecedores'] = $row['total'];
        if ($row['emp_tipo'] == 2) $empresas_por_tipo['Terceiros'] = $row['total'];
        if ($row['emp_tipo'] == 3) $empresas_por_tipo['Clientes'] = $row['total'];
    }
}

// 3. Contagem de Documentos (Total e por Status)
$total_documentos = 0;
$documentos_por_status = [
    'Pendente' => 0,
    'Aguardando' => 0,
    'Aprovado' => 0,
    'Recusado' => 0
];
$sql_count_docs = "SELECT anx_status, COUNT(*) as total FROM anexo GROUP BY anx_status";
$result_count_docs = $conn->query($sql_count_docs);
if ($result_count_docs) {
     while ($row = $result_count_docs->fetch_assoc()) {
        $total_documentos += $row['total'];
        if ($row['anx_status'] == 0) $documentos_por_status['Pendente'] = $row['total'];
        if ($row['anx_status'] == 1) $documentos_por_status['Aguardando'] = $row['total'];
        if ($row['anx_status'] == 2) $documentos_por_status['Aprovado'] = $row['total'];
        if ($row['anx_status'] == 3) $documentos_por_status['Recusado'] = $row['total'];
    }
}
// --- FIM DAS Consultas para os Cards ---


// --- NOVO: LÓGICA DE PAGINAÇÃO PARA TABELA DE EMPRESAS ---

// 1. Definir quantos itens por página
$itens_por_pagina = 10; // Como pediu

// 2. Obter a página atual (da URL, ex: ?pagina=2)
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) {
    $pagina_atual = 1;
}

// 3. Calcular o OFFSET (ponto de partida) para o SQL
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// 4. Contar o TOTAL de empresas para calcular o número de páginas
$sql_total_empresas_tabela = "SELECT COUNT(*) as total FROM empresa";
$result_total = $conn->query($sql_total_empresas_tabela);
$total_empresas_tabela = 0;
if ($result_total) {
    $total_empresas_tabela = $result_total->fetch_assoc()['total'] ?? 0;
}

// 5. Calcular o total de páginas
$total_paginas = ceil($total_empresas_tabela / $itens_por_pagina);
// --- FIM DA LÓGICA DE PAGINAÇÃO ---


// 4. Busca de empresas para a tabela principal (AGORA COM PAGINAÇÃO)
$empresas = [];
// --- SQL MODIFICADO: Removemos o LIMIT 10 fixo e adicionamos LIMIT ? OFFSET ? ---
$sql_empresas_tabela = "SELECT emp_razao_social, emp_cnpj, data_criacao
                        FROM empresa
                        ORDER BY data_criacao DESC
                        LIMIT ? OFFSET ?"; // <-- NOVO

// Usamos "prepared statements" para segurança
$stmt = $conn->prepare($sql_empresas_tabela);
if (!$stmt) {
     die("Erro na preparação da consulta de empresas: " . $conn->error);
}

// "ii" significa que estamos a passar dois Inteiros (integer)
$stmt->bind_param("ii", $itens_por_pagina, $offset);
$stmt->execute();
$result_empresas_tabela = $stmt->get_result();

if (!$result_empresas_tabela) {
    die("Erro ao buscar empresas para tabela: " . $stmt->error);
}

if ($result_empresas_tabela->num_rows > 0) {
    while ($row = $result_empresas_tabela->fetch_assoc()) {
        $empresas[] = $row; // Usa a mesma variável $empresas
    }
}
$stmt->close(); // Fechar o statement


// PARTE 2: APRESENTAÇÃO (HTML)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal TekSea - Admin Home</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>

    <style>
        /* Estilos do submenu (seus estilos) */
        .submenu { display: none; }
        .submenu.active { display: block; }
        .arrow { transition: transform 0.3s ease; margin-left: auto; } /* Adicionado margin-left auto */
        .arrow.active { transform: rotate(180deg); }

        /* --- ESTILOS PARA OS CARDS DE ESTATÍSTICAS (Seus estilos) --- */
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; padding: 20px 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); text-align: center; border-left: 5px solid var(--cor-verde, #385856); }
        .stat-card h4 { margin: 0 0 10px 0; font-size: 0.9em; color: #666; text-transform: uppercase; font-weight: 500; }
        .stat-card .stat-number { font-size: 2.2em; font-weight: 600; color: var(--cor-verde, #385856); margin-bottom: 5px; line-height: 1.2; }
        .stat-card .stat-details { font-size: 0.85em; color: #555; margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; text-align: left; }
        .stat-card .stat-details p { margin: 3px 0; display: flex; justify-content: space-between; }
        .stat-card .stat-details span { font-weight: 600; }
        .stat-card.blue-border { border-left-color: #0275d8; } .stat-card.blue-border .stat-number { color: #0275d8; }
        .stat-card.orange-border { border-left-color: #f0ad4e; } .stat-card.orange-border .stat-number { color: #f0ad4e; }
        .stat-card.green-border { border-left-color: #5cb85c; } .stat-card.green-border .stat-number { color: #5cb85c; }

        /* --- ESTILOS PARA ORGANIZAR A TABELA (Seus estilos) --- */
        .table-section table { width: 100%; border-collapse: collapse; table-layout: auto; }
        .table-section table th,
        .table-section table td { padding: 14px 12px; vertical-align: middle; border-bottom: 1px solid #eee; text-align: left; white-space: nowrap; }
        .table-section table th { font-weight: 600; color: #333; background-color: #f8f9fa; }
        .table-section table th:nth-child(1), .table-section table td:nth-child(1) { min-width: 180px; } /* CNPJ */
        .table-section table th:nth-child(2), .table-section table td:nth-child(2) { white-space: normal; min-width: 300px; } /* Razão Social */
        .table-section table th:nth-child(3), .table-section table td:nth-child(3) { min-width: 160px; text-align: center; } /* Data Criação */
        .table-section table tbody tr:nth-of-type(even) { background-color: #f9f9f9; }

        /* --- NOVO: ESTILOS PARA PAGINAÇÃO --- */
        .pagination-container {
            padding: 20px 0 10px 0;
            text-align: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
        }
        .pagination-link {
            text-decoration: none;
            color: var(--cor-verde, #385856);
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .pagination-link:hover {
            background-color: #f0f0f0;
        }
        .pagination-info {
            font-size: 0.9em;
            color: #555;
        }
        /* Se não houver link, fica invisível mas ocupa espaço */
        .pagination-link.disabled {
            visibility: hidden;
        }

    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once ROOT_PATH . '/teksea/navbar.php'; ?>
    </nav>

    <main class="content">
        <header>
            <h1>Bem-vindo ao Portal TekSea, <?= htmlspecialchars($_SESSION['user_nome']); ?>!</h1>
        </header>

        <?php /* ... */ ?>

        <section class="stats-container">
            <div class="stat-card blue-border">
                <h4>Total de Usuários</h4>
                <div class="stat-number"><?= $total_usuarios ?></div>
            </div>
            <div class="stat-card">
                <h4>Total de Empresas</h4>
                <div class="stat-number"><?= $total_empresas ?></div>
                <div class="stat-details">
                    <p>Fornecedores: <span><?= $empresas_por_tipo['Fornecedores'] ?></span></p>
                    <p>Terceiros: <span><?= $empresas_por_tipo['Terceiros'] ?></span></p>
                    <p>Clientes: <span><?= $empresas_por_tipo['Clientes'] ?></span></p>
                </div>
            </div>
            <div class="stat-card orange-border">
                <h4>Total de Documentos</h4>
                <div class="stat-number"><?= $total_documentos ?></div>
                 <div class="stat-details">
                    <p>Pendentes: <span><?= $documentos_por_status['Pendente'] ?></span></p>
                    <p>Aguardando: <span><?= $documentos_por_status['Aguardando'] ?></span></p>
                    <p>Aprovados: <span><?= $documentos_por_status['Aprovado'] ?></span></p>
                    <p>Recusados: <span><?= $documentos_por_status['Recusado'] ?></span></p>
                </div>
            </div>
        </section>
        <section class="card table-section">
            
            <h3>Gerenciamento de Empresas (<?= htmlspecialchars($total_empresas_tabela) ?> cadastradas)</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>CNPJ</th>
                        <th>Razão Social</th>
                        <th>Data de Criação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($empresas)): ?>
                        <?php foreach ($empresas as $empresa): ?>
                            <tr>
                                <td><?= htmlspecialchars($empresa['emp_cnpj'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($empresa['emp_razao_social'] ?? 'N/A'); ?></td>
                                <td style="text-align: center;"> <?php echo !empty($empresa['data_criacao']) ? date('d/m/Y H:i', strtotime($empresa['data_criacao'])) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align: center; padding: 20px;">Nenhuma empresa encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_paginas > 1): // Só mostra a paginação se houver mais de 1 página ?>
            <div class="pagination-container">
                
                <?php if ($pagina_atual > 1): ?>
                    <a href="?pagina=<?= $pagina_atual - 1 ?>" class="pagination-link">&laquo; Anterior</a>
                <?php else: ?>
                    <span class="pagination-link disabled">&laquo; Anterior</span>
                <?php endif; ?>

                <span class="pagination-info">
                    Página <?= $pagina_atual ?> de <?= $total_paginas ?>
                </span>

                <?php if ($pagina_atual < $total_paginas): ?>
                    <a href="?pagina=<?= $pagina_atual + 1 ?>" class="pagination-link">Próxima &raquo;</a>
                <?php else: ?>
                    <span class="pagination-link disabled">Próxima &raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            </section>
    </main>
</div>

<?php
if (isset($conn)) { $conn->close(); }
?>
</body>
</html>