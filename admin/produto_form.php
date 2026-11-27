<?php
/**
 * produto_form.php - Cadastro e edição de produto (tabela PRODUTOS)
 * Categoria, subgrupo e fornecedor novos podem ser criados aqui mesmo ("+ Nova"), via produto_cadastro_rapido.php.
 */
require_once __DIR__ . '/../config/config.php';
require_once RAIZ_PROJETO . '/funcoes/produtos_funcoes.php';

$id      = (int) ($_GET['id'] ?? 0);
$linha   = $id > 0 ? buscarProduto($id) : null;

if ($id > 0 && $linha === null) {
    definirMensagem('erro', 'Produto não encontrado.');
    redirecionar('produtos.php');
}

$editando = $linha !== null;
$dados    = $editando ? produtoParaFormulario($linha) : produtoVazio();
$erros    = [];

if (ehPost()) {
    $dados = montarProduto($_POST, $editando ? $id : 0);
    $erros = validarProduto($dados);

    if (empty($erros)) {
        try {
            salvarProduto($dados);
            definirMensagem('sucesso', $editando ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
            redirecionar('produtos.php');
        } catch (PDOException $e) {
            $erros['geral'] = 'Não foi possível gravar no banco de dados.' . (DB_MOSTRAR_ERROS ? ' ' . $e->getMessage() : '');
        }
    }
}

$grupos       = listarGrupos((int) $dados['grupo']);
$subgrupos    = listarSubgrupos();
$fornecedores = listarFornecedores((int) $dados['fornecedor']);
$tituloPagina = $editando ? 'Editar produto #' . $id : 'Novo produto';
$cssPagina    = ['cadastros.css'];

require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert">
        <span><?= isset($erros['geral']) ? e($erros['geral']) : 'Corrija os campos destacados e tente novamente.' ?></span>
    </div>
<?php endif; ?>

<form method="post" class="cartao cartao-formulario">
    <div class="formulario-grade">

        <div class="campo campo-largo">
            <label for="nome">Nome do produto *</label>
            <input type="text" id="nome" name="nome" value="<?= e($dados['nome']) ?>" maxlength="200" required>
            <?= erroCampo($erros, 'nome') ?>
        </div>

        <div class="campo">
            <div class="campo-rotulo">
                <label for="grupo">Categoria *</label>
                <button type="button" class="botao-link" data-novo="categoria" data-select="grupo">+ Nova</button>
            </div>
            <select id="grupo" name="grupo" required>
                <option value="">Escolha...</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= (int) $g['CODIGO'] ?>" <?= (string) $g['CODIGO'] === (string) $dados['grupo'] ? 'selected' : '' ?>><?= e($g['GRUPO']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erroCampo($erros, 'grupo') ?>
        </div>

        <div class="campo">
            <div class="campo-rotulo">
                <label for="subgrupo">Subgrupo</label>
                <button type="button" class="botao-link" data-novo="subgrupo" data-select="subgrupo">+ Novo</button>
            </div>
            <select id="subgrupo" name="subgrupo">
                <option value="">Nenhum</option>
                <?php foreach ($subgrupos as $s): ?>
                    <option value="<?= (int) $s['CODIGO'] ?>" <?= (string) $s['CODIGO'] === (string) $dados['subgrupo'] ? 'selected' : '' ?>><?= e($s['DESCRICAO']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erroCampo($erros, 'subgrupo') ?>
        </div>

        <div class="campo">
            <div class="campo-rotulo">
                <label for="fornecedor">Fornecedor</label>
                <button type="button" class="botao-link" data-novo="fornecedor" data-select="fornecedor">+ Novo</button>
            </div>
            <select id="fornecedor" name="fornecedor">
                <option value="">Nenhum</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int) $f['CODIGO'] ?>" <?= (string) (int) $f['CODIGO'] === (string) $dados['fornecedor'] ? 'selected' : '' ?>><?= e($f['NOME']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erroCampo($erros, 'fornecedor') ?>
        </div>

        <div class="campo">
            <label for="tamanho">Tamanho *</label>
            <input type="text" id="tamanho" name="tamanho" value="<?= e($dados['tamanho']) ?>" maxlength="5" placeholder="P, M, G, 38, PLUS..." required>
            <?= erroCampo($erros, 'tamanho') ?>
        </div>

        <div class="campo">
            <label for="referencia">Referência</label>
            <input type="text" id="referencia" name="referencia" value="<?= e($dados['referencia']) ?>" maxlength="50"
                   placeholder="<?= $editando ? '' : 'Em branco = usa o código do produto' ?>">
            <?= erroCampo($erros, 'referencia') ?>
        </div>

        <div class="campo">
            <label for="preco_custo">Preço de custo (R$) *</label>
            <input type="number" id="preco_custo" name="preco_custo" min="0" step="0.01" value="<?= e($dados['preco_custo']) ?>" required>
            <?= erroCampo($erros, 'preco_custo') ?>
        </div>

        <div class="campo">
            <label for="preco_venda">Preço de venda (R$) *</label>
            <input type="number" id="preco_venda" name="preco_venda" min="0.01" step="0.01" value="<?= e($dados['preco_venda']) ?>" required>
            <?= erroCampo($erros, 'preco_venda') ?>
        </div>

        <div class="campo">
            <label for="estoque">Quantidade em estoque *</label>
            <input type="number" id="estoque" name="estoque" min="0" step="1" value="<?= e($dados['estoque']) ?>" required>
            <?= erroCampo($erros, 'estoque') ?>
        </div>

        <?php if ($editando): ?>
            <div class="campo">
                <label for="status">Situação</label>
                <select id="status" name="status">
                    <option value="Ativo" <?= $dados['status'] === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="Cancelado" <?= $dados['status'] === 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
                <?= erroCampo($erros, 'status') ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="formulario-acoes">
        <a href="produtos.php" class="botao botao-secundario">Cancelar</a>
        <button type="submit" class="botao botao-primario">Salvar produto</button>
    </div>
</form>

<?php require __DIR__ . '/includes/rodape.php'; ?>
<script>
// Cadastro rápido ("+ Nova") — o código fica aqui dentro para não depender de outro arquivo
/**
 * produto_form.js - Cadastro rápido na tela de produto: botões "+ Nova" ao lado de Categoria,
 * Subgrupo e Fornecedor abrem um campo para digitar o nome; ao salvar, o item é criado
 * (admin/produto_cadastro_rapido.php) e já fica escolhido no select, sem perder o que foi digitado.
 */
(function () {
    'use strict';

    var nomes = { categoria: 'categoria', subgrupo: 'subgrupo', fornecedor: 'fornecedor' };

    document.querySelectorAll('[data-novo]').forEach(function (botao) {
        var tipo = botao.getAttribute('data-novo');
        var select = document.getElementById(botao.getAttribute('data-select'));
        var campo = botao.closest('.campo');
        if (!select || !campo) { return; }

        // Caixa: [ nome ] [Salvar] [x]  + mensagem
        var caixa = document.createElement('div');
        caixa.className = 'cadastro-rapido';
        caixa.hidden = true;
        caixa.innerHTML =
            '<div class="cadastro-rapido-linha">' +
            '<input type="text" maxlength="100" placeholder="Nome do(a) ' + nomes[tipo] + ' novo(a)" aria-label="Nome do(a) ' + nomes[tipo] + ' novo(a)">' +
            '<button type="button" class="botao botao-primario botao-pequeno">Salvar</button>' +
            '<button type="button" class="botao botao-secundario botao-pequeno" title="Cancelar">&times;</button>' +
            '</div><small class="cadastro-rapido-aviso" hidden></small>';
        select.insertAdjacentElement('afterend', caixa);

        var input = caixa.querySelector('input');
        var salvar = caixa.querySelectorAll('button')[0];
        var fechar = caixa.querySelectorAll('button')[1];
        var aviso = caixa.querySelector('.cadastro-rapido-aviso');

        function mostrarAviso(texto, erro) {
            aviso.textContent = texto;
            aviso.className = 'cadastro-rapido-aviso' + (erro ? ' campo-erro' : '');
            aviso.hidden = texto === '';
        }

        function abrir() {
            caixa.hidden = false;
            mostrarAviso('', false);
            input.value = '';
            input.focus();
        }

        function fecharCaixa() {
            caixa.hidden = true;
        }

        function enviar() {
            var nome = input.value.trim();
            if (nome.length < 2) { mostrarAviso('Digite pelo menos 2 letras.', true); input.focus(); return; }

            var dados = new FormData();
            dados.append('tipo', tipo);
            dados.append('nome', nome);
            var grupo = document.getElementById('grupo');
            if (tipo === 'subgrupo' && grupo) { dados.append('grupo', grupo.value); }

            salvar.disabled = true;
            fetch('produto_cadastro_rapido.php', { method: 'POST', body: dados })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (!r.ok) { mostrarAviso(r.erro || 'Não foi possível salvar.', true); return; }

                    var opcao = select.querySelector('option[value="' + r.id + '"]');
                    if (!opcao) {
                        opcao = document.createElement('option');
                        opcao.value = String(r.id);
                        opcao.textContent = r.nome;
                        select.appendChild(opcao);
                    }
                    select.value = String(r.id);
                    fecharCaixa();

                    var msg = campo.querySelector('.cadastro-rapido-ok');
                    if (!msg) {
                        msg = document.createElement('small');
                        msg.className = 'cadastro-rapido-ok';
                        select.insertAdjacentElement('afterend', msg);
                    }
                    msg.textContent = r.existia ? '"' + r.nome + '" já existia e foi escolhido(a).' : '"' + r.nome + '" cadastrado(a).';
                    setTimeout(function () { msg.textContent = ''; }, 5000);
                })
                .catch(function () { mostrarAviso('Não foi possível falar com o servidor.', true); })
                .then(function () { salvar.disabled = false; });
        }

        botao.addEventListener('click', function () { if (caixa.hidden) { abrir(); } else { fecharCaixa(); } });
        fechar.addEventListener('click', fecharCaixa);
        salvar.addEventListener('click', enviar);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); enviar(); }      // Enter não envia o formulário do produto
            if (ev.key === 'Escape') { ev.preventDefault(); fecharCaixa(); }
        });
    });
})();
</script>
