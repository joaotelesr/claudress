<?php
/**
 * produtos_funcoes.php
 * Regras do módulo de produtos, usando as tabelas PRODUTOS, GRUPOS e SUBGRUPO do banco da loja.
 *
 * Mapa dos campos do sistema para as colunas do banco:
 *   nome → DESCRICAO | categoria → COD_GRUPO (tabela GRUPOS) | estoque → QUANT
 *   custo → PRE_CUS  | venda → PRE_VEN                      | situação → STATUS_PRODUTO
 * Produtos não são apagados: ficam com STATUS_PRODUTO = 'Cancelado' (como no sistema atual da loja),
 * porque as vendas antigas continuam apontando para eles.
 */

const LIMITE_ESTOQUE_BAIXO = 2;

/** Lê e normaliza os filtros da tela de produtos (vindos da URL). */
function filtrosProdutos(array $get): array
{
    $status = $get['status'] ?? 'Ativo';
    if (!in_array($status, ['Ativo', 'Cancelado', ''], true)) {
        $status = 'Ativo';
    }

    $estoque = $get['estoque'] ?? '';
    if (!in_array($estoque, ['', 'com', 'sem'], true)) {
        $estoque = '';
    }

    return [
        'busca'   => trim((string) ($get['busca'] ?? '')),
        'grupo'   => (int) ($get['grupo'] ?? 0),
        'status'  => $status,
        'estoque' => $estoque,
    ];
}

/** Monta o WHERE a partir dos filtros e preenche $params. */
function whereProdutos(array $f, array &$params): string
{
    $where = ['1 = 1'];

    if ($f['busca'] !== '') {
        $like = '%' . $f['busca'] . '%';
        $cond = ['p.DESCRICAO LIKE :busca_desc', 'p.REFERENCIA LIKE :busca_ref'];
        $params[':busca_desc'] = $like;
        $params[':busca_ref']  = $like;

        if (ctype_digit($f['busca'])) {
            $cond[] = 'p.CODIGO = :busca_cod';
            $cond[] = 'p.COD_BARRAS_FORN = :busca_cb';
            $params[':busca_cod'] = (int) $f['busca'];
            $params[':busca_cb']  = $f['busca'];
        }
        $where[] = '(' . implode(' OR ', $cond) . ')';
    }

    if ($f['grupo'] > 0) {
        $where[] = 'p.COD_GRUPO = :grupo';
        $params[':grupo'] = (string) $f['grupo'];
    }

    if ($f['status'] !== '') {
        $where[] = 'p.STATUS_PRODUTO = :status';
        $params[':status'] = $f['status'];
    }

    if ($f['estoque'] === 'com') {
        $where[] = 'p.QUANT > 0';
    } elseif ($f['estoque'] === 'sem') {
        $where[] = '(p.QUANT <= 0 OR p.QUANT IS NULL)';
    }

    return implode(' AND ', $where);
}

function contarProdutos(array $f): int
{
    $params = [];
    $where  = whereProdutos($f, $params);
    return (int) consultarValor("SELECT COUNT(*) FROM PRODUTOS p WHERE $where", $params);
}

function listarProdutos(array $f, int $limite, int $offset): array
{
    $params = [];
    $where  = whereProdutos($f, $params);
    $params[':limite']  = $limite;
    $params[':posicao'] = $offset;

    return consultar(
        "SELECT p.CODIGO, p.DESCRICAO, p.REFERENCIA, p.PRE_CUS, p.PRE_VEN, p.QUANT, p.TAMANHO,
                p.STATUS_PRODUTO, p.COD_BARRAS_FORN, TRIM(g.GRUPO) AS GRUPO
           FROM PRODUTOS p
           LEFT JOIN GRUPOS g ON g.CODIGO = p.COD_GRUPO
          WHERE $where
          ORDER BY p.CODIGO DESC
          LIMIT :limite OFFSET :posicao",
        $params
    );
}

function buscarProduto(int $id): ?array
{
    return consultarUma('SELECT * FROM PRODUTOS WHERE CODIGO = :id', [':id' => $id]);
}

/** Grupos (categorias) ativos. Se estiver editando um produto de grupo cancelado, ele também entra na lista. */
function listarGrupos(int $incluirCodigo = 0): array
{
    return consultar(
        "SELECT CODIGO, TRIM(GRUPO) AS GRUPO
           FROM GRUPOS
          WHERE GRUPO IS NOT NULL AND TRIM(GRUPO) <> '' AND (STATUS_GRUPO = 'Ativo' OR CODIGO = :inc)
          ORDER BY TRIM(GRUPO)",
        [':inc' => $incluirCodigo]
    );
}

function listarSubgrupos(): array
{
    return consultar(
        "SELECT CODIGO, TRIM(DESCRICAO) AS DESCRICAO
           FROM SUBGRUPO
          WHERE DESCRICAO IS NOT NULL AND TRIM(DESCRICAO) <> '' AND STATUS_SUBGRUPO = 'Ativo'
          ORDER BY TRIM(DESCRICAO), CODIGO"
    );
}

/** Estrutura de um produto em branco (formulário de cadastro). */
function produtoVazio(): array
{
    return [
        'id' => 0, 'nome' => '', 'referencia' => '', 'grupo' => '', 'subgrupo' => '', 'tamanho' => '',
        'preco_custo' => '', 'preco_venda' => '', 'estoque' => '0', 'status' => 'Ativo',
    ];
}

/** Converte a linha do banco para os campos do formulário. */
function produtoParaFormulario(array $linha): array
{
    return [
        'id'          => (int) $linha['CODIGO'],
        'nome'        => trim((string) $linha['DESCRICAO']),
        'referencia'  => trim((string) $linha['REFERENCIA']),
        'grupo'       => trim((string) $linha['COD_GRUPO']),
        'subgrupo'    => trim((string) $linha['COD_SUBGRUPO']),
        'tamanho'     => trim((string) $linha['TAMANHO']),
        'preco_custo' => $linha['PRE_CUS'] === null ? '' : paraBanco((float) $linha['PRE_CUS']),
        'preco_venda' => $linha['PRE_VEN'] === null ? '' : paraBanco((float) $linha['PRE_VEN']),
        'estoque'     => $linha['QUANT'] === null ? '0' : formatarQuantidade($linha['QUANT']),
        'status'      => (string) ($linha['STATUS_PRODUTO'] ?? 'Ativo'),
    ];
}

/** Lê os campos enviados pelo formulário. */
function montarProduto(array $post, int $id = 0): array
{
    return [
        'id'          => $id,
        'nome'        => trim((string) ($post['nome'] ?? '')),
        'referencia'  => trim((string) ($post['referencia'] ?? '')),
        'grupo'       => trim((string) ($post['grupo'] ?? '')),
        'subgrupo'    => trim((string) ($post['subgrupo'] ?? '')),
        'tamanho'     => trim((string) ($post['tamanho'] ?? '')),
        'preco_custo' => trim((string) ($post['preco_custo'] ?? '')),
        'preco_venda' => trim((string) ($post['preco_venda'] ?? '')),
        'estoque'     => trim((string) ($post['estoque'] ?? '0')),
        'status'      => (string) ($post['status'] ?? 'Ativo'),
    ];
}

/** Valida o produto. Retorna [campo => mensagem]; vazio = tudo certo. */
function validarProduto(array $d): array
{
    $erros = [];

    if (mb_strlen($d['nome']) < 2 || mb_strlen($d['nome']) > 200) {
        $erros['nome'] = 'Informe o nome do produto (até 200 caracteres).';
    }
    if (mb_strlen($d['referencia']) > 50) {
        $erros['referencia'] = 'A referência pode ter no máximo 50 caracteres.';
    }
    if ($d['grupo'] === '' || !ctype_digit($d['grupo']) || strlen($d['grupo']) > 4) {
        $erros['grupo'] = 'Escolha a categoria.';
    } elseif (consultarValor('SELECT COUNT(*) FROM GRUPOS WHERE CODIGO = :g', [':g' => (int) $d['grupo']]) < 1) {
        $erros['grupo'] = 'Categoria não encontrada.';
    }
    if ($d['subgrupo'] !== '' && (!ctype_digit($d['subgrupo']) || strlen($d['subgrupo']) > 7)) {
        $erros['subgrupo'] = 'Subgrupo inválido.';
    }
    if ($d['tamanho'] === '' || mb_strlen($d['tamanho']) > 5) {
        $erros['tamanho'] = 'Informe o tamanho (até 5 caracteres, ex.: P, M, G, 38, PLUS).';
    }
    if ($d['preco_custo'] === '' || !is_numeric($d['preco_custo']) || (float) $d['preco_custo'] < 0 || (float) $d['preco_custo'] > 99999999) {
        $erros['preco_custo'] = 'Informe um preço de custo válido (zero ou maior).';
    }
    if ($d['preco_venda'] === '' || !is_numeric($d['preco_venda']) || (float) $d['preco_venda'] <= 0 || (float) $d['preco_venda'] > 99999999) {
        $erros['preco_venda'] = 'Informe um preço de venda maior que zero.';
    }
    if (!ctype_digit($d['estoque']) || (int) $d['estoque'] > 9999999) {
        $erros['estoque'] = 'Informe a quantidade em estoque (número inteiro, zero ou maior).';
    }
    if (!in_array($d['status'], ['Ativo', 'Cancelado'], true)) {
        $erros['status'] = 'Situação inválida.';
    }

    return $erros;
}

/** Grava (insere ou atualiza) o produto e devolve o código. */
function salvarProduto(array $d): int
{
    return emTransacao(function () use ($d) {
        $custo = (float) $d['preco_custo'];
        $venda = (float) $d['preco_venda'];
        $lucro = $custo > 0 ? round($venda / $custo, 2) : 0.0;   // o sistema atual grava a razão venda/custo
        $sub   = $d['subgrupo'] === '' ? null : $d['subgrupo'];
        $hoje  = date('Y-m-d');

        if ($d['id'] > 0) {
            executar(
                'UPDATE PRODUTOS
                    SET DESCRICAO = :nome, REFERENCIA = :ref, COD_GRUPO = :grupo, COD_SUBGRUPO = :sub,
                        TAMANHO = :tam, PRE_CUS = :custo, PRE_VEN = :venda, PORC_LUCRO = :lucro,
                        QUANT = :qtd, STATUS_PRODUTO = :status, DATA_ALT = :alt
                  WHERE CODIGO = :id',
                [
                    ':nome' => $d['nome'], ':ref' => $d['referencia'] === '' ? null : $d['referencia'],
                    ':grupo' => $d['grupo'], ':sub' => $sub, ':tam' => $d['tamanho'],
                    ':custo' => paraBanco($custo), ':venda' => paraBanco($venda), ':lucro' => paraBanco($lucro, 3),
                    ':qtd' => paraBanco((float) $d['estoque'], 3), ':status' => $d['status'], ':alt' => $hoje,
                    ':id' => $d['id'],
                ]
            );
            return $d['id'];
        }

        executar(
            "INSERT INTO PRODUTOS
                (LOJA, DESCRICAO, PRE_CUS, PRE_VEN, PORC_LUCRO, QUANT, COD_GRUPO, COD_SUBGRUPO, TAMANHO,
                 DATA_CADASTRO, DATA_ALT, STATUS_PRODUTO, UNIDADE_MEDIDA, COD_PUBLICO_ALVO,
                 ICMS, SUBST, ISENTO_ICMS, NAO_TRIB, PORC_DESC, VALOR_DESC, VALOR_PROMOCAO,
                 ECOMMERCE_FLAG_EXCECAO, ECOMMERCE_FLAG_HABILITADO, ECOMMERCE_FLAG_DESTAQUE)
             VALUES
                (:loja, :nome, :custo, :venda, :lucro, :qtd, :grupo, :sub, :tam,
                 :cad, :alt, :status, 'UN', '1',
                 0, '0', '0', 'N', 0, 0, 0,
                 0, 0, 0)",
            [
                ':loja' => CODIGO_LOJA, ':nome' => $d['nome'], ':custo' => paraBanco($custo), ':venda' => paraBanco($venda),
                ':lucro' => paraBanco($lucro, 3), ':qtd' => paraBanco((float) $d['estoque'], 3),
                ':grupo' => $d['grupo'], ':sub' => $sub, ':tam' => $d['tamanho'],
                ':cad' => $hoje, ':alt' => $hoje, ':status' => $d['status'],
            ]
        );
        $novoId = ultimoId();

        // Padrão do sistema atual: código de barras e referência = código do produto com 6 dígitos (ex.: 026090)
        $codigo = str_pad((string) $novoId, 6, '0', STR_PAD_LEFT);
        executar(
            'UPDATE PRODUTOS SET COD_BARRAS_FORN = :cb, REFERENCIA = :ref WHERE CODIGO = :id',
            [':cb' => $codigo, ':ref' => $d['referencia'] !== '' ? $d['referencia'] : $codigo, ':id' => $novoId]
        );

        return $novoId;
    });
}

/** "Exclui" o produto: apenas marca como Cancelado (o histórico de vendas é preservado). */
function inativarProduto(int $id): bool
{
    return executar(
        "UPDATE PRODUTOS SET STATUS_PRODUTO = 'Cancelado', DATA_ALT = :alt WHERE CODIGO = :id AND STATUS_PRODUTO <> 'Cancelado'",
        [':alt' => date('Y-m-d'), ':id' => $id]
    ) > 0;
}

/** Selo colorido conforme a quantidade em estoque. */
function badgeEstoque($quantidade): string
{
    $q = (float) $quantidade;

    if ($q <= 0) {
        return '<span class="badge">Zerado</span>';
    }
    if ($q <= LIMITE_ESTOQUE_BAIXO) {
        return '<span class="badge badge-aviso">' . e(formatarQuantidade($q)) . ' un. (baixo)</span>';
    }
    return '<span class="badge badge-sucesso">' . e(formatarQuantidade($q)) . ' un.</span>';
}

// ---------- Números do painel ----------

function resumoEstoque(): array
{
    $r = consultarUma(
        "SELECT COUNT(*) AS ATIVOS,
                COALESCE(SUM(CASE WHEN QUANT > 0 THEN 1 ELSE 0 END), 0) AS COM_ESTOQUE,
                COALESCE(SUM(CASE WHEN QUANT > 0 THEN QUANT ELSE 0 END), 0) AS PECAS,
                COALESCE(SUM(CASE WHEN QUANT > 0 THEN QUANT * PRE_VEN ELSE 0 END), 0) AS VALOR_VENDA
           FROM PRODUTOS
          WHERE STATUS_PRODUTO = 'Ativo'"
    );
    return $r ?? ['ATIVOS' => 0, 'COM_ESTOQUE' => 0, 'PECAS' => 0, 'VALOR_VENDA' => 0];
}

function produtosEstoqueBaixo(int $limite = 5): array
{
    return consultar(
        "SELECT CODIGO, DESCRICAO, TAMANHO, QUANT
           FROM PRODUTOS
          WHERE STATUS_PRODUTO = 'Ativo' AND QUANT > 0 AND QUANT <= :lim
          ORDER BY QUANT, CODIGO DESC
          LIMIT :n",
        [':lim' => LIMITE_ESTOQUE_BAIXO, ':n' => $limite]
    );
}
