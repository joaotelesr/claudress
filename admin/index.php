<?php
/**
 * index.php - Painel (versão de teste da Etapa 1)
 * Será substituído pelo dashboard completo na Etapa 2.
 */
require_once __DIR__ . '/../config/config.php';

$tituloPagina = 'Painel';

require __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina">
    <div>
        <h2>Bem-vinda ao sistema da <?= e(NOME_LOJA) ?></h2>
        <p>Estrutura base funcionando. O dashboard completo chega na próxima etapa.</p>
    </div>
    <a href="produtos.php" class="botao botao-primario">
        <?= icone('mais', 18) ?> Novo produto
    </a>
</div>

<div class="cartao">
    <div class="cartao-titulo">
        <h2>Dados de demonstração carregados</h2>
        <span class="badge badge-sucesso">Camada de dados OK</span>
    </div>

    <div class="tabela-container">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Coleção</th>
                    <th class="texto-direita">Registros</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Produtos</td><td class="texto-direita"><?= count(repoListar('produtos')) ?></td></tr>
                <tr><td>Clientes</td><td class="texto-direita"><?= count(repoListar('clientes')) ?></td></tr>
                <tr><td>Vendas</td><td class="texto-direita"><?= count(repoListar('vendas')) ?></td></tr>
                <tr><td>Formas de pagamento</td><td class="texto-direita"><?= count(repoListar('formas_pagamento')) ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="cartao">
    <div class="cartao-titulo"><h2>Pré-visualização dos componentes</h2></div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <button class="botao botao-primario">Primário</button>
        <button class="botao botao-secundario">Secundário</button>
        <button class="botao botao-perigo"><?= icone('excluir', 16) ?> Excluir</button>
        <span class="badge">Vestidos</span>
        <span class="badge badge-sucesso">Em estoque</span>
        <span class="badge badge-aviso">Estoque baixo</span>
    </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>