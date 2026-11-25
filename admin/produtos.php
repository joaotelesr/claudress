<?php
/**
 * produtos.php - Listagem de produtos com busca por nome e filtro por categoria
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

$busca     = trim($_GET['busca'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

$produtos   = listarProdutos($busca, $categoria);
$categorias = listarCategorias();

$tituloPagina = 'Produtos';
$cssPagina    = ['cadastros.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p><?= count($produtos) ?> produto(s) encontrado(s)</p>
    <a href="produto_form.php" class="botao botao-primario"><?= icone('mais', 18) ?> Novo produto</a>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Pesquisar por nome</label>
            <input type="search" id="busca" name="busca" value="<?= e($busca) ?>" placeholder="Ex.: vestido">
        </div>

        <div class="campo">
            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $item): ?>
                    <option value="<?= e($item) ?>" <?= $item === $categoria ? 'selected' : '' ?>><?= e($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="produtos.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <?php if (empty($produtos)): ?>
        <p class="estado-vazio">Nenhum produto encontrado.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Tam.</th>
                        <th>Cor</th>
                        <th>Custo</th>
                        <th>Venda</th>
                        <th>Estoque</th>
                        <th class="texto-direita">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                        <tr>
                            <td>#<?= (int) $p['id'] ?></td>
                            <td class="celula-nome">
                                <strong><?= e($p['nome']) ?></strong>
                                <small><?= e(mb_strimwidth($p['descricao'], 0, 60, '…')) ?></small>
                            </td>
                            <td><span class="badge"><?= e($p['categoria']) ?></span></td>
                            <td><?= e($p['tamanho']) ?></td>
                            <td><?= e($p['cor']) ?></td>
                            <td><?= formatarMoeda($p['preco_custo']) ?></td>
                            <td><strong><?= formatarMoeda($p['preco_venda']) ?></strong></td>
                            <td><?= badgeEstoque((int) $p['estoque']) ?></td>
                            <td>
                                <div class="acoes">
                                    <a href="produto_form.php?id=<?= (int) $p['id'] ?>" class="botao botao-secundario botao-pequeno" title="Editar">
                                        <?= icone('editar', 16) ?>
                                    </a>
                                    <form method="post" action="produto_excluir.php" class="form-inline"
                                          data-confirmar="Excluir o produto &quot;<?= e($p['nome']) ?>&quot;?">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="botao botao-perigo botao-pequeno" title="Excluir">
                                            <?= icone('excluir', 16) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>