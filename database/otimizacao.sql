-- otimizacao.sql  (OPCIONAL)  -  banco FaloPro (MySQL)
-- Faça um backup antes. As tabelas vieram do SQL Server sem índices, então as telas ficam
-- mais rápidas com os índices abaixo. Nada aqui altera dados.
USE FaloPro;

-- Colunas de ligação que estão como texto nas tabelas filhas viram número (o mesmo tipo de VENDAS.CODIGO_VENDA)
ALTER TABLE ITENS_VENDA           MODIFY CODIGO_VENDA INT;
ALTER TABLE FORMA_PAGAMENTO_VENDA MODIFY CODIGO_VENDA INT;

CREATE INDEX idx_vendas_data       ON VENDAS (DATA);
CREATE INDEX idx_vendas_cliente    ON VENDAS (CODIGO_CLIENTE);
CREATE INDEX idx_vendas_status     ON VENDAS (STATUS_VENDA);
CREATE INDEX idx_itens_venda       ON ITENS_VENDA (CODIGO_VENDA);
CREATE INDEX idx_itens_produto     ON ITENS_VENDA (CODIGO_PRODUTO);
CREATE INDEX idx_fpv_venda         ON FORMA_PAGAMENTO_VENDA (CODIGO_VENDA);
CREATE INDEX idx_contatos_cliente  ON CLIENTE_CONTATOS (CODIGO_CLIENTE);
CREATE INDEX idx_produtos_grupo    ON PRODUTOS (COD_GRUPO);
