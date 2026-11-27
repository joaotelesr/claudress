-- ajustar_nomes_tabelas.sql
-- Rode na hospedagem (phpMyAdmin > aba SQL) DEPOIS de importar o banco.
-- Passa para maiúscula só as tabelas que estiverem em minúscula. Pode rodar mais de uma vez sem problema.

SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'cliente') > 0, 'RENAME TABLE `cliente` TO `CLIENTE`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'cliente_contatos') > 0, 'RENAME TABLE `cliente_contatos` TO `CLIENTE_CONTATOS`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'conciliacao_extrato') > 0, 'RENAME TABLE `conciliacao_extrato` TO `CONCILIACAO_EXTRATO`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'conciliacao_pagamento') > 0, 'RENAME TABLE `conciliacao_pagamento` TO `CONCILIACAO_PAGAMENTO`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'crediario_parcela') > 0, 'RENAME TABLE `crediario_parcela` TO `CREDIARIO_PARCELA`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'devolucao') > 0, 'RENAME TABLE `devolucao` TO `DEVOLUCAO`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'devolucao_item') > 0, 'RENAME TABLE `devolucao_item` TO `DEVOLUCAO_ITEM`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'forma_pagamento') > 0, 'RENAME TABLE `forma_pagamento` TO `FORMA_PAGAMENTO`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'forma_pagamento_adquirente') > 0, 'RENAME TABLE `forma_pagamento_adquirente` TO `FORMA_PAGAMENTO_ADQUIRENTE`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'forma_pagamento_adquirente_bandeira') > 0, 'RENAME TABLE `forma_pagamento_adquirente_bandeira` TO `FORMA_PAGAMENTO_ADQUIRENTE_BANDEIRA`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'forma_pagamento_venda') > 0, 'RENAME TABLE `forma_pagamento_venda` TO `FORMA_PAGAMENTO_VENDA`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'fornecedor_loja') > 0, 'RENAME TABLE `fornecedor_loja` TO `FORNECEDOR_LOJA`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'funcionarios_usuarios') > 0, 'RENAME TABLE `funcionarios_usuarios` TO `FUNCIONARIOS_USUARIOS`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'grupos') > 0, 'RENAME TABLE `grupos` TO `GRUPOS`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'itens_venda') > 0, 'RENAME TABLE `itens_venda` TO `ITENS_VENDA`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'produtos') > 0, 'RENAME TABLE `produtos` TO `PRODUTOS`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'produto_fornecedor') > 0, 'RENAME TABLE `produto_fornecedor` TO `PRODUTO_FORNECEDOR`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'subgrupo') > 0, 'RENAME TABLE `subgrupo` TO `SUBGRUPO`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = 'vendas') > 0, 'RENAME TABLE `vendas` TO `VENDAS`', 'DO 0');
PREPARE comando FROM @sql; EXECUTE comando; DEALLOCATE PREPARE comando;