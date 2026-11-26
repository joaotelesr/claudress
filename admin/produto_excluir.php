<?php
/**
 * produto_excluir.php - "Exclui" um produto (somente via POST).
 * O produto não é apagado do banco: fica com STATUS_PRODUTO = 'Cancelado', como no sistema atual da loja.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

if (!ehPost()) {
    redirecionar('produtos.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$produto = buscarProduto($id);

if ($produto && inativarProduto($id)) {
    definirMensagem('sucesso', 'Produto "' . trim((string) $produto['DESCRICAO']) . '" cancelado.');
} else {
    definirMensagem('erro', 'Produto não encontrado ou já cancelado.');
}

redirecionar('produtos.php');
