<?php
/**
 * clientes.php - Listagem de clientes com busca por nome ou telefone
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

$busca    = trim($_GET['busca'] ?? '');
$clientes = listarClientes($busca);

$tituloPagina = 'Clientes';
$cssPagina    = ['cadastros.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <p><?= count($clientes) ?> cliente(s) encontrado(s)</p>
    <a href="cliente_form.php" class="botao botao-primario"><?= icone('mais', 18) ?> Novo cliente</a>
</div>

<div class="cartao">
    <form method="get" class="barra-filtros">
        <div class="campo">
            <label for="busca">Pesquisar por nome ou telefone</label>
            <input type="search" id="busca" name="busca" value="<?= e($busca) ?>" placeholder="Ex.: Mariana ou 98765">
        </div>

        <div class="barra-filtros-botoes">
            <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Buscar</button>
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
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Telefone</th>
                        <th>WhatsApp</th>
                        <th>E-mail</th>
                        <th>Endereço</th>
                        <th class="texto-direita">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td>#<?= (int) $c['id'] ?></td>
                            <td class="celula-nome">
                                <strong><?= e($c['nome']) ?></strong>
                                <?php if ($c['cpf'] !== ''): ?><small>CPF <?= e($c['cpf']) ?></small><?php endif; ?>
                            </td>
                            <td><?= $c['telefone'] !== '' ? e($c['telefone']) : '-' ?></td>
                            <td>
                                <?php if ($c['whatsapp'] !== ''): ?>
                                    <a class="link-whatsapp" href="https://wa.me/55<?= e(somenteDigitos($c['whatsapp'])) ?>"
                                       target="_blank" rel="noopener"><?= e($c['whatsapp']) ?></a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><?= $c['email'] !== '' ? e($c['email']) : '-' ?></td>
                            <td><?= $c['endereco'] !== '' ? e($c['endereco']) : '-' ?></td>
                            <td>
                                <div class="acoes">
                                    <a href="cliente_form.php?id=<?= (int) $c['id'] ?>" class="botao botao-secundario botao-pequeno" title="Editar">
                                        <?= icone('editar', 16) ?>
                                    </a>
                                    <form method="post" action="cliente_excluir.php" class="form-inline"
                                          data-confirmar="Excluir a cliente &quot;<?= e($c['nome']) ?>&quot;? As vendas já registradas continuam no histórico.">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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