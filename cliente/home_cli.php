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
  <title>Dashboard TekSea</title>
  <link rel="stylesheet" href="../css/styles.css">
  <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
</head>
<body>
  <div class="container">
    <nav class="sidebar">
      <?php include 'navbar.php'; ?>
      <?php include 'footer.php'; ?>
    </nav>

    <main class="content">
      <header>
        <h1>Bem-vindo ao Portal TekSea, <?php echo $_SESSION['user_name']; ?>!</h1>
      </header>

      <section class="card table-section">
        <h3>Termos, Políticas e Formulários</h3>
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Arquivo</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>Teste</td><td>teste.pdf</td><td>Pendente</td></tr>
            <tr><td>Teste</td><td>teste.pdf</td><td>Pendente</td></tr>
            <tr><td>Teste</td><td>teste.pdf</td><td>Pendente</td></tr>
            <tr><td>Teste</td><td>teste.pdf</td><td>Pendente</td></tr>
          </tbody>
        </table>
      </section>
    </main>
  </div>

<script src="js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
</body>
</html>
