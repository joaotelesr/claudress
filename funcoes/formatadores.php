<?php
/**
 * formatadores.php
 * Funções de apresentação: CPF, telefone, quantidade, paginação, selos de status.
 * (Complementa o helpers.php, que continua como está.)
 */

/** 12345678901 → 123.456.789-01 (se não tiver 11 dígitos, devolve o texto original). */
function formatarCpf(?string $cpf): string
{
    $d = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($d) !== 11) {
        return trim((string) $cpf);
    }
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

/** 11987654321 → (11) 98765-4321 (aceita também 10 dígitos; outros formatos voltam como estão). */
function formatarTelefone(?string $telefone): string
{
    $d = preg_replace('/\D/', '', (string) $telefone);
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return trim((string) $telefone);
}

/** 12345678 → 12345-678 */
function formatarCep(?string $cep): string
{
    $d = preg_replace('/\D/', '', (string) $cep);
    return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : trim((string) $cep);
}

/** Quantidade sem zeros desnecessários: 3.000 → "3" e 1.500 → "1,5". */
function formatarQuantidade($quantidade): string
{
    $texto = number_format((float) $quantidade, 3, ',', '.');
    return rtrim(rtrim($texto, '0'), ',');
}

/** A hora é gravada como "22:06:28:64"; mostramos só "22:06". */
function formatarHora(?string $hora): string
{
    return $hora ? substr($hora, 0, 5) : '';
}

/** Valida uma data no formato AAAA-MM-DD. Devolve a própria data ou '' se for inválida. */
function dataValida(?string $data): string
{
    $data = trim((string) $data);
    $obj  = DateTime::createFromFormat('Y-m-d', $data);
    return ($obj && $obj->format('Y-m-d') === $data) ? $data : '';
}

/** Nome do mês abreviado: "2026-11" → "Nov/26". */
function rotuloMes(string $anoMes): string
{
    $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    [$ano, $mes] = array_map('intval', explode('-', $anoMes));
    return $meses[$mes - 1] . '/' . substr((string) $ano, 2);
}

/** Selo colorido para o status de produtos, clientes e vendas. */
function badgeStatus(?string $status): string
{
    $status = trim((string) $status);
    $mapa = [
        'Ativo'              => 'badge-sucesso',
        'Finalizado'         => 'badge-sucesso',
        'Pendente'           => 'badge-aviso',
        'Aberta'             => 'badge-aviso',
        'Condicao'           => 'badge-aviso',
        'Pendente Pagamento' => 'badge-aviso',
        'Cancelado'          => 'badge-erro',
        'Condicao Cancelada' => 'badge-erro',
    ];
    $classe = $mapa[$status] ?? 'badge-info';
    $texto  = $status === '' ? '-' : $status;

    return '<span class="badge ' . $classe . '">' . e($texto) . '</span>';
}

/** Monta o endereço da página mantendo os filtros e trocando só o número da página. */
function urlComPagina(array $filtros, int $pagina): string
{
    $filtros['pagina'] = $pagina;
    return '?' . http_build_query($filtros);
}

/** Desenha os botões de paginação. $filtros = os parâmetros atuais da busca (para não perdê-los). */
function renderPaginacao(array $pg, array $filtros = []): void
{
    if ($pg['total_paginas'] <= 1) {
        return;
    }

    $atual = $pg['pagina'];
    $total = $pg['total_paginas'];

    echo '<nav class="paginacao" aria-label="Paginação">';

    if ($atual > 1) {
        echo '<a class="paginacao-link" href="' . e(urlComPagina($filtros, $atual - 1)) . '">&laquo; Anterior</a>';
    }

    $inicio = max(1, $atual - 2);
    $fim    = min($total, $atual + 2);

    if ($inicio > 1) {
        echo '<a class="paginacao-link" href="' . e(urlComPagina($filtros, 1)) . '">1</a>';
        if ($inicio > 2) {
            echo '<span class="paginacao-reticencias">…</span>';
        }
    }

    for ($i = $inicio; $i <= $fim; $i++) {
        $classe = $i === $atual ? ' paginacao-atual' : '';
        echo '<a class="paginacao-link' . $classe . '" href="' . e(urlComPagina($filtros, $i)) . '">' . $i . '</a>';
    }

    if ($fim < $total) {
        if ($fim < $total - 1) {
            echo '<span class="paginacao-reticencias">…</span>';
        }
        echo '<a class="paginacao-link" href="' . e(urlComPagina($filtros, $total)) . '">' . $total . '</a>';
    }

    if ($atual < $total) {
        echo '<a class="paginacao-link" href="' . e(urlComPagina($filtros, $atual + 1)) . '">Próxima &raquo;</a>';
    }

    echo '<span class="paginacao-resumo">Página ' . $atual . ' de ' . $total
        . ' · ' . number_format($pg['total'], 0, ',', '.') . ' registros</span>';
    echo '</nav>';
}

/** Valor curto para gráficos: 306430.41 → "R$ 306,4 mil"; valores pequenos ficam completos. */
function formatarMoedaCurta($valor): string
{
    $v = (float) $valor;
    if ($v >= 1000000) {
        return 'R$ ' . number_format($v / 1000000, 1, ',', '.') . ' mi';
    }
    if ($v >= 1000) {
        return 'R$ ' . number_format($v / 1000, 1, ',', '.') . ' mil';
    }
    return formatarMoeda($v);
}
