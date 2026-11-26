/**
 * venda.js - Tela de nova venda (vanilla JS)
 * Depende de window.NOVA_VENDA = { formas: [...], estado: {...} } definido em nova_venda.php
 */
(function () {
    'use strict';

    var cfg = window.NOVA_VENDA || { formas: [], estado: {} };
    var formas = cfg.formas;
    var estado = cfg.estado || {};
    var itens = [];       // {id, nome, tamanho, preco, estoque, qtd}
    var pagamentos = [];  // {forma_id, valor, bandeira_id, auto}

    var $ = function (id) { return document.getElementById(id); };
    var moeda = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
    var decimal = function (t) {
        t = String(t || '').trim().replace(/\./g, '').replace(',', '.');
        var n = parseFloat(t);
        return isNaN(n) ? 0 : n;
    };
    var cent = function (v) { return Math.round(v * 100); };
    var escapar = function (t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    };

    // ---------- Autocomplete ----------
    function autocomplete(inputId, listaId, tipo, aoEscolher, descrever) {
        var input = $(inputId), lista = $(listaId), timer = null, ultimo = 0;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { lista.hidden = true; return; }
            timer = setTimeout(function () {
                var chamada = ++ultimo;
                fetch('buscar.php?tipo=' + tipo + '&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (dados) {
                        if (chamada !== ultimo) { return; }   // resposta antiga, ignora
                        lista.innerHTML = '';
                        if (!dados.length) {
                            lista.innerHTML = '<li class="sugestao-vazia">Nada encontrado</li>';
                        }
                        dados.forEach(function (d) {
                            var li = document.createElement('li');
                            li.innerHTML = descrever(d);
                            li.addEventListener('mousedown', function (ev) {
                                ev.preventDefault();
                                aoEscolher(d);
                                input.value = '';
                                lista.hidden = true;
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

    autocomplete('produto-busca', 'produto-sugestoes', 'produto', function (p) {
        var existente = itens.find(function (i) { return i.id === p.id; });
        if (existente) {
            existente.qtd = Math.min(existente.qtd + 1, existente.estoque);
        } else {
            itens.push({ id: p.id, nome: p.nome, tamanho: p.tamanho, preco: p.preco, estoque: p.estoque, qtd: 1 });
        }
        desenhar();
    }, function (p) {
        return '<strong>' + escapar(p.nome) + '</strong> <small>Tam. ' + escapar(p.tamanho || '-') +
               ' · ' + moeda(p.preco) + ' · estoque ' + p.estoque + '</small>';
    });

    function escolherCliente(c) {
        $('cliente-id').value = c ? c.id : '';
        var rotulo = $('cliente-escolhido');
        rotulo.innerHTML = c ? 'Cliente: <strong>' + escapar(c.nome) + '</strong> <a href="#" id="remover-cliente">remover</a>' : '';
        var rem = $('remover-cliente');
        if (rem) { rem.addEventListener('click', function (e) { e.preventDefault(); escolherCliente(null); }); }
        resumo();
    }

    autocomplete('cliente-busca', 'cliente-sugestoes', 'cliente', escolherCliente, function (c) {
        return '<strong>' + escapar(c.nome) + '</strong> <small>' + escapar(c.cpf) + '</small>';
    });

    // ---------- Itens ----------
    function desenharItens() {
        var corpo = $('itens');
        corpo.innerHTML = '';
        itens.forEach(function (it, idx) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="celula-nome"><strong>' + escapar(it.nome) + '</strong><small>Tam. ' + escapar(it.tamanho || '-') +
                ' · estoque ' + it.estoque + '</small>' +
                '<input type="hidden" name="produto_id[]" value="' + it.id + '"></td>' +
                '<td>' + moeda(it.preco) + '</td>' +
                '<td><input type="number" class="campo-qtd" name="quantidade[]" min="1" max="' + it.estoque + '" step="1" value="' + it.qtd + '"></td>' +
                '<td class="texto-direita"><strong>' + moeda(it.preco * it.qtd) + '</strong></td>' +
                '<td><button type="button" class="botao botao-perigo botao-pequeno" title="Remover">&times;</button></td>';
            tr.querySelector('.campo-qtd').addEventListener('input', function (e) {
                var n = parseInt(e.target.value, 10);
                it.qtd = isNaN(n) || n < 1 ? 1 : n;
                resumo();
                tr.children[3].innerHTML = '<strong>' + moeda(it.preco * it.qtd) + '</strong>';
            });
            tr.querySelector('button').addEventListener('click', function () { itens.splice(idx, 1); desenhar(); });
            corpo.appendChild(tr);
        });
        $('itens-vazio').hidden = itens.length > 0;
        $('tabela-itens').hidden = itens.length === 0;
    }

    // ---------- Pagamentos ----------
    function formaPorId(id) { return formas.find(function (f) { return f.id === Number(id); }); }

    function desenharPagamentos() {
        var area = $('pagamentos');
        area.innerHTML = '';
        pagamentos.forEach(function (p, idx) {
            var forma = formaPorId(p.forma_id) || formas[0];
            var div = document.createElement('div');
            div.className = 'linha-pagamento';

            var opcoes = formas.map(function (f) {
                return '<option value="' + f.id + '"' + (f.id === p.forma_id ? ' selected' : '') + '>' + escapar(f.rotulo) + '</option>';
            }).join('');
            var bandeiras = '';
            if (forma && forma.cartao && forma.bandeiras.length) {
                bandeiras = '<select name="bandeira_id[]" class="p-bandeira">' + forma.bandeiras.map(function (b) {
                    return '<option value="' + b.id + '"' + (b.id === p.bandeira_id ? ' selected' : '') + '>' + escapar(b.nome) + '</option>';
                }).join('') + '</select>';
            } else {
                bandeiras = '<input type="hidden" name="bandeira_id[]" value="0">';
            }

            div.innerHTML =
                '<select name="forma_id[]" class="p-forma">' + opcoes + '</select>' + bandeiras +
                '<input type="text" name="valor_pagto[]" class="p-valor" inputmode="decimal" placeholder="0,00" value="' +
                (p.valor > 0 ? p.valor.toFixed(2).replace('.', ',') : '') + '">' +
                (pagamentos.length > 1 ? '<button type="button" class="botao botao-perigo botao-pequeno" title="Remover">&times;</button>' : '');

            div.querySelector('.p-forma').addEventListener('change', function (e) {
                p.forma_id = Number(e.target.value);
                var f = formaPorId(p.forma_id);
                p.bandeira_id = f && f.bandeiras.length ? f.bandeiras[0].id : 0;
                desenharPagamentos();
            });
            var sel = div.querySelector('.p-bandeira');
            if (sel) { sel.addEventListener('change', function (e) { p.bandeira_id = Number(e.target.value); }); }
            div.querySelector('.p-valor').addEventListener('input', function (e) {
                p.valor = decimal(e.target.value);
                p.auto = false;
                resumo();
            });
            var rem = div.querySelector('button');
            if (rem) { rem.addEventListener('click', function () { pagamentos.splice(idx, 1); p.auto = false; desenhar(); }); }
            area.appendChild(div);
        });
    }

    // ---------- Totais ----------
    function totais() {
        var sub = itens.reduce(function (s, i) { return s + cent(i.preco) * i.qtd; }, 0);
        var desc = cent(decimal($('desconto').value));
        var total = Math.max(0, sub - desc);
        var pago = pagamentos.reduce(function (s, p) { return s + cent(p.valor); }, 0);
        return { sub: sub, desc: desc, total: total, pago: pago };
    }

    function resumo() {
        // Com um único pagamento "automático", o valor acompanha o total da venda
        if (pagamentos.length === 1 && pagamentos[0].auto) {
            pagamentos[0].valor = totais().total / 100;
            var campo = document.querySelector('.p-valor');
            if (campo && document.activeElement !== campo) {
                campo.value = pagamentos[0].valor > 0 ? pagamentos[0].valor.toFixed(2).replace('.', ',') : '';
            }
        }
        var t = totais();
        $('r-subtotal').textContent = moeda(t.sub / 100);
        $('r-desconto').textContent = moeda(t.desc / 100);
        $('r-total').textContent = moeda(t.total / 100);
        $('r-pago').textContent = moeda(t.pago / 100);
        var dif = t.total - t.pago;
        var el = $('r-dif');
        el.textContent = moeda(dif / 100);
        el.className = dif === 0 ? 'ok' : 'alerta-texto';
        // A cliente é obrigatória tanto para finalizar quanto para deixar em condição
        var temCliente = $('cliente-id').value !== '';
        // Troca: se o crédito cobre as peças novas inteiras, a venda fecha com total zero (sem pagamento)
        var trocaCoberta = !!estado.troca_id && t.total === 0 && t.desc <= t.sub;
        $('finalizar').disabled = !(temCliente && itens.length > 0 && (trocaCoberta || (pagamentos.length > 0 && dif === 0 && t.total > 0)));
        if (trocaCoberta) { el.textContent = moeda(0); el.className = 'ok'; }
        if (estado.troca_id && itens.length > 0 && t.desc > t.sub) {
            el.textContent = 'Crédito maior que as peças: diminua o desconto para ' + moeda(t.sub / 100) + ' e devolva ' + moeda((t.desc - t.sub) / 100);
            el.className = 'alerta-texto';
        }
        if ($('condicao')) { $('condicao').disabled = !(temCliente && itens.length > 0); }
        $('aviso-cliente').hidden = temCliente || itens.length === 0;
    }

    function desenhar() { desenharItens(); desenharPagamentos(); resumo(); }

    // ---------- Início ----------
    $('desconto').addEventListener('input', resumo);

    // Condição: confirma antes de gravar sem pagamento
    var botaoCondicao = $('condicao');
    if (botaoCondicao) {
        botaoCondicao.addEventListener('click', function (ev) {
            var nome = ($('cliente-escolhido').querySelector('strong') || {}).textContent || 'a cliente';
            if (!window.confirm('Deixar estas peças em condição para ' + nome + '? Elas saem do estoque e o pagamento fica para depois.')) {
                ev.preventDefault();
            }
        });
    }

    $('add-pagamento').addEventListener('click', function () {
        if (!formas.length) { return; }
        var t = totais();
        var f = formas[0];
        pagamentos.forEach(function (p) { p.auto = false; });
        pagamentos.push({ forma_id: f.id, valor: Math.max(0, (t.total - t.pago) / 100), bandeira_id: f.bandeiras.length ? f.bandeiras[0].id : 0, auto: false });
        desenhar();
    });

    // Repovoa a tela quando o servidor devolveu erros
    (estado.itens || []).forEach(function (i) { itens.push({ id: i.id, nome: i.nome, tamanho: i.tamanho, preco: i.preco, estoque: i.estoque, qtd: i.qtd }); });
    (estado.pagamentos || []).forEach(function (p) { pagamentos.push({ forma_id: p.forma_id, valor: p.valor, bandeira_id: p.bandeira_id, auto: false }); });
    if (estado.cliente) { escolherCliente(estado.cliente); }
    if (!pagamentos.length && formas.length) {
        pagamentos.push({ forma_id: formas[0].id, valor: 0, bandeira_id: formas[0].bandeiras.length ? formas[0].bandeiras[0].id : 0, auto: true });
    }
    desenhar();
})();
