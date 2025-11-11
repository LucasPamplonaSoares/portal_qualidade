fetch('navbar_tks.html')
  .then(response => response.text())
  .then(data => {
    const navbar = document.getElementById('navbar-container');
    if (navbar) {
      navbar.innerHTML = data;

      const currentPage = window.location.pathname.split("/").pop();
      const navLinks = document.querySelectorAll(".nav-item");

      navLinks.forEach(link => {
        if (link.getAttribute("href") === currentPage) {
          link.classList.add("selected");
        } else {
          link.classList.remove("selected");
        }
      });
    }
  });
fetch('footer_tks.html')
  .then(response => response.text())
  .then(data => {
    const footer = document.getElementById('footer-container');
    if (footer) {
      footer.innerHTML = data;
    }
  });