<?php
session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login");
    exit;
}?>

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
          if (isset($_SESSION['mensagem'])) {
              echo "<div class='mensagem'>" . htmlspecialchars($_SESSION['mensagem']) . "</div>";
              unset($_SESSION['mensagem']);
          }
        ?>
        <header>
          <h1>Cadastro de Documentos</h1>
        </header>

        <section class="form-card">
          <h1>Anexo</h1>
          <form action="processa_upload.php" method="POST" enctype="multipart/form-data">
            <div class="input-group two-label-input">
              <div class="campo">
                <label for="anx_nome">Nome do Arquivo</label>
                <input type="text" id="anx_nome" name="anx_nome" required>
              </div>
              <div class="campo">
                <label for="tipo_id">Categoria Documento</label>
                <select id="tipo_id" name="tipo_id" required>
                  <option value="">Selecione um tipo</option>
                  <?php
                  include __DIR__.'/../../config/conexao.php';
                  $sql = "SELECT tipo_id, tipo_descricao FROM Tipo ORDER BY tipo_descricao ASC";
                  $result = $conn->query($sql);
                  if ($result && $result->num_rows > 0) {
                      while ($tipo = $result->fetch_assoc()) {
                          echo "<option value='" . $tipo['tipo_id'] . "'>" . htmlspecialchars($tipo['tipo_descricao']) . "</option>";
                      }
                  } else {
                      echo "<option disabled>Nenhum tipo cadastrado</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <label for="anx_arquivo">Arquivo</label>
            <div class="input-group">
              <div class="campo">
                <div class="upload-wrapper">
                  <label for="anx_arquivo" class="btn-upload"><i class="fas fa-upload"></i> Selecionar</label>
                  <span id="nome-arquivo" class="file-name">Nenhum arquivo selecionado</span>
                  <input type="file" id="anx_arquivo" name="anx_arquivo" required hidden>
                </div>
              </div>
            </div>


            <button type="submit" class="btn">Enviar Anexo</button>
          </form>

        </section>
      </main>
    </div>

    <script>
      
      // Atualiza o nome do arquivo selecionado
      const inputArquivo = document.getElementById('anx_arquivo');
      const nomeArquivo = document.getElementById('nome-arquivo');

      inputArquivo.addEventListener('change', function () {
        if (this.files.length > 0) {
          nomeArquivo.textContent = this.files[0].name;
        } else {
          nomeArquivo.textContent = 'Nenhum arquivo selecionado';
        }
      });

    </script>
    <script src="../../js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
