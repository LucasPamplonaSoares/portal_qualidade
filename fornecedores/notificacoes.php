<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php'; 

// 1. Segurança
if (!isset($_SESSION['logado']) || !isset($_SESSION['emp_tipo']) || $_SESSION['emp_tipo'] != 1 || !isset($_SESSION['user_id'])) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

// 2. Conexão
require_once ROOT_PATH . '/config/conexao.php';
$user_id_logado = $_SESSION['user_id'];
$notificacoes = [];
$tem_nao_lidas = false; // Variável para controlar se o botão deve aparecer

try {
    // --- MUDANÇA: O 'UPDATE' FOI REMOVIDO DAQUI ---
    // (Vamos marcá-las como lidas apenas quando o utilizador clicar no botão)

    // 4. BUSCAR TODAS AS NOTIFICAÇÕES
    $sql_list = "SELECT notificacao_id, mensagem, link, lida, data_criacao 
                 FROM notificacoes 
                 WHERE user_id_destino = ? 
                 ORDER BY data_criacao DESC
                 LIMIT 50"; 
                 
    $stmt_list = $conn->prepare($sql_list);
    if ($stmt_list) {
        $stmt_list->bind_param("i", $user_id_logado);
        $stmt_list->execute();
        $result_list = $stmt_list->get_result();
        while ($row = $result_list->fetch_assoc()) {
            if ($row['lida'] == 0) {
                $tem_nao_lidas = true; // Encontrámos pelo menos uma não lida
            }
            $notificacoes[] = $row; 
        }
        $stmt_list->close();
    }

} catch (Exception $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Notificações - Portal TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    <style>
        /* (Teus estilos .notificacao-item, .data, etc. mantidos) */
         .notificacao-pagina-lista { list-style: none; padding: 0; margin: 0; }
        .notificacao-item { border-bottom: 1px solid #eee; }
        .notificacao-item a {
            display: block; padding: 15px 20px;
            text-decoration: none; color: #333;
            transition: background-color 0.2s ease;
        }
        
        /* --- MUDANÇA: Estilo para NOVAS notificações --- */
        /* (As que têm lida = 0) */
        .notificacao-item.nova a {
            background-color: #f8faff; /* Fundo azul claro */
            font-weight: bold;
            color: #0056b3;
        }
        
        /* Estilo para Lidas (Mantido) */
        .notificacao-item.lida a { color: #777; }
        .notificacao-item a:hover { background-color: #f0f0f0; }
        .notificacao-item a .data {
            display: block; font-size: 0.85em;
            font-weight: normal; color: #999;
            margin-top: 5px;
        }
        .nenhuma-notificacao {
            padding: 30px; text-align: center;
            color: #888; font-style: italic;
        }
        
        /* --- MUDANÇA: Estilo para o cabeçalho com o botão --- */
        .header-com-botao {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-marcar-lidas {
            background-color: var(--cor-verde-claro, #e8f5e9);
            color: var(--cor-verde, #385856);
            border: 1px solid var(--cor-verde, #385856);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-marcar-lidas:hover {
            background-color: var(--cor-verde, #385856);
            color: white;
        }
        .btn-marcar-lidas:disabled {
             background-color: #eee;
             color: #aaa;
             border-color: #ccc;
             cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php require_once __DIR__ . '/navbar.php'; ?>
    </nav>

    <main class="content">
        <header class="header-com-botao">
            <h1>Minhas Notificações</h1>
            <?php if ($tem_nao_lidas): // Só mostra o botão se houver algo para marcar ?>
                <button class="btn-marcar-lidas" id="btnMarcarLidas">
                    <i class="fa-solid fa-check-double"></i> Marcar todas como lidas
                </button>
            <?php endif; ?>
        </header>

        <section class="card">
            <ul class="notificacao-pagina-lista" id="listaNotificacoes">
                
                <?php if (empty($notificacoes)): ?>
                    <li class="nenhuma-notificacao">Nenhuma notificação encontrada.</li>
                
                <?php else: ?>
                    <?php foreach ($notificacoes as $not): ?>
                        
                        <li class="notificacao-item <?= $not['lida'] ? 'lida' : 'nova' ?>" 
                            id="not-<?= $not['notificacao_id'] ?>"> 
                            
                            <a href="<?= htmlspecialchars($not['link'] ?? '#') ?>">
                                <?= htmlspecialchars($not['mensagem']) ?>
                                <span class="data">
                                    <?= date('d/m/Y H:i', strtotime($not['data_criacao'])) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </ul>
        </section>
    </main>
</div>

<?php if (isset($conn)) { $conn->close(); } ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnMarcarLidas = document.getElementById('btnMarcarLidas');
    
    if (btnMarcarLidas) {
        btnMarcarLidas.addEventListener('click', async function() {
            
            btnMarcarLidas.disabled = true;
            btnMarcarLidas.textContent = 'A processar...';

            try {
                // (Este é o ficheiro que te enviei na mensagem anterior)
                const response = await fetch('<?= APP_ROOT ?>fornecedores/api_marcar_lidas.php', {
                    method: 'POST'
                });
                
                if (response.ok) {
                    btnMarcarLidas.remove();
                    
                    const itensNovos = document.querySelectorAll('.notificacao-item.nova');
                    itensNovos.forEach(item => {
                        item.classList.remove('nova');
                        item.classList.add('lida');
                    });
                    
                    const badge = document.getElementById('notificacao-badge');
                    if (badge) {
                        badge.textContent = '';
                    }
                    
                } else {
                    alert('Erro ao marcar as notificações como lidas.');
                    btnMarcarLidas.disabled = false;
                    btnMarcarLidas.textContent = 'Marcar todas como lidas';
                }
            } catch (error) {
                console.error('Erro ao marcar como lidas:', error);
                btnMarcarLidas.disabled = false;
                btnMarcarLidas.textContent = 'Marcar todas como lidas';
            }
        });
    }
});
</script>
</body>
</html>