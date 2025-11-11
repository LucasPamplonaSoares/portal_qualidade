// script.js (corrigido e robusto)
document.addEventListener('DOMContentLoaded', function () {
  // Normaliza path atual ex.: "/cadastros/usuarios.php"
  const currentPath = window.location.pathname.replace(/\/+$/, ''); // sem barra final

  // Seletores
  const navLinks = Array.from(document.querySelectorAll('.nav-item[href]'));             // itens de topo (Home, etc.)
  const dropdownLinks = Array.from(document.querySelectorAll('.submenu .dropdown-item'));// itens do submenu
  const navDropdown = document.querySelector('.menu-toggle > a.nav-item');               // toggle do submenu
  const submenu = document.getElementById('submenu');
  const arrow = document.getElementById('arrow');

  // Converte qualquer href relativo ("cadastros/usuarios.php") para pathname absoluto ("/cadastros/usuarios.php")
  const pathOf = (href) => {
    try {
      return new URL(href, window.location.origin).pathname.replace(/\/+$/, '');
    } catch {
      return href;
    }
  };

  // 1) Marca item de topo como selecionado quando o pathname bate
  navLinks.forEach((link) => {
    const href = link.getAttribute('href');
    const hrefPath = pathOf(href);

    if (hrefPath !== '#' && hrefPath === currentPath) {
      link.classList.add('selected');
    } else {
      link.classList.remove('selected');
    }
  });

  // 2) Marca item do submenu e abre o submenu quando necessário
  let childMatched = false;

  dropdownLinks.forEach((link) => {
    const hrefPath = pathOf(link.getAttribute('href'));

    if (hrefPath === currentPath) {
      childMatched = true;
      link.classList.add('selected');

      // Abre o submenu e marca o toggle
      if (submenu) submenu.style.maxHeight = submenu.scrollHeight + 'px';
      if (navDropdown) navDropdown.classList.add('selected');
      if (arrow) arrow.classList.add('rotate');
    } else {
      link.classList.remove('selected');
    }
  });

  // 3) (Opcional) Se nenhum filho bateu e você quiser fechar o submenu ao carregar:
  if (!childMatched && submenu) {
    submenu.style.maxHeight = null;
    if (arrow) arrow.classList.remove('rotate');
    if (navDropdown) navDropdown.classList.remove('selected');
  }

  // 4) Toggle do submenu (sem inline onclick no <li>)
  if (navDropdown && submenu) {
    navDropdown.addEventListener('click', function (e) {
      e.preventDefault();
      const isOpen = !!submenu.style.maxHeight;
      if (isOpen) {
        submenu.style.maxHeight = null;
        arrow && arrow.classList.remove('rotate');
        navDropdown.classList.remove('selected');
      } else {
        submenu.style.maxHeight = submenu.scrollHeight + 'px';
        arrow && arrow.classList.add('rotate');
        navDropdown.classList.add('selected');
      }
    });
  }
});