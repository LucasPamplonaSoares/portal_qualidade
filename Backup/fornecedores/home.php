<?php
  session_start();
  if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
      header("Location: login");
      exit;
  }

  include '../config/conexao.php';  

  $emp_id = $_SESSION['emp_id'] ?? null;
  $razao_social = 'Empresa não definida'. $emp_id;

  // Consulta razão social da empresa
  if ($conn->connect_error) {
      die("Erro de conexão: " . $conn->connect_error);
  }

  if ($emp_id) {
      $stmt = $conn->prepare("SELECT e.emp_razao_social, e.emp_nome_fantasia, e.emp_cnpj, e.emp_insc_social, e.emp_tipo, e.end_id,
                                            en.end_cep, en.end_rua, en.end_nmr, en.end_bairro, en.end_cidade, en.end_uf, en.end_pais
                                     FROM empresa e INNER JOIN endereco en ON e.end_id = en.end_id WHERE emp_id = ?
                                     ");
      if ($stmt) {
          $stmt->bind_param("i", $emp_id);
          $stmt->execute();
          $stmt->bind_result(
            $razao_social, $nome_fantasia, $cnpj, $insc_social, $tipo, $end_id,
            $cep, $rua, $numero, $bairro, $cidade, $uf, $pais
          );
          $stmt->fetch();
          $stmt->close();
      }
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
        <section class="form-card">
          <h1>Minha Empresa</h1>
          <div class="input-group two-label-input">
            <div class="campo">
              <label for="razao_social">Razão Social</label>
              <input type="text" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($razao_social); ?>" readonly>
            </div>
            <div class="campo">
              <label for="nome_fantasia">Nome Fantasia</label>
              <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?php echo htmlspecialchars($nome_fantasia); ?>" readonly>
            </div>
          </div>

          <div class="input-group two-label-input">
            <div class="campo">
              <label for="cnpj">CNPJ</label>
              <input type="text" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($cnpj); ?>" readonly>
            </div>
            <div class="campo">
              <label for="insc_social">Inscrição Social</label>
              <input type="text" id="insc_social" name="insc_social" value="<?php echo htmlspecialchars($insc_social); ?>" readonly>
            </div>
          </div>

          <label for="cep">CEP</label>
          <div class="input-group">
            <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cep); ?>" readonly>
          </div>

          <div class="input-group two-label-input">
            <div class="campo">
              <label for="rua">Rua</label>
              <input type="text" id="rua" name="rua" value="<?php echo htmlspecialchars($rua); ?>" readonly>
            </div>
            <div class="campo">
              <label for="nmr">Número</label>
              <input type="text" id="nmr" name="nmr" value="<?php echo htmlspecialchars($numero); ?>" readonly>
            </div>
          </div>

          <div class="input-group two-label-input">
            <div class="campo">
              <label for="bairro">Bairro</label>
              <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($bairro); ?>" readonly>
            </div>
            <div class="campo">
              <label for="cidade">Cidade</label>
              <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>" readonly>
            </div>
          </div>

          <div class="input-group two-label-input">
            <div class="campo">
              <label for="uf">Estado</label>
              <input type="text" id="uf" name="uf" value="<?php echo htmlspecialchars($uf); ?>" readonly>
            </div>
            <div class="campo">
              <label for="pais">País</label>
              <input type="text" id="pais" name="pais" value="<?php echo htmlspecialchars($pais); ?>" readonly>
            </div>
          </div>

        </section>
      </main>
    </div>

    <script>
      document.getElementById('addRow').addEventListener('click', function () {
        const tbody = document.getElementById('tipoBody');
        const newRow = document.createElement('tr');

        newRow.innerHTML = `
          <td><div class="input-group"><div class="campo">
            <input type="text" name="nova_descricao[]" placeholder="Nova categoria" readonly>
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