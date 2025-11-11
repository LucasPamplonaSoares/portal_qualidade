<?php
  session_start();
  if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
      header("Location: login");
      exit;
  }

  include '../config/conexao.php';

  $emp_id = $_SESSION['emp_id'] ?? null;
  $razao_social = 'Empresa não definida';

  // Consulta razão social da empresa
  if ($conn->connect_error) {
      die("Erro de conexão: " . $conn->connect_error);
  }

  if ($emp_id) {
      $stmt = $conn->prepare("SELECT emp_razao_social FROM empresa WHERE emp_id = ?");
      if ($stmt) {
          $stmt->bind_param("i", $emp_id);
          $stmt->execute();
          $stmt->bind_result($razao_social);
          $stmt->fetch();
          $stmt->close();
      }
  }

  // Atualização de dados do usuário
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
      $nome = $_POST['nome'] ?? '';
      $sobrenome = $_POST['sobrenome'] ?? '';
      $senha = $_POST['senha'] ?? '';
      $user_id = $_SESSION['user_id'];

      // Atualiza nome e sobrenome
      $stmt = $conn->prepare("UPDATE Usuario SET user_nome = ?, user_sobrenome = ? WHERE user_id = ?");
      $stmt->bind_param("ssi", $nome, $sobrenome, $user_id);
      $stmt->execute();
      $stmt->close();

      // Atualiza senha se fornecida
      if (!empty($senha)) {
          $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
          $stmt = $conn->prepare("UPDATE Usuario SET user_senha = ? WHERE user_id = ?");
          $stmt->bind_param("si", $senha_hash, $user_id);
          $stmt->execute();
          $stmt->close();
      }

      // Atualiza sessão
      $_SESSION['user_nome'] = $nome;
      $_SESSION['user_sobrenome'] = $sobrenome;

      header("Location: user.php?sucesso=4");
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
          <h1>Configurações</h1>
        </header>

        <form method="POST" action="">
          <div id="usuario" class="tab-content active form-card">
            <?php
              if (isset($_GET['erro']) && $_GET['erro'] == 1) {
                  echo "<p style='color:red;'>Erro ao excluir empresa.</p>";
              }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 1) {
                  echo "<p style='color:green;'>Empresa excluída com sucesso.</p>";
              }elseif (isset($_GET['erro']) && $_GET['erro'] == 2) {
                  echo "<p style='color:red;'>Erro ao excluir usuário.</p>";
              }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 2) {
                  echo "<p style='color:green;'>Usuário excluído com sucesso.</p>";
              }elseif (isset($_GET['erro']) && $_GET['erro'] == 3) {
                  echo "<p style='color:red;'>Erro ao excluir arquivo.</p>";
              }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 3) {
                  echo "<p style='color:green;'>Arquivo excluído com sucesso.</p>";
              }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 4) {
                echo "<p style='color:green;'>Usuário cadastrado com sucesso.</p>";
              }elseif (isset($_GET['erro']) && $_GET['erro'] == 4) {
                echo "<p style='color:red;'>Erro ao cadastrar usuário.</p>";
              }elseif (isset($_GET['erro']) && $_GET['erro'] == 5) {
                  echo "<p style='color:red;'>Erro ao criar categoria.</p>";
              }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 5) {
                  echo "<p style='color:green;'>Categoria criada com sucesso.</p>";
              }elseif (isset($_GET['erro']) && $_GET['erro'] == 6) {
                  echo "<p style='color:red;'>Erro ao excluir categoria.</p>";
              }elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == 6) {
                  echo "<p style='color:green;'>Categoria excluída com sucesso.</p>";
              }
            ?>
            <h3>Perfil do Usuário</h3>

            <div class="input-group two-label-input">
              <div class="campo">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" value="<?php echo $_SESSION['user_nome']; ?>" required>
              </div>
              <div class="campo">
                <label for="sobrenome">Sobrenome</label>
                <input type="text" id="sobrenome" name="sobrenome" value="<?php echo $_SESSION['user_sobrenome']; ?>" required>
              </div>
            </div>

            <label>Email:</label>
            <div class="input-group">
              <input type="email" value="<?php echo $_SESSION['user_email']; ?>" readonly>
            </div>

            <label>Empresa:</label>
            <div class="input-group">
              <input type="text" value="<?php echo htmlspecialchars($razao_social); ?>" readonly>
            </div>

            <label>Senha:</label>
            <div class="input-group">
              <input type="password" name="senha" placeholder="Nova senha">
            </div>

            <button class="btn" type="submit" name="salvar">Salvar Alterações</button>
          </div>
        </form>

        <br>

        <section class="card table-section">
          <h3>Categorias de Documentos</h3>
          <form method="POST" action="cadastros/processa_tipo.php">
            <table>
              <thead>
                <tr>
                  <th>Descrição</th>
                  <th>Ações</th>
                  <th>
                    <button type="button" id="addRow" class="add-action">
                      <i class="fa-solid fa-circle-plus"></i>
                    </button>

                  </th>
                </tr>
              </thead>
              <tbody id="tipoBody">
                <?php
                  $sql = "SELECT 
                              t.tipo_id,
                              t.tipo_descricao,
                              t.tipo_acao
                          FROM Tipo t";

                  $result = $conn->query($sql);

                  if ($result && $result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                          echo "<tr>";
                          echo "<td>" . htmlspecialchars($row['tipo_descricao']) . "</td>";
                          if ($row["tipo_acao"] == 0) {
                            echo "<td> Indefinido</td>";
                          }elseif ($row["tipo_acao"] == 1) {
                            echo "<td>Download Obrigatório</td>";
                          }elseif ($row["tipo_acao"] == 2) {
                            echo "<td>Aceite</td>";
                          }
                          echo "<td><a href='exclusoes/excluir_tipo.php?id=" . urlencode($row['tipo_id']) . "' onclick='return confirm(\"Tem certeza que deseja excluir esta categoria?\")'><i class='fas fa-trash-alt' style='color:red;'></i></a></td>";
                      }
                  } else {
                      echo "<tr><td colspan='4'>Nenhum arquivo encontrado.</td></tr>";
                  }
                ?>
              </tbody>
            </table>
          </form>

          <br>
          <h3>Termos, Políticas e Formulários Cadastrados</h3>
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Arquivo</th>
                <th>Tipo</th>
                <th>Excluir</th>
              </tr>
            </thead>
            <?php
              $sql = "SELECT 
                          a.anx_id,
                          a.anx_nome, 
                          a.anx_arquivo,
                          t.tipo_descricao
                      FROM Anexo a
                      LEFT JOIN Tipo t ON a.tipo_id = t.tipo_id";

              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      echo "<tr>";
                      echo "<td>" . htmlspecialchars($row['anx_nome']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['anx_arquivo']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['tipo_descricao']) . "</td>";
                      echo "<td><a href='exclusoes/excluir_anexo.php?id=" . urlencode($row['anx_id']) . "' onclick='return confirm(\"Tem certeza que deseja excluir este arquivo?\")'><i class='fas fa-trash-alt' style='color:red;'></i></a></td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='4'>Nenhum arquivo encontrado.</td></tr>";
              }
            ?>
          </table>

          <br>

          <h3>Empresas Cadastradas</h3>
          <table>
            <thead>
              <tr>
                <th>CNPJ</th>
                <th>Nome Fantasia</th>
                <th>Razão Social</th>
                <th>Excluir</th>
              </tr>
            </thead>
            <?php
              include __DIR__.'/../config/conexao.php';

              $sql = "SELECT
                          e.emp_razao_social,
                          e.emp_nome_fantasia,
                          e.emp_cnpj
                      FROM Empresa e";

              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      echo "<tr>";
                      echo "<td>" . htmlspecialchars($row['emp_cnpj']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['emp_nome_fantasia']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['emp_razao_social']) . "</td>";
                      echo "<td><a href='exclusoes/excluir_empresa.php?cnpj=" . urlencode($row['emp_cnpj']) . "' onclick='return confirm(\"Tem certeza que deseja excluir esta empresa?\")'><i class='fas fa-trash-alt' style='color:red;'></i></a></td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='4'>Nenhuma empresa encontrada.</td></tr>";
              }
            ?>
          </table>
          <br>
          <h3>Usuários Ativos</h3>
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Empresa</th>
                <th>Tipo</th>
                <th>Excluir</th>
              </tr>
            </thead>
            <?php
              $sql = "SELECT 
                          u.user_id,
                          u.user_nome, 
                          u.user_sobrenome, 
                          e.emp_razao_social,
                          e.emp_tipo
                      FROM Usuario u
                      LEFT JOIN Empresa e ON u.emp_id = e.emp_id";

              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      $nomeCompleto = trim($row['user_nome'] . ' ' . $row['user_sobrenome']);
                      echo "<tr>";
                      echo "<td>" . htmlspecialchars($nomeCompleto) . "</td>";
                      echo "<td>" . htmlspecialchars($row['emp_razao_social']) . "</td>";
                      if ($row["emp_tipo"] == "0") {
                        echo "<td> TEKSEA</td>";
                      }elseif ($row["emp_tipo"] == "1") {
                        echo "<td>FORNECEDOR</td>";
                      }elseif ($row["emp_tipo"] == "2") {
                        echo "<td>TERCEIRO</td>";
                      }elseif ($row["emp_tipo"] == "3") {
                        echo "<td>CLIENTE</td>";
                      }
                      echo "<td><a href='exclusoes/excluir_usuario.php?id=" . urlencode($row['user_id']) . "' onclick='return confirm(\"Tem certeza que deseja excluir este usuário?\")'><i class='fas fa-trash-alt' style='color:red;'></i></a></td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='4'>Nenhum usuário encontrado.</td></tr>";
              }
            ?>
          </table>
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
          <td><div class="input-group">
              <select name="tipo_acao[]" required>
                <option value="">Selecione uma Ação</option>
                <option value=1>Download Obrigatório</option>
                <option value=2>Aceite</option>
              </select>
            </div></td>
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