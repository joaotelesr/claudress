<?php
/**
 * produto_form.php - Cadastro e edição de produto
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

$id      = (int) ($_GET['id'] ?? 0);
$produto = $id > 0 ? buscarProduto($id) : null;

if ($id > 0 && $produto === null) {
    definirMensagem('erro', 'Produto não encontrado.');
    redirecionar('produtos.php');
}

$dados = $produto ?? produtoVazio();
$erros = [];

if (ehPost()) {
    $dados = montarProduto($_POST, $produto);
    $erros = validarProduto($dados);

    if (empty($erros)) {
        salvarProduto($dados);
        definirMensagem('sucesso', $produto ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
        redirecionar('produtos.php');
    }
}

$categorias   = listarCategorias();
$tituloPagina = $produto ? 'Editar produto' : 'Novo produto';
$cssPagina    = ['cadastros.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert"><span>Corrija os campos destacados e tente novamente.</span></div>
<?php endif; ?>

<form method="post" class="cartao cartao-formulario">
    <div class="formulario-grade">

        <div class="campo campo-largo">
            <label for="nome">Nome do produto *</label>
            <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" required>
            <?= erroCampo($erros, 'nome') ?>
        </div>

        <div class="campo campo-largo">
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao"><?= e($dados['descricao']) ?></textarea>
        </div>

        <div class="campo">
            <label for="categoria">Categoria *</label>
            <input type="text" id="categoria" name="categoria" list="lista-categorias"
                   value="<?= e($dados['categoria']) ?>" placeholder="Escolha ou digite uma nova" required>
            <datalist id="lista-categorias">
                <?php foreach ($categorias as $item): ?>
                    <option value="<?= e($item) ?>">
                <?php endforeach; ?>
            </datalist>
            <?= erroCampo($erros, 'categoria') ?>
        </div>

        <div class="campo">
            <label for="tamanho">Tamanho *</label>
            <input type="text" id="tamanho" name="tamanho" value="<?= e($dados['tamanho']) ?>" placeholder="P, M, G, 38, Único..." required>
            <?= erroCampo($erros, 'tamanho') ?>
        </div>

        <div class="campo">
            <label for="cor">Cor *</label>
            <input type="text" id="cor" name="cor" value="<?= e($dados['cor']) ?>" required>
            <?= erroCampo($erros, 'cor') ?>
        </div>

        <div class="campo">
            <label for="estoque">Quantidade em estoque *</label>
            <input type="number" id="estoque" name="estoque" min="0" step="1" value="<?= e($dados['estoque']) ?>" required>
            <?= erroCampo($erros, 'estoque') ?>
        </div>

        <div class="campo">
            <label for="preco_custo">Preço de custo (R$) *</label>
            <input type="number" id="preco_custo" name="preco_custo" min="0" step="0.01" value="<?= e($dados['preco_custo']) ?>" required>
            <?= erroCampo($erros, 'preco_custo') ?>
        </div>

        <div class="campo">
            <label for="preco_venda">Preço de venda (R$) *</label>
            <input type="number" id="preco_venda" name="preco_venda" min="0" step="0.01" value="<?= e($dados['preco_venda']) ?>" required>
            <?= erroCampo($erros, 'preco_venda') ?>
        </div>

    </div>

    <div class="formulario-acoes">
        <a href="produtos.php" class="botao botao-secundario">Cancelar</a>
        <button type="submit" class="botao botao-primario">Salvar produto</button>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>