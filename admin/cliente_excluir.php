<?php
/**
 * cliente_excluir.php - "Exclui" um cliente (somente via POST): STATUS_CLI passa a 'Cancelado'.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

if (!ehPost()) {
    redirecionar('clientes.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$cliente = buscarCliente($id);

if ($cliente && inativarCliente($id)) {
    definirMensagem('sucesso', 'Cliente "' . trim((string) $cliente['NOME']) . '" cancelado.');
} else {
    definirMensagem('erro', 'Cliente não encontrado ou já cancelado.');
}

redirecionar('clientes.php');
