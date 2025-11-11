// Home.js atualizado para login AJAX com PHP
const form = document.getElementById('loginForm');
const msgDiv = document.getElementById('mensagem');

if (form) {
  form.addEventListener('submit', function(event) {
    event.preventDefault();

    const formData = new FormData(form);

    fetch('processa_login.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.text())
    .then(data => {
      if (data.trim() === 'ok') {
        window.location.href = 'home'; // URL amigável
      } else {
        mostrarMensagem('E-mail ou senha inválidos.');
      }
    })
    .catch(error => {
      mostrarMensagem('Erro de comunicação com o servidor.');
      console.error(error);
    });
  });
}

function mostrarMensagem(texto) {
  if (!msgDiv) return;
  msgDiv.innerText = texto;
  msgDiv.style.display = 'block';

  setTimeout(() => {
    msgDiv.style.display = 'none';
  }, 5000);
}
