<?php
/**
 * dados_demo.php
 * Dados fictícios usados na versão de demonstração.
 * Na integração com o banco real, este arquivo deixa de ser usado.
 */

function obterDadosDemo(): array
{
    // ---------- PRODUTOS ----------
    // [nome, descrição, categoria, tamanho, cor, preço de custo, preço de venda, estoque, criado há X dias]
    $linhasProdutos = [
        ['Vestido Midi Floral',   'Vestido midi em viscose com estampa floral e forro',   'Vestidos',   'M',     'Rosa',     89.90,  189.90, 8,  30],
        ['Vestido Longo Aurora',  'Vestido longo fluido com fenda lateral',               'Vestidos',   'G',     'Vinho',    120.00, 259.90, 4,  25],
        ['Blusa Cetim Clássica',  'Blusa de cetim com manga longa e botões',              'Blusas',     'P',     'Off-white', 45.00, 109.90, 15, 20],
        ['Cropped Tricô',         'Cropped em tricô macio com gola redonda',              'Blusas',     'M',     'Bege',     32.50,  79.90,  12, 14],
        ['Calça Alfaiataria',     'Calça wide leg de alfaiataria com cintura alta',       'Calças',     '38',    'Preto',    78.00,  179.90, 9,  12],
        ['Calça Jeans Reta',      'Jeans reto com lavagem clara',                         'Calças',     '40',    'Azul',     65.00,  149.90, 11, 9],
        ['Saia Plissada',         'Saia midi plissada com elástico na cintura',           'Saias',      'M',     'Verde',    40.00,  119.90, 7,  6],
        ['Saia Jeans Curta',      'Saia curta em jeans com botões frontais',              'Saias',      'P',     'Azul',     35.00,  99.90,  3,  4],
        ['Bolsa Transversal',     'Bolsa pequena com alça removível',                     'Acessórios', 'Único', 'Caramelo', 55.00,  139.90, 6,  2],
        ['Lenço de Seda',         'Lenço estampado em tecido acetinado',                  'Acessórios', 'Único', 'Rosa',     15.00,  49.90,  20, 1],
    ];

    $produtos = [];
    foreach ($linhasProdutos as $indice => $l) {
        $produtos[] = [
            'id'          => $indice + 1,
            'nome'        => $l[0],
            'descricao'   => $l[1],
            'categoria'   => $l[2],
            'tamanho'     => $l[3],
            'cor'         => $l[4],
            'preco_custo' => $l[5],
            'preco_venda' => $l[6],
            'estoque'     => $l[7],
            'criado_em'   => date('Y-m-d H:i:s', strtotime("-{$l[8]} days")),
        ];
    }

    // ---------- CLIENTES ----------
    $clientes = [
        ['id' => 1, 'nome' => 'Mariana Souza',    'cpf' => '111.444.777-35', 'telefone' => '(11) 3456-7890', 'whatsapp' => '(11) 98765-4321', 'email' => 'mariana.souza@exemplo.com',  'endereco' => 'Rua das Flores, 120 - Jordanésia, Cajamar/SP'],
        ['id' => 2, 'nome' => 'Camila Ferreira',  'cpf' => '',               'telefone' => '(11) 3345-1122', 'whatsapp' => '(11) 97654-3210', 'email' => '',                           'endereco' => ''],
        ['id' => 3, 'nome' => 'Juliana Almeida',  'cpf' => '529.982.247-25', 'telefone' => '',               'whatsapp' => '(11) 96543-2109', 'email' => 'ju.almeida@exemplo.com',     'endereco' => 'Av. Principal, 450 - Centro, Cajamar/SP'],
        ['id' => 4, 'nome' => 'Fernanda Costa',   'cpf' => '',               'telefone' => '(11) 4567-8899', 'whatsapp' => '(11) 95432-1098', 'email' => 'fe.costa@exemplo.com',       'endereco' => ''],
        ['id' => 5, 'nome' => 'Beatriz Lima',     'cpf' => '',               'telefone' => '(11) 3222-3344', 'whatsapp' => '(11) 94321-0987', 'email' => '',                           'endereco' => 'Rua do Comércio, 80 - Polvilho, Cajamar/SP'],
        ['id' => 6, 'nome' => 'Patrícia Rocha',   'cpf' => '',               'telefone' => '(11) 3111-2233', 'whatsapp' => '(11) 93210-9876', 'email' => 'paty.rocha@exemplo.com',     'endereco' => ''],
    ];

    // ---------- FORMAS DE PAGAMENTO ----------
    $formasPagamento = [
        ['id' => 1, 'nome' => 'Dinheiro',         'descricao' => 'Pagamento em espécie no balcão',  'ativo' => true],
        ['id' => 2, 'nome' => 'PIX',              'descricao' => 'Transferência instantânea',       'ativo' => true],
        ['id' => 3, 'nome' => 'Cartão de débito', 'descricao' => 'Débito na maquininha',            'ativo' => true],
        ['id' => 4, 'nome' => 'Cartão de crédito', 'descricao' => 'Crédito na maquininha',          'ativo' => true],
    ];

    // ---------- VENDAS ----------
    // [cliente_id, vendida há X dias, forma_pagamento_id, [[produto_id, quantidade], ...]]
    $linhasVendas = [
        [1, 0, 2, [[1, 1], [10, 1]]],
        [2, 1, 4, [[3, 2], [5, 1]]],
        [3, 2, 1, [[4, 1]]],
        [4, 4, 3, [[2, 1]]],
        [5, 6, 2, [[6, 1], [7, 1], [9, 1]]],
        [6, 9, 4, [[8, 2], [3, 1]]],
    ];

    $produtosPorId = array_column($produtos, null, 'id');
    $vendas = [];

    foreach ($linhasVendas as $indice => $l) {
        $itens = [];
        $total = 0;

        foreach ($l[3] as $itemBruto) {
            $produto  = $produtosPorId[$itemBruto[0]];
            $subtotal = $produto['preco_venda'] * $itemBruto[1];
            $itens[]  = [
                'produto_id'     => $produto['id'],
                'nome'           => $produto['nome'],
                'quantidade'     => $itemBruto[1],
                'preco_unitario' => $produto['preco_venda'],
                'subtotal'       => $subtotal,
            ];
            $total += $subtotal;
        }

        $vendas[] = [
            'id'                 => $indice + 1,
            'cliente_id'         => $l[0],
            'data'               => date('Y-m-d', strtotime("-{$l[1]} days")),
            'forma_pagamento_id' => $l[2],
            'itens'              => $itens,
            'total'              => $total,
        ];
    }

    return [
        'produtos'         => $produtos,
        'clientes'         => $clientes,
        'formas_pagamento' => $formasPagamento,
        'vendas'           => $vendas,
    ];
}