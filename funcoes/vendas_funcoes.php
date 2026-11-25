<?php
/**
 * vendas_funcoes.php
 * Regras do módulo de vendas.
 * (Etapa 2: apenas leitura. O registro de novas vendas entra na Etapa 5.)
 */

/** Lista as vendas da mais recente para a mais antiga. */
function listarVendas(): array
{
    $vendas = repoListar('vendas');
    usort($vendas, fn($a, $b) => [$b['data'], $b['id']] <=> [$a['data'], $a['id']]);
    return $vendas;
}

function buscarVenda(int $id): ?array
{
    return repoBuscar('vendas', $id);
}

function ultimasVendas(int $limite = 5): array
{
    return array_slice(listarVendas(), 0, $limite);
}

/** Soma do valor de todas as vendas. */
function totalVendido(): float
{
    return (float) array_sum(array_column(repoListar('vendas'), 'total'));
}

/** Nome do cliente de uma venda (mesmo que o cliente tenha sido excluído depois). */
function nomeCliente(int $clienteId): string
{
    $cliente = repoBuscar('clientes', $clienteId);
    return $cliente ? $cliente['nome'] : 'Cliente removido';
}

function nomeFormaPagamento(int $formaId): string
{
    $forma = repoBuscar('formas_pagamento', $formaId);
    return $forma ? $forma['nome'] : '-';
}