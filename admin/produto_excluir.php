<?php
/**
 * produto_excluir.php - Processa a exclusão de um produto (somente via POST)
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

if (!ehPost()) {
    redirecionar('produtos.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$produto = buscarProduto($id);

if ($produto && excluirProduto($id)) {
    definirMensagem('sucesso', 'Produto "' . $produto['nome'] . '" excluído.');
} else {
    definirMensagem('erro', 'Produto não encontrado.');
}

redirecionar('produtos.php');