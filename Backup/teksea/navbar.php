<?php require_once __DIR__.'/../config/config.php'; ?>
<div class="sidebar-header" id="navbar">
    <img src="https://teksea.net/wordpress/wp-content/uploads/2019/08/logo-tks-2.png" alt="TekSea Logo" class="logo">
    <ul class="sidebar-menu">
        <li><a href="<?= APP_ROOT ?>teksea/home.php" class="nav-item" data-page="home"><i class="fa-solid fa-house"></i> Início</a></li>
        
        <li class="menu-toggle" onclick="toggleSubmenu()">
            <a href="#" class="nav-item" data-page="cadastros">
                <i class="fa-solid fa-building"></i> Cadastros Empresariais
                <span class="arrow" id="arrow">&#9662;</span>
            </a>
            <ul class="submenu" id="submenu">
                <li><a href="<?= APP_ROOT ?>teksea/cadastros/empresas.php" class="dropdown-item"><i class="fa-solid fa-circle-info"></i> Empresas</a></li>
                <li><a href="<?= APP_ROOT ?>teksea/cadastros/usuarios.php" class="dropdown-item"><i class="fa-solid fa-circle-user"></i> Usuários</a></li>
                <li><a href="<?= APP_ROOT ?>teksea/cadastros/upload_anexo.php" class="nav-item" data-page="anexos"><i class="fa-solid fa-clipboard"></i> Documentos</a></li>
            </ul>
        </li>
        
        <li><a href="<?= APP_ROOT ?>teksea/user.php" class="nav-item" data-page="user"><i class="fa-solid fa-user-gear"></i> Configurações</a></li>
        
    </ul>
</div>
    <div class="sidebar-footer">
        <div class="footer-button">
    <p>&copy; 2025 TekSea</p>
    <small>Todos os direitos reservados.</small>
  </div>
</div>
