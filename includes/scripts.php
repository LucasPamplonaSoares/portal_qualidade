<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- LÓGICA DO MENU DROPDOWN ---
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
        const toggleLink = menuToggle.querySelector('a.nav-item');
        if (toggleLink) {
            toggleLink.addEventListener('click', function(event) {
                event.preventDefault();
                const submenu = this.nextElementSibling;
                const arrow = this.querySelector('.arrow');
                if (submenu) submenu.classList.toggle('active');
                if (arrow) arrow.classList.toggle('active');
            });
        }
    }

    // --- LÓGICA PARA MARCAR LINK ATIVO ---
    const allLinks = document.querySelectorAll('.sidebar-menu a');
    const currentPath = window.location.pathname;
    allLinks.forEach(link => {
        if (link.pathname === currentPath) {
            link.classList.add('selected');
            const submenu = link.closest('.submenu');
            if (submenu) {
                submenu.classList.add('active');
                const parentToggle = submenu.closest('.menu-toggle');
                if (parentToggle) {
                    parentToggle.querySelector('.arrow')?.classList.add('active');
                    parentToggle.querySelector('a.nav-item')?.classList.add('selected');
                }
            }
        }
    });

    // --- LÓGICA PARA ADICIONAR NOVA CATEGORIA (DA PÁGINA USER.PHP) ---
    const addRowBtn = document.getElementById('addRow');
    const tableBody = document.getElementById('tipoBody');

    if (addRowBtn && tableBody) {
        addRowBtn.addEventListener('click', function () {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <div class="input-group" style="margin-bottom: 0;">
                        <input type="text" name="nova_descricao[]" placeholder="Nova categoria" required style="padding: 10px; width: 100%;">
                    </div>
                </td>
                <td>
                    <div class="input-group" style="margin-bottom: 0;">
                        <select name="tipo_acao[]" required style="padding: 10px; width: 100%;">
                            <option value="1">Download Obrigatório</option>
                            <option value="2">Aceite</option>
                        </select>
                    </div>
                </td>
                <td class="actions-cell">
                    <button type="submit" class="action-link download" title="Salvar">
                        <i class="fa-solid fa-save"></i>
                    </button>
                </td>
                <td></td> 
            `;
            tableBody.appendChild(newRow);
            this.disabled = true; // Desabilita o botão '+' para adicionar uma linha por vez
        });
    }
});
</script>