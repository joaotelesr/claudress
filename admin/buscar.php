<?php
/**
 * buscar.php - JSON para o autocomplete da nova venda: ?tipo=produto|cliente&q=texto
 * ?tipo=credito&id=CLIENTE devolve o saldo de crédito na loja da cliente: {"id": 1, "credito": 45.5}
 * (produto: procura no nome, referência, código e nome do fornecedor)
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/devolucoes_funcoes.php';

header('Content-Type: application/json; charset=utf-8');

// Saldo de crédito na loja de uma cliente
if (($_GET['tipo'] ?? '') === 'credito') {
    $id = (int) ($_GET['id'] ?? 0);
    echo json_encode(['id' => $id, 'credito' => saldoCredito($id)], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = $_GET['tipo'] ?? '';
$q    = trim((string) ($_GET['q'] ?? ''));
$out  = [];

if (mb_strlen($q) >= 2) {
    if ($tipo === 'produto') {
        $f = ['busca' => $q, 'grupo' => 0, 'estoque' => 'com', 'status' => 'Ativo'];
        foreach (listarProdutos($f, 10, 0) as $p) {
            $out[] = [
                'id' => (int) $p['CODIGO'], 'nome' => trim((string) $p['DESCRICAO']), 'tamanho' => trim((string) $p['TAMANHO']),
                'preco' => (float) $p['PRE_VEN'], 'estoque' => (float) $p['QUANT'],
                'fornecedor' => trim((string) ($p['FORNECEDOR'] ?? '')),
            ];
        }
    } elseif ($tipo === 'cliente') {
        foreach (listarClientes(['busca' => $q, 'status' => 'Ativo'], 10, 0) as $c) {
            $out[] = ['id' => (int) $c['CODIGO'], 'nome' => trim((string) $c['NOME']), 'cpf' => $c['CPF'] ? formatarCpf($c['CPF']) : ''];
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
