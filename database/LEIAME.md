# Banco de dados - Clau Dress

O site usa o banco **FaloPro** (MySQL), convertido do backup do sistema atual da loja.

## Importar
1. Coloque `FaloPro_mysql.sql` nesta pasta (não envie esse arquivo ao GitHub: tem dados pessoais de clientes).
2. No MySQL: `mysql -u root -p < database/FaloPro_mysql.sql`
3. Confira em `config/conexao.php`: DB_HOST, DB_PORTA, DB_NOME (FaloPro), DB_USUARIO, DB_SENHA.
4. Abra `http://localhost/CLAUDRESS/admin/` (ajuste conforme seu servidor).

## Otimização (opcional)
`otimizacao.sql` cria índices e ajusta o tipo de CODIGO_VENDA nas tabelas filhas. Rode depois de importar, com backup.

## Regras seguidas ao gravar (iguais às do sistema atual)
- Excluir produto/cliente = STATUS 'Cancelado' (nada é apagado).
- Venda: TOTAL = VALOR - DESCONTO; o frete fica fora do total.
- Só vendas 'Finalizado' entram nos totais.
- Venda baixa o estoque (QUANT) do produto na mesma transação.
- Cartão de crédito vence em 30 dias, débito em 2.
