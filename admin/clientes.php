<?php
/**
 * clientes.php - Listagem de clientes (tabela CLIENTE + CLIENTE_CONTATOS)
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

$filtros  = filtrosClientes($_GET);
$total    = contarClientes($filtros);
$pg       = paginar($total, ITENS_POR_PAGINA, (int) ($_GET['pagina'] ?? 1));
$clientes = listarClientes($filtros, $pg['por_pagina'], $pg['offset']);

$tituloPagina = 'Clientes';
$cssPagina    = ['cadastros.css', 'paginacao.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p><?= number_format($total, 0, ',', '.') ?> cliente(s) encontrado(s)</p>
    <a href="cliente_form.php" class="botao botao-primario"><?= icone('mais', 18) ?> Novo cliente</a>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Nome, CPF ou telefone</label>
            <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>" placeholder="Ex.: Maria, 123.456">
        </div>
        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="Ativo" <?= $filtros['status'] === 'Ativo' ? 'selected' : '' ?>>Ativos</option>
                <option value="Pendente" <?= $filtros['status'] === 'Pendente' ? 'selected' : '' ?>>Pendentes</option>
                <option value="Cancelado" <?= $filtros['status'] === 'Cancelado' ? 'selected' : '' ?>>Cancelados</option>
                <option value="" <?= $filtros['status'] === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <label class="filtro-marcar" for="incompletos">
            <input type="checkbox" id="incompletos" name="incompletos" value="1" <?= ($filtros['incompletos'] ?? '') === '1' ? 'checked' : '' ?>>
            <span>Só dados incompletos</span>
        </label>
        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
            <a href="clientes.php" class="botao botao-secundario">Limpar</a>
        </div>
    </form>

    <?php if (empty($clientes)): ?>
        <p class="estado-vazio">Nenhum cliente encontrado.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Cód.</th>
                        <th>Cliente</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Cidade</th>
                        <th class="texto-direita">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <?php $fone = $c['WHATSAPP'] ?: $c['TELEFONE']; ?>
                        <tr>
                            <td>#<?= (int) $c['CODIGO'] ?></td>
                            <td class="celula-nome">
                                <strong><?= e(trim((string) $c['NOME'])) ?></strong>
                                <small>
                                    Cliente desde <?= $c['DATA_CADASTRO'] ? formatarData($c['DATA_CADASTRO']) : '-' ?>
                                    <?= $c['STATUS_CLI'] !== 'Ativo' ? ' ' . badgeStatus($c['STATUS_CLI']) : '' ?>
                                    <?php if (!empty($c['FALTANDO'])): ?>
                                        <span class="badge badge-aviso" title="Falta: <?= e(implode(', ', $c['FALTANDO'])) ?>">Incompleto</span>
                                    <?php endif; ?>
                                </small>
                                <?php if (!empty($c['FALTANDO']) && ($filtros['incompletos'] ?? '') === '1'): ?>
                                    <small class="faltando-lista">Falta: <?= e(implode(', ', $c['FALTANDO'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $c['CPF'] ? e(formatarCpf($c['CPF'])) : '-' ?></td>
                            <td><?= $fone ? e(formatarTelefone($fone)) : '-' ?></td>
                            <td><?= e(trim((string) $c['CIDADE']) ?: '-') ?></td>
                            <td>
                                <div class="acoes">
                                    <a href="cliente_form.php?id=<?= (int) $c['CODIGO'] ?>" class="botao botao-secundario botao-pequeno" title="<?= empty($c['FALTANDO']) ? 'Editar' : 'Editar e completar o cadastro' ?>">
                                        <?= icone('editar', 16) ?>
                                    </a>
                                    <?php if ($c['STATUS_CLI'] === 'Ativo'): ?>
                                        <form method="post" action="cliente_excluir.php" class="form-inline"
                                              data-confirmar="Cancelar o cadastro de &quot;<?= e(trim((string) $c['NOME'])) ?>&quot;? O histórico de compras é mantido.">
                                            <input type="hidden" name="id" value="<?= (int) $c['CODIGO'] ?>">
                                            <button type="submit" class="botao botao-perigo botao-pequeno" title="Cancelar cliente">
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
