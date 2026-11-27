<?php
/**
 * produtos.php - Listagem de produtos (tabela PRODUTOS) com busca (também pelo fornecedor), filtros e paginação
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

$filtros = filtrosProdutos($_GET);
$total   = contarProdutos($filtros);
$pg      = paginar($total, ITENS_POR_PAGINA, (int) ($_GET['pagina'] ?? 1));
$produtos = listarProdutos($filtros, $pg['por_pagina'], $pg['offset']);
$grupos   = listarGrupos();
$fornecedores = listarFornecedores($filtros['fornecedor']);

$tituloPagina = 'Produtos';
$cssPagina    = ['cadastros.css', 'paginacao.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p><?= number_format($total, 0, ',', '.') ?> produto(s) encontrado(s)</p>
    <a href="produto_form.php" class="botao botao-primario"><?= icone('mais', 18) ?> Novo produto</a>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Nome, referência, código ou fornecedor</label>
            <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>" placeholder="Ex.: vestido, 026090, nome do fornecedor">
        </div>

        <div class="campo">
            <label for="grupo">Categoria</label>
            <select id="grupo" name="grupo">
                <option value="">Todas as categorias</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= (int) $g['CODIGO'] ?>" <?= (int) $g['CODIGO'] === $filtros['grupo'] ? 'selected' : '' ?>><?= e($g['GRUPO']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="fornecedor">Fornecedor</label>
            <select id="fornecedor" name="fornecedor">
                <option value="">Todos os fornecedores</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int) $f['CODIGO'] ?>" <?= (int) $f['CODIGO'] === $filtros['fornecedor'] ? 'selected' : '' ?>><?= e($f['NOME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="estoque">Estoque</label>
            <select id="estoque" name="estoque">
                <option value="" <?= $filtros['estoque'] === '' ? 'selected' : '' ?>>Todos</option>
                <option value="com" <?= $filtros['estoque'] === 'com' ? 'selected' : '' ?>>Com estoque</option>
                <option value="sem" <?= $filtros['estoque'] === 'sem' ? 'selected' : '' ?>>Zerado</option>
            </select>
        </div>

        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="Ativo" <?= $filtros['status'] === 'Ativo' ? 'selected' : '' ?>>Ativos</option>
                <option value="Cancelado" <?= $filtros['status'] === 'Cancelado' ? 'selected' : '' ?>>Cancelados</option>
                <option value="" <?= $filtros['status'] === '' ? 'selected' : '' ?>>Todos</option>
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
                        <th>Cód.</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Fornecedor</th>
                        <th>Tam.</th>
                        <th>Custo</th>
                        <th>Venda</th>
                        <th>Estoque</th>
                        <th class="texto-direita">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                        <tr>
                            <td>#<?= (int) $p['CODIGO'] ?></td>
                            <td class="celula-nome">
                                <strong><?= e(trim((string) $p['DESCRICAO'])) ?></strong>
                                <small>
                                    <?= $p['REFERENCIA'] ? 'Ref. ' . e($p['REFERENCIA']) : '' ?>
                                    <?= $p['STATUS_PRODUTO'] !== 'Ativo' ? ' ' . badgeStatus($p['STATUS_PRODUTO']) : '' ?>
                                </small>
                            </td>
                            <td><?= $p['GRUPO'] ? '<span class="badge">' . e($p['GRUPO']) . '</span>' : '-' ?></td>
                            <td><?= $p['FORNECEDOR'] !== '' && $p['FORNECEDOR'] !== null ? e($p['FORNECEDOR']) : '-' ?></td>
                            <td><?= e($p['TAMANHO'] ?: '-') ?></td>
                            <td><?= formatarMoeda($p['PRE_CUS']) ?></td>
                            <td><strong><?= formatarMoeda($p['PRE_VEN']) ?></strong></td>
                            <td><?= badgeEstoque($p['QUANT']) ?></td>
                            <td>
                                <div class="acoes">
                                    <a href="produto_form.php?id=<?= (int) $p['CODIGO'] ?>" class="botao botao-secundario botao-pequeno" title="Editar">
                                        <?= icone('editar', 16) ?>
                                    </a>
                                    <?php if ($p['STATUS_PRODUTO'] === 'Ativo'): ?>
                                        <form method="post" action="produto_excluir.php" class="form-inline"
                                              data-confirmar="Cancelar o produto &quot;<?= e(trim((string) $p['DESCRICAO'])) ?>&quot;? Ele sai da lista de ativos, mas o histórico de vendas é mantido.">
                                            <input type="hidden" name="id" value="<?= (int) $p['CODIGO'] ?>">
                                            <button type="submit" class="botao botao-perigo botao-pequeno" title="Cancelar produto">
                                                <?= icone('excluir', 16) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php renderPaginacao($pg, $filtros); ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
