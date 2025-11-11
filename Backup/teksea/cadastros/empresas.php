<?php
session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TekSea Sistemas de Energia LTDA</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
  </head>
  <body>
    <div class="container">
      <nav class="sidebar">
        <?php include '../navbar.php'; ?>
      </nav>

      <main class="content">
        <?php
          if (isset($_GET['erro']) && $_GET['erro'] == 1) {
              echo "<p style='color:red;'>Erro ao excluir empresa.</p>";
          }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 1) {
              echo "<p style='color:green;'>Empresa excluída com sucesso.</p>";
          }
        ?>
        <header>
          <h1>Cadastros Empresariais</h1>
        </header>

        <section class="form-card">
          <h1>Cadastro de Empresa</h1>
          <form action="processa_empresa.php" method="POST">
            <div class="input-group two-label-input">
              <div class="campo">
                <label for="razao_social">Razão Social</label>
                <input type="text" id="razao_social" name="razao_social" required>
              </div>
              <div class="campo">
                <label for="nome_fantasia">Nome Fantasia</label>
                <input type="text" id="nome_fantasia" name="nome_fantasia" required>
              </div>
            </div>

            <div class="input-group two-label-input">
              <div class="campo">
                <label for="cnpj">CNPJ</label>
                <input type="text" id="cnpj" name="cnpj" required>
              </div>
              <div class="campo">
                <label for="insc_social">Inscrição Social</label>
                <input type="text" id="insc_social" name="insc_social" required>
              </div>
            </div>

            <label for="tipo">Tipo</label>
            <div class="input-group">
              <select id="tipo" name="tipo" required>
                <option value="0">TEKSEA</option>
                <option value="1">FORNECEDORES</option>
                <option value="2">TERCEIROS</option>
                <option value="3">CLIENTES</option>
              </select>
            </div>

            <label for="cep">CEP</label>
            <div class="input-group">
              <input type="text" id="cep" name="cep" required>
            </div>

            <div class="input-group two-label-input">
              <div class="campo">
                <label for="rua">Rua</label>
                <input type="text" id="rua" name="rua" required>
              </div>
              <div class="campo">
                <label for="nmr">Número</label>
                <input type="text" id="nmr" name="nmr" required>
              </div>
            </div>

            <div class="input-group two-label-input">
              <div class="campo">
                <label for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" required>
              </div>
              <div class="campo">
                <label for="cidade">Cidade</label>
                <input type="text" id="cidade" name="cidade" required>
              </div>
            </div>

            <div class="input-group two-label-input">
              <div class="campo">
                <label for="uf">Estado</label>
                <input type="text" id="uf" name="uf" required>
              </div>
              <div class="campo">
                <label for="pais">País</label>
                <input type="text" id="pais" name="pais" required>
              </div>
            </div>

            <button type="submit" class="btn">Cadastrar</button>
          </form>
        </section>
      </main>
    </div>

    <script src="../../js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        // Máscaras
        Inputmask("99999-999").mask(document.getElementById("cep"));
        Inputmask("99.999.999/9999-99").mask(document.getElementById("cnpj"));


        // Busca de endereço via CEP
        document.getElementById('cep').addEventListener('blur', function () {
          const cep = this.value.replace(/\D/g, '');

          if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
              .then(response => response.json())
              .then(data => {
                if (!data.erro) {
                  document.getElementById('rua').value = data.logradouro;
                  document.getElementById('bairro').value = data.bairro;
                  document.getElementById('cidade').value = data.localidade;
                  document.getElementById('uf').value = data.uf;
                  document.getElementById('pais').value = 'Brasil';
                } else {
                  alert('CEP não encontrado.');
                }
              })
              .catch(() => {
                alert('Erro ao buscar o CEP.');
              });
          } else {
            alert('CEP inválido.');
          }
        });
      });
    </script>

  </body>
</html>
