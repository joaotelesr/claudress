<?php
/**
 * repositorio.php
 * CAMADA DE DADOS: único lugar que sabe "onde" os dados ficam.
 *
 * Hoje: os dados ficam em $_SESSION (demonstração).
 * Futuro: trocar o corpo destas funções por consultas ao banco de dados.
 * As demais páginas e funções só usam as funções repo...() e não precisam mudar.
 */

const CHAVE_DADOS = 'clau_dress_demo';

/** Carrega os dados fictícios na sessão, caso ainda não existam. */
function iniciarDadosDemo(): void
{
    if (!isset($_SESSION[CHAVE_DADOS])) {
        $_SESSION[CHAVE_DADOS] = obterDadosDemo();
    }
}

/** Descarta tudo o que foi alterado e recarrega os dados fictícios originais. */
function resetarDadosDemo(): void
{
    unset($_SESSION[CHAVE_DADOS]);
    iniciarDadosDemo();
}

/** Lista todos os registros de uma coleção ('produtos', 'clientes', 'vendas', 'formas_pagamento'). */
function repoListar(string $colecao): array
{
    return array_values($_SESSION[CHAVE_DADOS][$colecao] ?? []);
}

/** Busca um registro pelo ID. Retorna null se não existir. */
function repoBuscar(string $colecao, int $id): ?array
{
    foreach (repoListar($colecao) as $registro) {
        if ((int) $registro['id'] === $id) {
            return $registro;
        }
    }
    return null;
}

/** Calcula o próximo ID disponível (o banco real fará isso com AUTO_INCREMENT). */
function repoProximoId(string $colecao): int
{
    $maior = 0;
    foreach (repoListar($colecao) as $registro) {
        $maior = max($maior, (int) $registro['id']);
    }
    return $maior + 1;
}

/**
 * Salva um registro: se tiver 'id', atualiza; se não tiver, insere um novo.
 * Retorna o registro salvo (já com o ID).
 */
function repoSalvar(string $colecao, array $registro): array
{
    $registros = repoListar($colecao);

    // Atualização
    if (!empty($registro['id'])) {
        foreach ($registros as $indice => $existente) {
            if ((int) $existente['id'] === (int) $registro['id']) {
                $registros[$indice] = $registro;
                $_SESSION[CHAVE_DADOS][$colecao] = $registros;
                return $registro;
            }
        }
    }

    // Inserção
    $registro['id'] = repoProximoId($colecao);
    $registros[] = $registro;
    $_SESSION[CHAVE_DADOS][$colecao] = $registros;

    return $registro;
}

/** Exclui um registro pelo ID. Retorna true se algo foi removido. */
function repoExcluir(string $colecao, int $id): bool
{
    $registros = repoListar($colecao);
    $restantes = array_filter($registros, fn($r) => (int) $r['id'] !== $id);

    $_SESSION[CHAVE_DADOS][$colecao] = array_values($restantes);

    return count($restantes) < count($registros);
}
?>
<?php
/**
 * produtos_funcoes.php
 * Regras do módulo de produtos: listagem, filtros, validação e gravação.
 * Os dados são acessados somente pelas funções repo...() do repositorio.php.
 */

const LIMITE_ESTOQUE_BAIXO = 3;

/** Lista produtos, com filtro opcional por nome e por categoria. */
function listarProdutos(string $busca = '', string $categoria = ''): array
{
    $produtos = repoListar('produtos');

    return array_values(array_filter($produtos, function ($p) use ($busca, $categoria) {
        if ($busca !== '' && mb_stripos($p['nome'], $busca) === false) {
            return false;
        }
        if ($categoria !== '' && $p['categoria'] !== $categoria) {
            return false;
        }
        return true;
    }));
}

function buscarProduto(int $id): ?array
{
    return repoBuscar('produtos', $id);
}

/** Categorias existentes (sem repetição, em ordem alfabética). */
function listarCategorias(): array
{
    $categorias = array_unique(array_column(repoListar('produtos'), 'categoria'));
    sort($categorias);
    return $categorias;
}

/** Os produtos cadastrados mais recentemente. */
function produtosRecentes(int $limite = 5): array
{
    $produtos = repoListar('produtos');
    usort($produtos, fn($a, $b) => [$b['criado_em'], $b['id']] <=> [$a['criado_em'], $a['id']]);
    return array_slice($produtos, 0, $limite);
}

/** Estrutura de um produto em branco (usada no formulário de cadastro). */
function produtoVazio(): array
{
    return [
        'id' => 0, 'nome' => '', 'descricao' => '', 'categoria' => '', 'tamanho' => '',
        'cor' => '', 'preco_custo' => '', 'preco_venda' => '', 'estoque' => '',
    ];
}

/** Monta o produto a partir do POST, limpando e convertendo os valores. */
function montarProduto(array $post, ?array $existente = null): array
{
    $categoria = trim($post['categoria'] ?? '');
    $categoria = mb_strtoupper(mb_substr($categoria, 0, 1)) . mb_substr($categoria, 1);

    return [
        'id'          => $existente['id'] ?? 0,
        'nome'        => trim($post['nome'] ?? ''),
        'descricao'   => trim($post['descricao'] ?? ''),
        'categoria'   => $categoria,
        'tamanho'     => trim($post['tamanho'] ?? ''),
        'cor'         => trim($post['cor'] ?? ''),
        'preco_custo' => paraDecimal($post['preco_custo'] ?? ''),
        'preco_venda' => paraDecimal($post['preco_venda'] ?? ''),
        'estoque'     => (int) ($post['estoque'] ?? 0),
        'criado_em'   => $existente['criado_em'] ?? date('Y-m-d H:i:s'),
    ];
}

/** Valida o produto. Retorna um array [campo => mensagem]; vazio = tudo certo. */
function validarProduto(array $dados): array
{
    $erros = [];

    if (mb_strlen($dados['nome']) < 2)  { $erros['nome'] = 'Informe o nome do produto.'; }
    if ($dados['categoria'] === '')     { $erros['categoria'] = 'Informe a categoria.'; }
    if ($dados['tamanho'] === '')       { $erros['tamanho'] = 'Informe o tamanho.'; }
    if ($dados['cor'] === '')           { $erros['cor'] = 'Informe a cor.'; }
    if ($dados['preco_custo'] < 0)      { $erros['preco_custo'] = 'O preço de custo não pode ser negativo.'; }
    if ($dados['preco_venda'] <= 0)     { $erros['preco_venda'] = 'Informe um preço de venda maior que zero.'; }
    if ($dados['estoque'] < 0)          { $erros['estoque'] = 'O estoque não pode ser negativo.'; }

    return $erros;
}

function salvarProduto(array $dados): array
{
    return repoSalvar('produtos', $dados);
}

function excluirProduto(int $id): bool
{
    return repoExcluir('produtos', $id);
}

/** Selo colorido conforme a quantidade em estoque. */
function badgeEstoque(int $quantidade): string
{
    if ($quantidade <= 0) {
        return '<span class="badge badge-erro">Sem estoque</span>';
    }
    if ($quantidade <= LIMITE_ESTOQUE_BAIXO) {
        return '<span class="badge badge-aviso">' . $quantidade . ' un. (baixo)</span>';
    }
    return '<span class="badge badge-sucesso">' . $quantidade . ' un.</span>';
}
?>