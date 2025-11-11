<?php
// navbar.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__.'/../config/config.php'; 

// --- Bloco PHP para buscar notificações (Seu código original, mantido) ---
$total_nao_lidas = 0;

if (isset($_SESSION['logado']) && isset($_SESSION['emp_id']) && $_SESSION['emp_id'] == 11 && isset($_SESSION['user_id']) && isset($conn)) {
    
    $user_id_atual = $_SESSION['user_id'];
    
    $sql_count = "SELECT COUNT(*) as total FROM notificacoes WHERE user_id_destino = ? AND lida = 0";
    $stmt_count = $conn->prepare($sql_count);
    
    if ($stmt_count) {
        $stmt_count->bind_param("i", $user_id_atual);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $row = $result_count->fetch_assoc();
        $total_nao_lidas = $row['total'] ?? 0;
        $stmt_count->close();
    } else {
        error_log("Erro ao preparar contagem de notificações: " . $conn->error);
    }
}
// --- FIM DO BLOCO ---
?>

<style>
.nav-item.notificacoes { position: relative; }
.notificacao-badge {
    position: absolute;
    top: 10px; right: 20px;
    background-color: #d9534f;
    color: white;
    font-weight: bold;
    font-size: 11px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 2px solid var(--cor-verde, #385856);
}
</style>
<div class="sidebar-header" id="navbar">
    <a href="<?= APP_ROOT ?>teksea/home.php"><img src="<?= APP_ROOT ?>img/LOGO-TEKSEA-VERDE.PNG" alt="TekSea Logo" class="logo"></a>
    
    <ul class="sidebar-menu">
        <li><a href="<?= APP_ROOT ?>teksea/home.php" class="nav-item"><i class="fa-solid fa-house"></i> Início</a></li>
        
        <?php if (isset($_SESSION['logado']) && isset($_SESSION['emp_id']) && $_SESSION['emp_id'] == 11): ?>    
    
            <li>
                <a href="<?= APP_ROOT ?>teksea/notificacoes.php" class="nav-item notificacoes">
                    <i class="fa-solid fa-bell"></i> Notificações
                    
                    <?php if ($total_nao_lidas > 0): ?>
                        <span class="notificacao-badge" id="badge-notificacao">
                            <?= $total_nao_lidas > 9 ? '9+' : $total_nao_lidas ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <li><a href="<?= APP_ROOT ?>teksea/cadastros/empresas.php" class="nav-item sub-item"><i class="fa-solid fa-circle-info"></i> Empresas</a></li>
            <li><a href="<?= APP_ROOT ?>teksea/cadastros/usuarios.php" class="nav-item sub-item"><i class="fa-solid fa-circle-user"></i> Usuários</a></li>
            <li><a href="<?= APP_ROOT ?>teksea/cadastros/upload_anexo.php" class="nav-item sub-item"><i class="fa-solid fa-clipboard"></i> Documentos</a></li>
            <li><a href="<?= APP_ROOT ?>teksea/user.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Configurações</a></li>
            
        <?php endif; ?>

        <li><a href="<?= APP_ROOT ?>login/logout.php" class="nav-item"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
    </ul>
</div>

<audio id="som-notificacao" src="<?= APP_ROOT ?>sons/notificacao.mp3" preload="auto"></audio>
<div class="sidebar-footer">
    <div class="footer-button">
        <p>&copy; <?php echo date("Y"); ?> TekSea</p>
        <small>Todos os direitos reservados.</small>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    
    // 1. Pega a contagem inicial que o PHP carregou
    let contagemAtual = <?= $total_nao_lidas ?>;
    
    // 2. Encontra o elemento de áudio
    const somNotificacao = document.getElementById('som-notificacao');

    /**
     * Função que atualiza o crachá (badge) 🔔
     */
    function atualizarBadge(contagem) {
        let badge = document.getElementById('badge-notificacao');
        
        if (contagem > 0) {
            const textoBadge = contagem > 9 ? '9+' : contagem;
            
            if (badge) {
                // Se o badge já existe, só atualiza o número
                badge.textContent = textoBadge;
            } else {
                // Se o badge não existe (contagem era 0), cria e adiciona
                badge = document.createElement('span');
                badge.id = 'badge-notificacao';
                badge.className = 'notificacao-badge';
                badge.textContent = textoBadge;
                
                const linkPai = document.querySelector('.nav-item.notificacoes');
                if (linkPai) {
                    linkPai.appendChild(badge);
                }
            }
        } else {
            // Se a contagem for 0 e o badge existir, remove-o
            if (badge) {
                badge.remove();
            }
        }
    }

    /**
     * Função que verifica o servidor por novas notificações
     */
    function checarNotificacoes() {
        // Chama o nosso ficheiro "Verificador"
        fetch('<?= APP_ROOT ?>teksea/helpers/check_notificacoes.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    const novaContagem = data.novas_notificacoes;
                    
                    // A MÁGICA ACONTECE AQUI
                    if (novaContagem > contagemAtual) {
                        
                        // 1. Toca o som (se existir)
                        if (somNotificacao) {
                            somNotificacao.play().catch(e => {
                                console.warn("O som não pôde ser tocado automaticamente. O usuário precisa interagir com a página primeiro.");
                            });
                        }
                    }
                    
                    // 2. Atualiza o badge 🔔 (mesmo que não toque o som)
                    atualizarBadge(novaContagem);
                    
                    // 3. Atualiza a contagem local para a próxima verificação
                    contagemAtual = novaContagem;
                }
            })
            .catch(error => {
                console.error("Erro ao checar notificações:", error);
            });
    }

    // --- Inicia o "Polling" ---
    // Verifica pela primeira vez (para o caso de algo ter chegado 
    // enquanto a página carregava)
    checarNotificacoes(); 
    
    // E depois continua a verificar a cada 15 segundos
    setInterval(checarNotificacoes, 15000); // 15000 ms = 15 segundos
});
</script>