<?php
/**
 * cliente_excluir.php - Processa a exclusão de um cliente (somente via POST)
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

if (!ehPost()) {
    redirecionar('clientes.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$cliente = buscarCliente($id);

if ($cliente && excluirCliente($id)) {
    definirMensagem('sucesso', 'Cliente "' . $cliente['nome'] . '" excluída.');
} else {
    definirMensagem('erro', 'Cliente não encontrado.');
}

redirecionar('clientes.php');