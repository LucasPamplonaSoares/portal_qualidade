<?php
session_start();
// Se já estiver logado, manda direto pra home correta
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    switch ($_SESSION['emp_tipo']) {
        case 0:
            header("Location: ../teksea/home.php");
            break;
        case 1:
            header("Location: ../fornecedores/home.php");
            break;
        case 2:
            header("Location: ../terceiros/home.php");
            break;
        default:
            header("Location: ../fornecedores/home.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Portal de Fornecedores TekSea</title>
  <link rel="stylesheet" href="../css/index.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <div class="login-side">
      <div class="login-container">
        <h2>Portal de Fornecedores TekSea</h2>

        <div id="mensagem" class="mensagem-erro" style="display: none;"></div>

        <form id="loginForm" method="POST">
          <div class="input-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
          </div>
          <div class="input-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="senha" required>
          </div>
          <button type="submit" class="login-btn">ENTRAR</button>
        </form>
      </div>
    </div>
    <div class="image-side"></div>
  </div>

<script>
const form = document.getElementById('loginForm');
const msgDiv = document.getElementById('mensagem');

form.addEventListener('submit', async function(event) {
  event.preventDefault();
  const formData = new FormData(form);

  try {
    const response = await fetch('processa_login.php', {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'application/json' }
    });

    const text = await response.text();
    console.log("Resposta bruta do PHP:", text);

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error("Resposta não é JSON válido: " + text);
    }

    if (data.status === 'ok') {
      switch (parseInt(data.tipo)) {
        case 0:
          window.location.href = '../teksea/home.php';
          break;
        case 1:
          window.location.href = '../fornecedores/home.php';
          break;
        case 2:
          window.location.href = '../terceiros/home.php';
          break;
        default:
          mostrarMensagem('Tipo de empresa desconhecido: ' + data.tipo);
      }
    } else {
      mostrarMensagem(data.mensagem || 'Erro ao fazer login.');
    }

  } catch (error) {
    mostrarMensagem('Erro de conexão: ' + error.message);
    console.error(error);
  }
});

function mostrarMensagem(texto) {
  msgDiv.innerText = texto;
  msgDiv.style.display = 'block';
  setTimeout(() => { msgDiv.style.display = 'none'; }, 4000);
}
</script>
</body>
</html>
