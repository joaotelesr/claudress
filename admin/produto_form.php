<?php
/**
 * produto_form.php - Cadastro e edição de produto (tabela PRODUTOS)
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

$id      = (int) ($_GET['id'] ?? 0);
$linha   = $id > 0 ? buscarProduto($id) : null;

if ($id > 0 && $linha === null) {
    definirMensagem('erro', 'Produto não encontrado.');
    redirecionar('produtos.php');
}

$editando = $linha !== null;
$dados    = $editando ? produtoParaFormulario($linha) : produtoVazio();
$erros    = [];

if (ehPost()) {
    $dados = montarProduto($_POST, $editando ? $id : 0);
    $erros = validarProduto($dados);

    if (empty($erros)) {
        try {
            salvarProduto($dados);
            definirMensagem('sucesso', $editando ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
            redirecionar('produtos.php');
        } catch (PDOException $e) {
            $erros['geral'] = 'Não foi possível gravar no banco de dados.' . (DB_MOSTRAR_ERROS ? ' ' . $e->getMessage() : '');
        }
    }
}

$grupos       = listarGrupos((int) $dados['grupo']);
$subgrupos    = listarSubgrupos();
$tituloPagina = $editando ? 'Editar produto #' . $id : 'Novo produto';
$cssPagina    = ['cadastros.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert">
        <span><?= isset($erros['geral']) ? e($erros['geral']) : 'Corrija os campos destacados e tente novamente.' ?></span>
    </div>
<?php endif; ?>

<form method="post" class="cartao cartao-formulario">
    <div class="formulario-grade">

        <div class="campo campo-largo">
            <label for="nome">Nome do produto *</label>
            <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" maxlength="200" required>
            <?= erroCampo($erros, 'nome') ?>
        </div>

        <div class="campo">
            <label for="grupo">Categoria *</label>
            <select id="grupo" name="grupo" required>
                <option value="">Escolha...</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= (int) $g['CODIGO'] ?>" <?= (string) $g['CODIGO'] === (string) $dados['grupo'] ? 'selected' : '' ?>><?= e($g['GRUPO']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erroCampo($erros, 'grupo') ?>
        </div>

        <div class="campo">
            <label for="subgrupo">Subgrupo</label>
            <select id="subgrupo" name="subgrupo">
                <option value="">Nenhum</option>
                <?php foreach ($subgrupos as $s): ?>
                    <option value="<?= (int) $s['CODIGO'] ?>" <?= (string) $s['CODIGO'] === (string) $dados['subgrupo'] ? 'selected' : '' ?>><?= e($s['DESCRICAO']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erroCampo($erros, 'subgrupo') ?>
        </div>

        <div class="campo">
            <label for="tamanho">Tamanho *</label>
            <input type="text" id="tamanho" name="tamanho" value="<?= e($dados['tamanho']) ?>" maxlength="5" placeholder="P, M, G, 38, PLUS..." required>
            <?= erroCampo($erros, 'tamanho') ?>
        </div>

        <div class="campo">
            <label for="referencia">Referência</label>
            <input type="text" id="referencia" name="referencia" value="<?= e($dados['referencia']) ?>" maxlength="50"
                   placeholder="<?= $editando ? '' : 'Em branco = usa o código do produto' ?>">
            <?= erroCampo($erros, 'referencia') ?>
        </div>

        <div class="campo">
            <label for="preco_custo">Preço de custo (R$) *</label>
            <input type="number" id="preco_custo" name="preco_custo" min="0" step="0.01" value="<?= e($dados['preco_custo']) ?>" required>
            <?= erroCampo($erros, 'preco_custo') ?>
        </div>

        <div class="campo">
            <label for="preco_venda">Preço de venda (R$) *</label>
            <input type="number" id="preco_venda" name="preco_venda" min="0.01" step="0.01" value="<?= e($dados['preco_venda']) ?>" required>
            <?= erroCampo($erros, 'preco_venda') ?>
        </div>

        <div class="campo">
            <label for="estoque">Quantidade em estoque *</label>
            <input type="number" id="estoque" name="estoque" min="0" step="1" value="<?= e($dados['estoque']) ?>" required>
            <?= erroCampo($erros, 'estoque') ?>
        </div>

        <?php if ($editando): ?>
            <div class="campo">
                <label for="status">Situação</label>
                <select id="status" name="status">
                    <option value="Ativo" <?= $dados['status'] === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="Cancelado" <?= $dados['status'] === 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
                <?= erroCampo($erros, 'status') ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="formulario-acoes">
        <a href="produtos.php" class="botao botao-secundario">Cancelar</a>
        <button type="submit" class="botao botao-primario">Salvar produto</button>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>
