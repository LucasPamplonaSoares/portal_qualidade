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
        <header>
          <h1>Cadastros Empresariais</h1>
        </header>

        <section class="form-card">
          <h1>Cadastro de Usuário</h1>
          <form action="processa_usuario.php" method="POST">
            <div class="input-group two-label-input">
              <div class="campo">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required>
              </div>
              <div class="campo">
                <label for="sobrenome">Sobrenome</label>
                <input type="text" id="sobrenome" name="sobrenome" required>
              </div>
            </div>

            <label for="empresa">Empresa</label>
            <div class="input-group">
              <div class="campo">
                <select id="emp_id" name="emp_id" required>
                  <option value="">Selecione uma empresa</option>
                  <?php
                  include __DIR__.'/../../config/conexao.php';
                  $sql = "SELECT emp_id, emp_razao_social FROM Empresa ORDER BY emp_razao_social ASC";
                  $result = $conn->query($sql);
                  if ($result && $result->num_rows > 0) {
                      while ($tipo = $result->fetch_assoc()) {
                          echo "<option value='" . $tipo['emp_id'] . "'>" . htmlspecialchars($tipo['emp_razao_social']) . "</option>";
                      }
                  } else {
                      echo "<option disabled>Nenhum tipo cadastrado</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <label for="email">E-mail</label>
            <div class="input-group">
              <input type="email" id="email" name="email" required>
            </div>

            <label for="senha">Senha</label>
            <div class="input-group">
              <input type="password" id="senha" name="senha" required>
            </div>

            <label for="confirmar-senha">Confirmar Senha</label>
            <div class="input-group">
              <input type="password" id="confirmar-senha" name="confirmar-senha" required>
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
  </body>
</html>
