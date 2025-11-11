// Arquivo: js/script.js (Versão Final e Correta)
document.addEventListener('DOMContentLoaded', function() {

    // --- SELETORES DOS ELEMENTOS DO MENU ---
    const menuToggle = document.querySelector('.menu-toggle');
    const submenu = document.querySelector('.submenu');
    const arrow = document.querySelector('.arrow');
    const allLinks = document.querySelectorAll('.sidebar-menu a');

    // Pega o caminho da URL atual
    const currentPath = window.location.pathname;

    // --- LÓGICA PARA MARCAR O LINK ATIVO NA PÁGINA ---
    let isSubmenuLinkActive = false;

    allLinks.forEach(link => {
        // Compara o caminho do link com o caminho da página atual
        if (link.pathname === currentPath) {
            link.classList.add('selected');

            // Verifica se o link ativo está dentro do submenu
            if (link.closest('.submenu')) {
                isSubmenuLinkActive = true;
            }
        }
    });

    // Se um link do submenu estiver ativo, expande o menu e marca o item pai
    if (isSubmenuLinkActive && menuToggle) {
        submenu.classList.add('active');
        if (arrow) arrow.classList.add('active');
        menuToggle.querySelector('a').classList.add('selected');
    }

    // --- LÓGICA PARA CONTROLAR O CLIQUE E ABRIR/FECHAR O MENU ---
    if (menuToggle) {
        menuToggle.addEventListener('click', function(event) {
            // Impede que o link '#' cause um pulo na página
            event.preventDefault();
            
            // Simplesmente abre/fecha o submenu e gira a seta
            if (submenu) submenu.classList.toggle('active');
            if (arrow) arrow.classList.toggle('active');
        });
    }
});