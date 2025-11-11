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
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://kit.fontawesome.com/90b9d5e3db.js"></script>
  </head>
  <body>
    <div class="container">
      <nav class="sidebar">
        <?php include 'navbar.php'; ?>
      </nav>

      <main class="content">
        <header>
          <h1>Bem-vindo ao Portal TekSea, <?php echo htmlspecialchars($_SESSION['user_nome']); ?>!</h1>
        </header>
        <section class="card table-section">

          <h3>Empresas Homologadas</h3>
          <table>
            <thead>
              <tr>
                <th>CNPJ</th>
                <th>Razão Social</th>
                <th>Data da Homologação</th>
                <th>Data de Expiração</th>
              </tr>
            </thead>
            <?php
              include __DIR__.'/../config/conexao.php';

              $sql = "SELECT
                          e.emp_razao_social,
                          e.emp_cnpj
                      FROM Empresa e";

              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      echo "<tr>";
                      echo "<td>" . htmlspecialchars($row['emp_cnpj']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['emp_razao_social']) . "</td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='4'>Nenhuma empresa encontrada.</td></tr>";
              }
            ?>
          </table>
          <br>
          
        </section>

      </main>
    </div>

    <script>
      document.getElementById('addRow').addEventListener('click', function () {
        const tbody = document.getElementById('tipoBody');
        const newRow = document.createElement('tr');

        newRow.innerHTML = `
          <td><div class="input-group"><div class="campo">
            <input type="text" name="nova_descricao[]" placeholder="Nova categoria" required>
          </div></div></td>  
          <td></td>
          <td><button type="submit">Salvar</button></td>
        `;

        tbody.appendChild(newRow);
      });
    </script>

    <script src="../js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
            crossorigin="anonymous"></script>
  </body> 
</html>