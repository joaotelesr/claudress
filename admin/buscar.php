<?php
/**
 * buscar.php - JSON para o autocomplete da nova venda: ?tipo=produto|cliente&q=texto
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';
require_once RAIZ_PROJETO . '/funcoes/clientes_funcoes.php';

header('Content-Type: application/json; charset=utf-8');

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
            ];
        }
    } elseif ($tipo === 'cliente') {
        foreach (listarClientes(['busca' => $q, 'status' => 'Ativo'], 10, 0) as $c) {
            $out[] = ['id' => (int) $c['CODIGO'], 'nome' => trim((string) $c['NOME']), 'cpf' => $c['CPF'] ? formatarCpf($c['CPF']) : ''];
        }
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
