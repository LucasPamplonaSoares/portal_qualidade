<?php
// Garantir que a sessão existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. CARREGAR CONFIGURAÇÕES E CONEXÃO
if (!defined('APP_ROOT')) {
    @include_once __DIR__ . '/../config/config.php';
}
if (!isset($conn)) {
    if (defined('ROOT_PATH')) {
        @include_once ROOT_PATH . '/config/conexao.php';
    } else {
        @include_once __DIR__ . '/../config/conexao.php';
    }
}

// 2. BUSCAR A CONTAGEM DE NOTIFICAÇÕES (APENAS NA PRIMEIRA VEZ)
$count_nao_lidas = 0;
if (isset($conn) && is_object($conn) && isset($_SESSION['user_id'])) {
    $user_id_logado = $_SESSION['user_id'];

    $sql_count = "SELECT COUNT(*) as total FROM notificacoes WHERE user_id_destino = ? AND lida = 0";
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count) {
        $stmt_count->bind_param("i", $user_id_logado);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $count_nao_lidas = $result_count->fetch_assoc()['total'] ?? 0;
        $stmt_count->close();
    }
}
?>

<style>
    /* --- MUDANÇA: CSS DO BADGE CORRIGIDO --- */
    .notificacao-sino-wrapper {
        position: relative;
        display: inline-block;
        width: 1.25em; 
        text-align: center;
        line-height: 1; /* Garante que o sino não tenha altura extra */
    }
    
    .notificacao-sino-wrapper .badge {
        position: absolute;
        top: -8px; /* Puxa mais para cima */
        right: -12px; /* Puxa mais para a direita */
        
        background-color: #d9534f; /* Vermelho */
        color: white;
        font-size: 10px;
        font-weight: bold;
        
        /* Garante que seja um círculo */
        min-width: 18px;
        height: 18px;
        line-height: 18px; /* Centraliza o número verticalmente */
        text-align: center;
        border-radius: 50%;
        
        padding: 0; /* Remove padding extra */
        border: 2px solid var(--cor-verde-escuro, #2c4543); /* Borda da cor do menu */
        
        /* Esconde se estiver vazio */
        display: none; 
    }
    
    /* Mostra o badge apenas se ele NÃO estiver vazio */
    .notificacao-sino-wrapper .badge:not(:empty) {
        display: block;
    }
</style>

<ul class="sidebar-menu">
    <li>
        <a href="<?= APP_ROOT ?>fornecedores/home.php" class="nav-item">
            <i class="fa-solid fa-house"></i> Início
        </a>
    </li>
    <li>
        <a href="<?= APP_ROOT ?>fornecedores/homologacao.php" class="nav-item">
            <i class="fa-solid fa-building"></i> Minha Homologação
        </a>
    </li>

    <li>
        <a href="<?= APP_ROOT ?>fornecedores/notificacoes.php" class="nav-item">
            <span class="notificacao-sino-wrapper">
                <i class="fa-solid fa-bell"></i>
                <span class="badge" id="notificacao-badge">
                    <?= ($count_nao_lidas > 0) ? $count_nao_lidas : '' ?>
                </span>
            </span>
            Notificações 
        </a>
    </li>
    
    <li>
        <a href="<?= APP_ROOT ?>fornecedores/dados_empresa.php" class="nav-item">
            <i class="fa-solid fa-address-card"></i> Dados da Empresa
        </a>
    </li>
    
    <li>
        <a href="<?= APP_ROOT ?>fornecedores/user.php" class="nav-item">
            <i class="fa-solid fa-user"></i> Meu Perfil
        </a>
    </li>
    <li>
        <a href="<?= APP_ROOT ?>login/logout.php" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i> Sair
        </a>
    </li>
</ul>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const badge = document.getElementById('notificacao-badge');
    
    // 1. Função que busca a contagem na API
    async function fetchNotificacoes() {
        try {
            // (Vamos criar este ficheiro no Passo 2)
            const response = await fetch('<?= APP_ROOT ?>fornecedores/api_get_notificacoes.php');
            if (!response.ok) return;

            const data = await response.json();
            
            if (data.count > 0) {
                badge.textContent = data.count;
            } else {
                badge.textContent = '';
            }
        } catch (error) {
            console.error('Erro ao buscar notificações:', error);
        }
    }
    
    // 2. Chama a função imediatamente
    fetchNotificacoes();
    
    // 3. E chama a função a cada 30 segundos
    setInterval(fetchNotificacoes, 30000); // 30.000 ms = 30 segundos
});
</script>