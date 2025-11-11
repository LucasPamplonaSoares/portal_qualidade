<?php
  session_start();
  if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
      header("Location: login");
      exit;
  }

  include '../config/conexao.php';
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
          <h1>Termos, Políticas e Formulários</h1>
        </header>

        <?php
        // Buscar todos os tipos distintos
        $sqlTipos = "SELECT DISTINCT tipo_descricao FROM Tipo";
        $resultTipos = $conn->query($sqlTipos);

        if ($resultTipos && $resultTipos->num_rows > 0) {
            while ($tipo = $resultTipos->fetch_assoc()) {
                $descricao = $tipo['tipo_descricao'];

                echo "<section class='card table-section'>";
                echo "<h3>" . htmlspecialchars($descricao) . "</h3>";
                echo "<table>";
                echo "<thead><tr><th>Nome</th><th>Arquivo</th><th>Situação</th></tr></thead>";

                $sqlAnexos = "SELECT a.anx_id, a.anx_nome, a.anx_arquivo 
                              FROM Anexo a 
                              LEFT JOIN Tipo t ON a.tipo_id = t.tipo_id 
                              WHERE t.tipo_descricao = ?";
                $stmt = $conn->prepare($sqlAnexos);
                $stmt->bind_param("s", $descricao);
                $stmt->execute();
                $resultAnexos = $stmt->get_result();

                if ($resultAnexos && $resultAnexos->num_rows > 0) {
                    while ($row = $resultAnexos->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['anx_nome']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['anx_arquivo']) . "</td>";
                        echo "<td>Em Desenvolvimento</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>Nenhum arquivo encontrado.</td></tr>";
                }

                echo "</table>";
                echo "</section>";
            }
        } else {
            echo "<p>Nenhum tipo de documento encontrado.</p>";
        }
        ?>
      </main>
    </div>

    <script src="../js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
            crossorigin="anonymous"></script>
  </body> 
</html>