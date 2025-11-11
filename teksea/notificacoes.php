<?php
// PARTE 1: LÓGICA E PROCESSAMENTO DE DADOS
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';

// 1. Segurança: Verifica se é ADMIN (emp_id 11) e se temos o user_id
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11 || !isset($_SESSION['user_id'])) {
    header("Location: " . APP_ROOT . "login/");
    exit;
}

// 2. Conexão
// Temos de incluir a conexão ANTES do navbar
require_once ROOT_PATH . '/config/conexao.php';

$user_id_atual = $_SESSION['user_id'];
$notificacoes = [];

// --- Inicia o Try/Catch para buscar e atualizar ---
try {
    
    // --- PASSO 1: BUSCAR A LISTA DE NOTIFICAÇÕES (ANTES DE MARCAR COMO LIDA) ---
    // Buscamos as 100 mais recentes
    $sql_fetch = "SELECT notif_id, mensagem, link, lida, data_criacao 
                  FROM notificacoes 
                  WHERE user_id_destino = ? 
                  ORDER BY data_criacao DESC
                  LIMIT 100";
                  
    $stmt_fetch = $conn->prepare($sql_fetch);
    if (!$stmt_fetch) {
        throw new Exception("Erro ao preparar consulta de notificações: " . $conn->error);
    }
    
    $stmt_fetch->bind_param("i", $user_id_atual);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    if ($result_fetch) {
        while ($row = $result_fetch->fetch_assoc()) {
            $notificacoes[] = $row;
        }
    }
    $stmt_fetch->close();

    // --- PASSO 2: MARCAR COMO LIDAS (DEPOIS DE BUSCAR) ---
    // Agora que já temos a lista (com o status "lida=0"),
    // podemos atualizar o banco para limpar o contador do navbar.
    
    $sql_update = "UPDATE notificacoes SET lida = 1 WHERE user_id_destino = ? AND lida = 0";
    $stmt_update = $conn->prepare($sql_update);
    
    if ($stmt_update) {
        $stmt_update->bind_param("i", $user_id_atual);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Não é um erro fatal, apenas registamos
        error_log("Erro ao marcar notificações como lidas: " . $conn->error);
    }

} catch (Exception $e) {
    // Se algo der errado, guardamos a mensagem de erro para exibir
    $erro_msg = $e->getMessage();
}

// PARTE 2: APRESENTAÇÃO (HTML)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações - Portal TekSea</title>
    <link rel="stylesheet" href="<?= APP_ROOT ?>css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>

    <style>
        /* Estilos para a lista de notificações */
        .notificacao-lista {
            margin-top: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            overflow: hidden; /* Para o border-radius funcionar */
        }
        
        /* Link que envolve o item */
        a.notificacao-link {
            text-decoration: none;
            color: var(--cor-preto, #333);
        }
        a.notificacao-link:hover .notificacao-item {
            background-color: #f9f9f9; /* Leve destaque ao passar o mouse */
        }

        .notificacao-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .notificacao-item:last-child {
            border-bottom: none;
        }

        /* Estilo para item NÃO LIDO (que acabou de ser lido) */
        .notificacao-item.nao-lida {
            background-color: #f8f9fa; /* Um cinza muito leve */
        }
        .notificacao-item.nao-lida .notificacao-conteudo p {
            font-weight: 600; /* Texto em negrito */
        }

        .notificacao-icon {
            font-size: 1.5em; /* 24px */
            margin-right: 20px;
            color: var(--cor-verde, #385856);
            width: 30px; /* Largura fixa para alinhamento */
            text-align: center;
        }

        .notificacao-conteudo {
            flex-grow: 1; /* Ocupa o espaço restante */
        }
        .notificacao-conteudo p {
            margin: 0;
            line-height: 1.5;
        }
        .notificacao-conteudo .notificacao-data {
            font-size: 0.85em;
            color: #777;
            margin-top: 2px;
            font-weight: 400; /* Garante que a data não fique em negrito */
        }

        .notificacao-vazia {
            padding: 40px;
            text-align: center;
            color: #888;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <?php 
        // --- IMPORTANTE ---
        // Incluímos o navbar DEPOIS de atualizar o banco,
        // para que a contagem do 🔔 já apareça como 0.
        require_once __DIR__ . '/navbar.php'; 
        ?>
    </nav>

    <main class="content">
        <header>
            <h1>Central de Notificações</h1>
        </header>

        <?php
        // Bloco de mensagens (Apenas para erros de carregamento)
        if (isset($erro_msg)) {
             echo "<p class='mensagem mensagem-erro'><b>ERRO:</b> " . htmlspecialchars($erro_msg) . "</p>";
        }
        ?>

        <section class="card notificacao-lista">
            
            <?php if (!empty($notificacoes)): ?>
                <?php foreach ($notificacoes as $notif): ?>
                    <?php
                        // Define a classe se a notificação era "não lida"
                        $classe_css = $notif['lida'] == 0 ? 'nao-lida' : '';
                        
                        // Define o ícone (pode ser melhorado no futuro)
                        $icone = 'fa-solid fa-bell'; // Padrão
                        if (strpos($notif['mensagem'], 'enviou') !== false) {
                            $icone = 'fa-solid fa-upload'; // Ícone de Upload
                        }
                        
                        // Formata a data
                        $data = new DateTime($notif['data_criacao']);
                        $data_formatada = $data->format('d/m/Y \à\s H:i');
                        
                        // Define o link (se existir)
                        $link_href = !empty($notif['link']) ? APP_ROOT . trim($notif['link'], '/') : '#';
                        $tag_abertura = "<a href='{$link_href}' class='notificacao-link'>";
                        $tag_fecho = "</a>";
                        
                        // Se não houver link, usamos DIVs
                        if ($link_href === '#') {
                            $tag_abertura = "<div>";
                            $tag_fecho = "</div>";
                        }
                    ?>

                    <?= $tag_abertura ?>
                        <div class="notificacao-item <?= $classe_css ?>">
                            <div class="notificacao-icon">
                                <i class="<?= $icone ?>"></i>
                            </div>
                            <div class="notificacao-conteudo">
                                <p><?= htmlspecialchars($notif['mensagem']) ?></p>
                                <span class="notificacao-data"><?= $data_formatada ?></span>
                            </div>
                        </div>
                    <?= $tag_fecho ?>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="notificacao-vazia">
                    <i class="fa-solid fa-check-double" style="font-size: 2em; margin-bottom: 10px;"></i>
                    <p>Tudo em dia! Nenhuma notificação por aqui.</p>
                </div>
            <?php endif; ?>
            
        </section>
    </main>
</div>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>