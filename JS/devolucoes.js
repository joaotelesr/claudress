/**
 * devolucao.js - Tela de devolução e troca (vanilla JS)
 * - Pesquisa de cliente: sugestões enquanto digita (devolucoes.php?acao=buscar_cliente). Se o JavaScript falhar,
 *   o botão Pesquisar (ou Enter) faz a mesma busca pelo servidor.
 * - Em cada venda: soma as peças marcadas, mostra/esconde "Devolver o dinheiro em" e confirma antes de gravar.
 */
(function () {
    'use strict';

    var moeda = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
    var escapar = function (t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    };

    // ---------- Pesquisa de cliente ----------
    var input = document.getElementById('cliente-busca');
    var lista = document.getElementById('cliente-sugestoes');
    if (input && lista) {
        var timer = null, ultimo = 0;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { lista.hidden = true; return; }
            timer = setTimeout(function () {
                var chamada = ++ultimo;
                fetch('devolucoes.php?acao=buscar_cliente&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (dados) {
                        if (chamada !== ultimo) { return; }
                        lista.innerHTML = '';
                        if (!dados.length) { lista.innerHTML = '<li class="sugestao-vazia">Nenhuma cliente encontrada</li>'; }
                        dados.forEach(function (c) {
                            var li = document.createElement('li');
                            li.innerHTML = '<strong>' + escapar(c.nome) + '</strong> <small>' + escapar(c.cpf) +
                                (c.cpf ? ' · ' : '') + c.compras + ' compra(s)</small>';
                            li.addEventListener('mousedown', function (ev) {
                                ev.preventDefault();
                                window.location.href = 'devolucoes.php?cliente=' + encodeURIComponent(c.id);
                            });
                            lista.appendChild(li);
                        });
                        lista.hidden = false;
                    })
                    .catch(function () { lista.hidden = true; });
            }, 250);
        });
        input.addEventListener('blur', function () { setTimeout(function () { lista.hidden = true; }, 150); });
    }

    // ---------- Formulário de cada venda ----------
    document.querySelectorAll('form.form-devolucao').forEach(function (form) {
        var campos = form.querySelectorAll('.qtd-devolver');
        var resumo = form.querySelector('.resumo-devolucao');
        var botao = form.querySelector('.confirmar-devolucao');
        var reembolso = form.querySelector('.campo-reembolso');
        if (!botao) { return; }

        function tipo() {
            var r = form.querySelector('input[name="tipo"]:checked');
            return r ? r.value : 'Devolucao';
        }

        function calcular() {
            var pecas = 0, valor = 0;
            campos.forEach(function (c) {
                var max = parseInt(c.max, 10) || 0;
                var n = parseInt(c.value, 10);
                if (isNaN(n) || n < 0) { n = 0; }
                if (n > max) { n = max; c.value = max; }
                var linha = c.closest('tr').querySelector('.valor-linha');
                var v = n * parseFloat(c.getAttribute('data-valor') || '0');
                linha.textContent = n > 0 ? moeda(v) : '-';
                pecas += n;
                valor += v;
            });
            var troca = tipo() === 'Troca';
            reembolso.hidden = troca;
            botao.textContent = troca ? 'Confirmar troca' : 'Confirmar devolução';
            botao.disabled = pecas === 0;
            resumo.innerHTML = pecas === 0
                ? 'Nenhuma peça marcada'
                : '<strong>' + pecas + '</strong> peça(s) · <strong>' + moeda(valor) + '</strong>' + (troca ? ' de crédito' : ' a devolver');
            return { pecas: pecas, valor: valor, troca: troca };
        }

        campos.forEach(function (c) { c.addEventListener('input', calcular); });
        form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', calcular); });

        var tudo = form.querySelector('.devolver-tudo');
        if (tudo) {
            tudo.addEventListener('click', function () {
                campos.forEach(function (c) { c.value = c.max; });
                calcular();
            });
        }

        form.addEventListener('submit', function (ev) {
            var t = calcular();
            if (t.pecas === 0) { ev.preventDefault(); return; }
            var texto = t.troca
                ? 'Registrar a TROCA de ' + t.pecas + ' peça(s)? As peças voltam ao estoque e a cliente fica com ' + moeda(t.valor) + ' de crédito.'
                : 'Registrar a DEVOLUÇÃO de ' + t.pecas + ' peça(s)? As peças voltam ao estoque e a cliente recebe ' + moeda(t.valor) + ' de volta.';
            if (!window.confirm(texto)) { ev.preventDefault(); return; }
            botao.disabled = true;   // evita clique duplo
        });

        calcular();
    });
})();
