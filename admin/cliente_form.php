<?php
/**
 * cliente_form.php - Cadastro e edição de cliente, com resumo de compras
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

$id    = (int) ($_GET['id'] ?? 0);
$linha = $id > 0 ? buscarCliente($id) : null;

if ($id > 0 && $linha === null) {
    definirMensagem('erro', 'Cliente não encontrado.');
    redirecionar('clientes.php');
}

$editando = $linha !== null;
$dados    = $editando ? clienteParaFormulario($linha, contatosDoCliente($id)) : clienteVazio();
$cpfAtual = $editando ? $dados['cpf'] : '';
$erros    = [];

if (ehPost()) {
    $dados = montarCliente($_POST, $editando ? $id : 0);
    $erros = validarCliente($dados, $cpfAtual);

    if (empty($erros)) {
        try {
            salvarCliente($dados);
            definirMensagem('sucesso', $editando ? 'Cliente atualizado com sucesso.' : 'Cliente cadastrado com sucesso.');
            redirecionar('clientes.php');
        } catch (PDOException $e) {
            $erros['geral'] = 'Não foi possível gravar no banco de dados.' . (DB_MOSTRAR_ERROS ? ' ' . $e->getMessage() : '');
        }
    }
}

$compras      = $editando ? resumoComprasCliente($id) : null;
$tituloPagina = $editando ? 'Editar cliente #' . $id : 'Novo cliente';
$cssPagina    = ['cadastros.css', 'vendas.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert">
        <span><?= isset($erros['geral']) ? e($erros['geral']) : 'Corrija os campos destacados e tente novamente.' ?></span>
    </div>
<?php endif; ?>

<?php if ($compras): ?>
    <section class="grade-indicadores indicadores-compactos">
        <div class="indicador">
            <span>
                <span class="indicador-rotulo">Compras</span>
                <strong class="indicador-valor"><?= (int) $compras['resumo']['QTD'] ?></strong>
            </span>
        </div>
        <div class="indicador">
            <span>
                <span class="indicador-rotulo">Total gasto</span>
                <strong class="indicador-valor"><?= formatarMoeda($compras['resumo']['TOTAL']) ?></strong>
            </span>
        </div>
        <div class="indicador">
            <span>
                <span class="indicador-rotulo">Última compra</span>
                <strong class="indicador-valor"><?= $compras['resumo']['ULTIMA'] ? formatarData($compras['resumo']['ULTIMA']) : '-' ?></strong>
            </span>
        </div>
    </section>
<?php endif; ?>

<form method="post" class="cartao cartao-formulario">
    <div class="formulario-grade">

        <div class="campo campo-largo">
            <label for="nome">Nome completo *</label>
            <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" maxlength="100" required>
            <?= erroCampo($erros, 'nome') ?>
        </div>

        <div class="campo">
            <label for="cpf">CPF</label>
            <input type="text" id="cpf" name="cpf" data-mascara="cpf" value="<?= e(formatarCpf($dados['cpf'])) ?>" inputmode="numeric" placeholder="000.000.000-00">
            <?= erroCampo($erros, 'cpf') ?>
        </div>

        <div class="campo">
            <label for="data_nasc">Nascimento</label>
            <input type="date" id="data_nasc" name="data_nasc" value="<?= e(substr((string) $dados['data_nasc'], 0, 10)) ?>">
            <?= erroCampo($erros, 'data_nasc') ?>
        </div>

        <div class="campo">
            <label for="sexo">Sexo</label>
            <select id="sexo" name="sexo">
                <option value="">Não informado</option>
                <option value="F" <?= $dados['sexo'] === 'F' ? 'selected' : '' ?>>Feminino</option>
                <option value="M" <?= $dados['sexo'] === 'M' ? 'selected' : '' ?>>Masculino</option>
            </select>
            <?= erroCampo($erros, 'sexo') ?>
        </div>

        <div class="campo">
            <label for="celular">Celular</label>
            <input type="text" id="celular" name="celular" data-mascara="telefone" value="<?= e(formatarTelefone($dados['celular'])) ?>" inputmode="tel" placeholder="(00) 00000-0000">
            <?= erroCampo($erros, 'celular') ?>
        </div>

        <div class="campo">
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp" data-mascara="telefone" value="<?= e(formatarTelefone($dados['whatsapp'])) ?>" inputmode="tel" placeholder="(00) 00000-0000">
            <?= erroCampo($erros, 'whatsapp') ?>
        </div>

        <div class="campo">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= e($dados['email']) ?>" maxlength="100">
            <?= erroCampo($erros, 'email') ?>
        </div>

        <div class="campo">
            <label for="instagram">Instagram</label>
            <input type="text" id="instagram" name="instagram" value="<?= e($dados['instagram']) ?>" maxlength="100" placeholder="sem @">
            <?= erroCampo($erros, 'instagram') ?>
        </div>

        <div class="campo">
            <label for="cep">CEP</label>
            <input type="text" id="cep" name="cep" data-mascara="cep" value="<?= e($dados['cep']) ?>" inputmode="numeric" placeholder="00000-000">
            <?= erroCampo($erros, 'cep') ?>
        </div>

        <div class="campo campo-largo">
            <label for="endereco">Endereço</label>
            <input type="text" id="endereco" name="endereco" value="<?= e($dados['endereco']) ?>" maxlength="100">
            <?= erroCampo($erros, 'endereco') ?>
        </div>

        <div class="campo">
            <label for="numero">Número</label>
            <input type="text" id="numero" name="numero" value="<?= e($dados['numero']) ?>" maxlength="10">
            <?= erroCampo($erros, 'numero') ?>
        </div>

        <div class="campo">
            <label for="complemento">Complemento</label>
            <input type="text" id="complemento" name="complemento" value="<?= e($dados['complemento']) ?>" maxlength="50">
        </div>

        <div class="campo">
            <label for="bairro">Bairro</label>
            <input type="text" id="bairro" name="bairro" value="<?= e($dados['bairro']) ?>" maxlength="50">
        </div>

        <div class="campo">
            <label for="cidade">Cidade</label>
            <input type="text" id="cidade" name="cidade" value="<?= e($dados['cidade']) ?>" maxlength="50">
        </div>

        <div class="campo">
            <label for="uf">UF</label>
            <input type="text" id="uf" name="uf" value="<?= e($dados['uf']) ?>" maxlength="2" style="text-transform: uppercase">
            <?= erroCampo($erros, 'uf') ?>
        </div>

        <?php if ($editando): ?>
            <div class="campo">
                <label for="status">Situação</label>
                <select id="status" name="status">
                    <?php foreach (['Ativo', 'Pendente', 'Cancelado'] as $s): ?>
                        <option value="<?= $s ?>" <?= $dados['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

    </div>

    <div class="formulario-acoes">
        <a href="clientes.php" class="botao botao-secundario">Cancelar</a>
        <button type="submit" class="botao botao-primario">Salvar cliente</button>
    </div>
</form>

<?php if ($compras && !empty($compras['ultimas'])): ?>
    <section class="cartao">
        <div class="cartao-titulo"><h2>Últimas compras</h2>
            <a href="vendas.php?busca=<?= urlencode($dados['nome']) ?>" class="botao botao-secundario botao-pequeno">Ver histórico</a>
        </div>
        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Venda</th><th>Data</th><th>Peças</th><th class="texto-direita">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($compras['ultimas'] as $v): ?>
                        <tr>
                            <td><a href="venda_detalhe.php?id=<?= (int) $v['CODIGO_VENDA'] ?>">#<?= (int) $v['CODIGO_VENDA'] ?></a></td>
                            <td><?= formatarData($v['DATA']) ?></td>
                            <td><?= e(formatarQuantidade($v['NUM_PECAS'])) ?></td>
                            <td class="texto-direita"><strong><?= formatarMoeda($v['TOTAL']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<script src="../JS/mascaras.js"></script>
<?php require __DIR__ . '/includes/rodape.php'; ?>
