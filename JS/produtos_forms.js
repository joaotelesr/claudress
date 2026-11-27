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
