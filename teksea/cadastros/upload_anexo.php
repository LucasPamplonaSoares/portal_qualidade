<?php
// PARTE 1: LÓGICA, SEGURANÇA E BUSCA DE DADOS
session_start();
error_reporting(E_ALL); // Habilitar erros
ini_set('display_errors', 1);

// 1. Segurança
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['emp_id']) || $_SESSION['emp_id'] != 11) {
    header("Location: /login/");
    exit;
}

// 2. Conexão
require_once __DIR__ . '/../../config/conexao.php';

// 3. Busca das empresas (Mantido)
$empresas = [];
$sql_empresas = "SELECT emp_id, emp_razao_social FROM empresa ORDER BY emp_razao_social ASC";
$result_empresas = $conn->query($sql_empresas);
if ($result_empresas && $result_empresas->num_rows > 0) {
    while ($row = $result_empresas->fetch_assoc()) {
        $empresas[] = $row;
    }
}

// 4. Busca dos anexos (Mantido)
$anexos = [];
$sql_anexos = "SELECT 
                    a.anx_id, a.anx_nome, a.anx_arquivo, a.anx_status,
                    a.anx_data_criacao, a.anx_data_atualizacao,
                    a.anx_data_vencimento, a.anx_vitalicio,
                    t.tipo_descricao, e.emp_razao_social,
                    criador.user_nome AS criador_nome,
                    atualizador.user_nome AS atualizador_nome
                FROM anexo a
                LEFT JOIN tipo t ON a.tipo_id = t.tipo_id
                LEFT JOIN empresa e ON a.emp_id = e.emp_id
                LEFT JOIN usuario criador ON a.anx_criado_por_id = criador.user_id
                LEFT JOIN usuario atualizador ON a.user_id_atualizacao = atualizador.user_id
                ORDER BY a.anx_id DESC";
$result_anexos = $conn->query($sql_anexos);
if (!$result_anexos) { 
    die("Erro ao consultar anexos: " . $conn->error);
}
if ($result_anexos->num_rows > 0) {
    while ($row = $result_anexos->fetch_assoc()) {
        $anexos[] = $row;
    }
}
$status_map = [
    0 => 'Pendente', 1 => 'Aguardando Aprovação', 
    2 => 'Aprovado', 3 => 'Recusado'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Documentos - Portal TekSea</title>
    <link rel="stylesheet" href="/css/styles.css"> <script src="https://kit.fontawesome.com/90b9d5e3db.js" crossorigin="anonymous"></script>
    
    <style>
        /* 1. Correção dos Status (Texto Branco) */
        .status { 
            padding: 5px 10px; 
            border-radius: 12px; 
            font-size: 0.85em; 
            font-weight: bold; 
            color: #ffffff !important; 
            text-align: center; 
            display: inline-block;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.1); 
        }
        .status-pendente { background-color: #f0ad4e; }
        .status-aguardando { background-color: #0275d8; }
        .status-aprovado { background-color: #5cb85c; }
        .status-reprovado { background-color: #d9534f; }
        .status-default { background-color: #777; }

        /* 2. Correção do Botão "Selecionar Arquivo" (Texto Branco) */
        .upload-wrapper .btn-upload {
            background-color: var(--cor-verde, #385856) !important;
            color: white !important; 
            border: none !important;
            padding: 10px 15px !important;
            height: 41px;
            border-radius: 6px !important;
            font-size: 15px !important;
            font-weight: 500 !important;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            box-sizing: border-box;
        }
        .upload-wrapper .btn-upload:hover {
            background-color: var(--cor-verde-hover, #2c4543) !important;
        }
        .upload-wrapper .btn-upload i {
             margin-right: 8px;
        }

        /* (Resto do seu CSS existente) */
        .table-section .actions-cell a { 
            display: inline-flex !important; 
            align-items: center !important; 
            /* ... etc ... */
        }
        /* --- ESTILOS DOS BOTÕES DE AÇÃO NA TABELA --- */
        .table-section .actions-cell a.btn-editar { background-color: #ffc107 !important; color: #333 !important; }
        .table-section .actions-cell a.btn-download { background-color: #0275d8 !important; color: white !important; }
        .table-section .actions-cell a.btn-excluir { background-color: #d9534f !important; color: white !important; }

        .vencimento-vitalicio { font-weight: bold; color: var(--cor-verde, #385856); }
        .input-group:not(.two-label-input) .campo { flex-basis: 100%; }
        
        /* Estilo para o seletor Sim/Não */
        .grupo-vitalicio { margin-top: 10px; }
        .etiqueta-grupo { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #333; }
        .opcoes-vitalicio { display: flex; width: 100%; }
        .opcoes-vitalicio input[type="radio"] { display: none; }
        .opcoes-vitalicio label { padding: 10px 12px; border: 1px solid var(--cor-cinza-borda, #ddd); cursor: pointer; transition: background-color 0.3s ease, color 0.3s ease; font-size: 15px; color: #555; background-color: #f9f9f9; text-align: center; flex-grow: 1; flex-basis: 0; }
        .opcoes-vitalicio label[for="cad_vitalicio_sim"] { border-top-left-radius: 6px; border-bottom-left-radius: 6px; border-right: none; }
        .opcoes-vitalicio label[for="cad_vitalicio_nao"] { border-top-right-radius: 6px; border-bottom-right-radius: 6px; }
        .opcoes-vitalicio input[type="radio"]:checked + label { background-color: var(--cor-verde, #385856); color: #ffffff; border-color: var(--cor-verde, #385856); font-weight: bold; }
        
        select:disabled { background-color: #eee; color: #999; cursor: not-allowed; }

        /* === CSS ADICIONADO PARA DURAÇÃO === */
        .vencimento-info { display: block; line-height: 1.4; white-space: nowrap; }
        .vencimento-duracao { font-size: 0.85em; color: #555; font-style: italic; }
        .vencimento-vencido { color: #d9534f; font-weight: bold; }
        .vencimento-hoje { color: #f0ad4e; font-weight: bold; }
        /* === FIM CSS ADICIONADO === */
    </style>
    </head>
<body>
    <div class="container">
        <nav class="sidebar">
            <?php require_once __DIR__ . '/../navbar.php'; ?>
        </nav>

        <main class="content">
            <?php
            if (isset($_SESSION['mensagem'])) {
                $is_error = strpos(strtolower($_SESSION['mensagem']), 'erro') !== false;
                $message_class = $is_error ? 'mensagem-erro' : 'mensagem-sucesso';
                echo "<div class='mensagem {$message_class}'>" . htmlspecialchars($_SESSION['mensagem']) . "</div>";
                unset($_SESSION['mensagem']);
            }
            ?>
            <header>
                <h1>Cadastro de Documentos</h1>
            </header>

            <section class="form-card">
                <h2>Enviar Novo Anexo</h2>
                <form action="processa_upload.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="input-group two-label-input">
                         <div class="campo">
                            <label for="anx_nome">Nome do Documento</label>
                            <input type="text" id="anx_nome" name="anx_nome" required>
                         </div>
                         
                         <div class="campo">
                            <label for="emp_id">Empresa</label>
                            <select id="emp_id" name="emp_id" required>
                                <option value="" disabled selected>Selecione uma empresa</option>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= htmlspecialchars($empresa['emp_id']) ?>"><?= htmlspecialchars($empresa['emp_razao_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                    </div>
                    
                    <div class="input-group two-label-input">
                         
                         <div class="campo">
                            <label for="tipo_id">Categoria do Documento</label>
                            <select id="tipo_id" name="tipo_id" required disabled>
                                <option value="" disabled selected>Selecione uma empresa primeiro</option>
                            </select>
                         </div>

                         <div class="campo">
                            <label for="anx_data_vencimento">Data de Vencimento (Opcional)</label>
                            <input type="date" id="anx_data_vencimento" name="anx_data_vencimento">
                         </div>
                    </div>

                    <div class="input-group">
                        <div class="campo">
                             <div class="grupo-vitalicio">
                                <span class="etiqueta-grupo">Documento Vitalício?</span>
                                <div class="opcoes-vitalicio">
                                    <input type="radio" id="cad_vitalicio_sim" name="anx_vitalicio" value="1">
                                    <label for="cad_vitalicio_sim">Sim</label>

                                    <input type="radio" id="cad_vitalicio_nao" name="anx_vitalicio" value="0" checked>
                                    <label for="cad_vitalicio_nao">Não</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="input-group"> <div class="campo">
                            <label for="anx_arquivo">Arquivo</label>
                            <div class="upload-wrapper">
                                <input type="file" id="anx_arquivo" name="anx_arquivo" required hidden>
                                <label for="anx_arquivo" class="btn-upload"> <i class="fas fa-upload"></i> Selecionar Arquivo 
                                </label>
                                <span id="nome-arquivo" class="file-name">Nenhum arquivo selecionado</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Enviar Anexo</button>
                </form>
            </section>
            
            <section class="card table-section">
                <h3>Documentos Enviados</h3>
                <table>
                   <thead>
                        <tr>
                            <th>Nome do Documento</th>
                            <th>Empresa</th>
                            <th>Status</th>
                            <th>Vencimento / Duração</th> 
                            <th>Data de Envio</th>
                            <th>Criado por</th>
                            <th>Última Atualização</th>
                            <th>Atualizado por</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($anexos)): ?>
                            <?php foreach ($anexos as $anexo): ?>
                                <?php
                                    $status_id = $anexo['anx_status'] ?? -1;
                                    $status_texto = $status_map[$status_id] ?? 'Desconhecido';
                                    $status_class = 'status-default';
                                    if ($status_id == 0) $status_class = 'status-pendente';
                                    if ($status_id == 1) $status_class = 'status-aguardando';
                                    if ($status_id == 2) $status_class = 'status-aprovado';
                                    if ($status_id == 3) $status_class = 'status-reprovado';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($anexo['anx_nome']) ?></td>
                                    <td><?= htmlspecialchars($anexo['emp_razao_social'] ?? 'N/A') ?></td>
                                    <td><span class="status <?= $status_class ?>"><?= $status_texto ?></span></td>
                                    
                                    <td>
                                        <?php
                                        if (!empty($anexo['anx_vitalicio']) && $anexo['anx_vitalicio'] == 1) {
                                            echo '<span class="vencimento-info vencimento-vitalicio">Vitalício</span>';
                                        
                                        } elseif (empty($anexo['anx_data_vencimento'])) {
                                            echo '-';
                                        
                                        } else {
                                            try {
                                                $hoje = new DateTime(); 
                                                $hoje->setTime(0, 0, 0);
                                                
                                                $vencimento = new DateTime($anexo['anx_data_vencimento']);
                                                $data_formatada = $vencimento->format('d/m/Y');
                                                
                                                echo '<span class="vencimento-info">' . $data_formatada;

                                                // Lógica de cálculo de diferença
                                                $diff = $hoje->diff($vencimento);
                                                $diff_dias_total = (int)$diff->format('%R%a'); 

                                                if ($diff_dias_total < 0) {
                                                    echo '<br><span class="vencimento-duracao vencimento-vencido">(Vencido há ' . abs($diff_dias_total) . ' dias)</span>';
                                                } elseif ($diff_dias_total == 0) {
                                                    echo '<br><span class="vencimento-duracao vencimento-hoje">(Vence hoje)</span>';
                                                } else {
                                                    $duracao_str = 'Vence em ';
                                                    if ($diff->y > 0) {
                                                        $duracao_str .= $diff->y . ($diff->y > 1 ? ' anos' : ' ano');
                                                    } elseif ($diff->m > 0) {
                                                        $duracao_str .= $diff->m . ($diff->m > 1 ? ' meses' : ' mês');
                                                    } else {
                                                        $duracao_str .= $diff->d . ($diff->d > 1 ? ' dias' : ' dia');
                                                    }
                                                    echo '<br><span class="vencimento-duracao">(' . $duracao_str . ')</span>';
                                                }
                                                echo '</span>'; // Fecha o .vencimento-info

                                            } catch (Exception $e) { 
                                                echo 'Data inválida'; 
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?= !empty($anexo['anx_data_criacao']) ? date('d/m/Y H:i', strtotime($anexo['anx_data_criacao'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($anexo['criador_nome'] ?? 'N/A') ?></td>
                                    <td><?= !empty($anexo['anx_data_atualizacao']) ? date('d/m/Y H:i', strtotime($anexo['anx_data_atualizacao'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($anexo['atualizador_nome'] ?? 'N/A') ?></td>
                                    <td class="actions-cell">
                                         <a href="editar_anexo.php?anx_id=<?= $anexo['anx_id'] ?>" class="btn-editar" title="Editar"><i class="fa-solid fa-pencil"></i></a> 
                                         <a href="download_anexo.php?id=<?= $anexo['anx_id'] ?>" class="btn-download" title="Baixar"><i class="fa-solid fa-download"></i></a> 
                                         <a href="/teksea/exclusoes/excluir_anexo.php?id=<?= $anexo['anx_id'] ?>" onclick="return confirm('Tem certeza?')" class="btn-excluir" title="Excluir"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align: center;">Nenhum documento enviado ainda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script src="/js/script.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // (Script de nome de arquivo)
        const inputArquivo = document.getElementById('anx_arquivo');
        const nomeArquivoSpan = document.getElementById('nome-arquivo');
        if (inputArquivo) {
            inputArquivo.addEventListener('change', function () {
                nomeArquivoSpan.textContent = this.files.length > 0 ? this.files[0].name : 'Nenhum arquivo selecionado';
            });
        }

        // (Script de vitalício)
        const chkVitalicioSim = document.getElementById('cad_vitalicio_sim');
        const chkVitalicioNao = document.getElementById('cad_vitalicio_nao');
        const inputDataVenc = document.getElementById('anx_data_vencimento');

        if (chkVitalicioSim && chkVitalicioNao && inputDataVenc) {
            function toggleData(isVitalicio) {
                inputDataVenc.disabled = isVitalicio;
                if (isVitalicio) {
                    inputDataVenc.value = '';
                }
            }
            chkVitalicioSim.addEventListener('change', () => toggleData(true));
            chkVitalicioNao.addEventListener('change', () => toggleData(false));
        }

        // (Script de Dropdown Dependente)
        const selectEmpresa = document.getElementById('emp_id');
        const selectCategoria = document.getElementById('tipo_id');

        if (selectEmpresa && selectCategoria) {
            selectEmpresa.addEventListener('change', function() {
                const empId = this.value;
                
                selectCategoria.innerHTML = '<option value="" disabled selected>A carregar...</option>';
                selectCategoria.disabled = true;

                if (!empId) {
                    selectCategoria.innerHTML = '<option value="" disabled selected>Selecione uma empresa primeiro</option>';
                    return;
                }

                fetch('buscar_requisitos_por_empresa.php?emp_id=' + empId)
                    .then(response => {
                        if (!response.ok) { throw new Error('Erro na rede'); }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'error') { throw new Error(data.message); }
                        selectCategoria.innerHTML = ''; 

                        if (data.length === 0) {
                            selectCategoria.innerHTML = '<option value="" disabled selected>Nenhum requisito para esta empresa</option>';
                        } else {
                            selectCategoria.innerHTML = '<option value="" disabled selected>Selecione um documento</option>';
                            data.forEach(tipo => {
                                const option = document.createElement('option');
                                option.value = tipo.id;
                                option.textContent = tipo.nome;
                                selectCategoria.appendChild(option);
                            });
                            selectCategoria.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar categorias:', error);
                        selectCategoria.innerHTML = '<option value="" disabled selected>Erro ao carregar categorias</option>';
                    });
            });
        }
    });
    </script>

    <?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>