<?php
/**
 * produto_cadastro_rapido.php - JSON usado pela tela de produto para criar, sem sair da tela,
 * uma categoria, um subgrupo ou um fornecedor novo.
 * POST tipo=categoria|subgrupo|fornecedor, nome=texto (subgrupo também aceita grupo=código da categoria)
 * Resposta: {"ok":true,"id":12,"nome":"Vestidos","existia":false} ou {"ok":false,"erro":"..."}
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

header('Content-Type: application/json; charset=utf-8');

function responderCadastroRapido(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ehPost()) {
    responderCadastroRapido(['ok' => false, 'erro' => 'Use o formulário do produto.'], 405);
}

$tipo = (string) ($_POST['tipo'] ?? '');
$nome = (string) ($_POST['nome'] ?? '');

try {
    if ($tipo === 'categoria') {
        $r = criarCategoria($nome);
    } elseif ($tipo === 'subgrupo') {
        $r = criarSubgrupo($nome, (int) ($_POST['grupo'] ?? 0));
    } elseif ($tipo === 'fornecedor') {
        $r = criarFornecedor($nome);
    } else {
        responderCadastroRapido(['ok' => false, 'erro' => 'Tipo de cadastro inválido.'], 400);
    }
    responderCadastroRapido(['ok' => true] + $r);
} catch (InvalidArgumentException $e) {
    responderCadastroRapido(['ok' => false, 'erro' => $e->getMessage()], 422);
} catch (Throwable $e) {
    responderCadastroRapido([
        'ok'   => false,
        'erro' => 'Não foi possível gravar no banco de dados.' . (DB_MOSTRAR_ERROS ? ' ' . $e->getMessage() : ''),
    ], 500);
}
