/**
 * venda.js - Tela de nova venda (vanilla JS)
 * Depende de window.NOVA_VENDA = { formas: [...], estado: {...}, crediario: {...} } definido em nova_venda.php
 * Crediário: a linha de pagamento ganha "nº de parcelas" e "1º vencimento", com a prévia das parcelas.
 * Crédito na loja: ao escolher a cliente, busca o saldo (buscar.php?tipo=credito) e mostra "Pagar com crédito da loja".
 */
(function () {
    'use strict';

    var cfg = window.NOVA_VENDA || { formas: [], estado: {} };
    var formas = cfg.formas;
    var estado = cfg.estado || {};
    var itens = [];       // {id, nome, tamanho, preco, estoque, qtd}
    var pagamentos = [];  // {forma_id, valor, bandeira_id, auto, parcelas, vencimento}
    var cred = cfg.crediario || { max: 12, primeiro: '', min: '', limite: '' };
    var saldoCredito = 0; // em centavos, da cliente escolhida

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
               ' · ' + moeda(p.preco) + ' · estoque ' + p.estoque +
               (p.fornecedor ? ' · ' + escapar(p.fornecedor) : '') + '</small>';
    });

    function escolherCliente(c) {
        $('cliente-id').value = c ? c.id : '';
        var rotulo = $('cliente-escolhido');
        rotulo.innerHTML = c ? 'Cliente: <strong>' + escapar(c.nome) + '</strong> <a href="#" id="remover-cliente">remover</a>' : '';
        var rem = $('remover-cliente');
        if (rem) { rem.addEventListener('click', function (e) { e.preventDefault(); escolherCliente(null); }); }
        mostrarCredito(c && typeof c.credito === 'number' ? cent(c.credito) : null);
        if (c) {
            // Confere o saldo atualizado no servidor
            var pedido = c.id;
            fetch('buscar.php?tipo=credito&id=' + encodeURIComponent(c.id))
                .then(function (r) { return r.json(); })
                .then(function (d) { if ($('cliente-id').value === String(pedido) && typeof d.credito === 'number') { mostrarCredito(cent(d.credito)); } })
                .catch(function () {});
        }
        resumo();
    }

    // ---------- Crédito na loja ----------
    // saldo = centavos (null = ainda carregando); sem cliente esconde o quadro
    function mostrarCredito(saldo) {
        var bloco = $('bloco-credito'), info = $('credito-info'), campo = $('campo-credito');
        if (!bloco) { return; }
        var temCliente = $('cliente-id').value !== '';
        bloco.hidden = !temCliente;
        if (!temCliente) { saldoCredito = 0; $('credito_usar').value = ''; resumo(); return; }
        if (saldo === null) { info.innerHTML = 'Conferindo o crédito da cliente...'; return; }
        saldoCredito = saldo;
        if (saldo > 0) {
            info.innerHTML = 'Crédito na loja disponível: <strong>' + moeda(saldo / 100) + '</strong>';
            bloco.className = 'bloco-credito tem-credito';
            campo.hidden = false;
        } else {
            info.innerHTML = 'Esta cliente não tem crédito na loja.';
            bloco.className = 'bloco-credito';
            campo.hidden = true;
            $('credito_usar').value = '';
        }
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

    // ---------- Crediário (mesma conta do servidor: centavos que sobram vão na 1ª parcela) ----------
    function somarMeses(iso, meses) {
        var p = String(iso || '').split('-');
        if (p.length !== 3) { return ''; }
        var ano = Number(p[0]), mes = Number(p[1]) - 1 + meses, dia = Number(p[2]);
        ano += Math.floor(mes / 12);
        mes = ((mes % 12) + 12) % 12;
        var ultimo = new Date(ano, mes + 1, 0).getDate();
        var d = Math.min(dia, ultimo);
        return ano + '-' + String(mes + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }
    function dataBr(iso) { var p = String(iso || '').split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : ''; }

    function previaParcelas(p) {
        var n = Math.max(1, Number(p.parcelas) || 1);
        var total = cent(p.valor);
        if (total <= 0 || !p.vencimento) { return 'Informe o valor e o 1º vencimento.'; }
        var base = Math.floor(total / n), resto = total - base * n;
        var linhas = [];
        for (var i = 1; i <= n; i++) {
            linhas.push('<li><span>' + i + '/' + n + ' · ' + dataBr(somarMeses(p.vencimento, i - 1)) + '</span><strong>' +
                        moeda((base + (i === 1 ? resto : 0)) / 100) + '</strong></li>');
        }
        var titulo = resto === 0 ? n + 'x de ' + moeda(base / 100) : n + ' parcelas (1ª de ' + moeda((base + resto) / 100) + ')';
        return '<strong>' + titulo + '</strong><ul>' + linhas.join('') + '</ul>';
    }

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

            var crediario = '';
            if (forma && forma.crediario) {
                p.parcelas = Math.min(cred.max, Math.max(1, Number(p.parcelas) || 1));
                p.vencimento = p.vencimento || cred.primeiro;
                var opParc = '';
                for (var n = 1; n <= cred.max; n++) {
                    opParc += '<option value="' + n + '"' + (n === p.parcelas ? ' selected' : '') + '>' + n + 'x</option>';
                }
                crediario =
                    '<div class="crediario-opcoes">' +
                    '<label>Parcelas<select name="parcelas_pagto[]" class="p-parcelas">' + opParc + '</select></label>' +
                    '<label>1º vencimento<input type="date" name="venc_pagto[]" class="p-venc" value="' + escapar(p.vencimento) +
                    '" min="' + escapar(cred.min) + '" max="' + escapar(cred.limite) + '"></label>' +
                    '</div><div class="crediario-previa"></div>';
            } else {
                crediario = '<input type="hidden" name="parcelas_pagto[]" value="0"><input type="hidden" name="venc_pagto[]" value="">';
            }

            div.innerHTML =
                '<select name="forma_id[]" class="p-forma">' + opcoes + '</select>' + bandeiras +
                '<input type="text" name="valor_pagto[]" class="p-valor" inputmode="decimal" placeholder="0,00" value="' +
                (p.valor > 0 ? p.valor.toFixed(2).replace('.', ',') : '') + '">' + crediario +
                (pagamentos.length > 1 ? '<button type="button" class="botao botao-perigo botao-pequeno" title="Remover">&times;</button>' : '');
            if (forma && forma.crediario) { div.classList.add('linha-crediario'); }

            div.querySelector('.p-forma').addEventListener('change', function (e) {
                p.forma_id = Number(e.target.value);
                var f = formaPorId(p.forma_id);
                p.bandeira_id = f && f.bandeiras.length ? f.bandeiras[0].id : 0;
                desenharPagamentos();
            });
            var selParc = div.querySelector('.p-parcelas');
            if (selParc) { selParc.addEventListener('change', function (e) { p.parcelas = Number(e.target.value); resumo(); }); }
            var campoVenc = div.querySelector('.p-venc');
            if (campoVenc) { campoVenc.addEventListener('input', function (e) { p.vencimento = e.target.value; resumo(); }); }
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
        var campoCred = $('credito_usar');
        var credPedido = campoCred && $('cliente-id').value !== '' ? cent(decimal(campoCred.value)) : 0;
        var credMax = Math.min(saldoCredito, Math.max(0, sub - desc));   // não passa do saldo nem do valor da venda
        var credito = Math.max(0, Math.min(credPedido, credMax));
        var total = Math.max(0, sub - desc - credito);
        var pago = pagamentos.reduce(function (s, p) { return s + cent(p.valor); }, 0);
        return { sub: sub, desc: desc, credito: credito, credPedido: credPedido, credMax: credMax, total: total, pago: pago };
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
        // Prévia das parcelas do crediário
        var linhasPagto = document.querySelectorAll('#pagamentos .linha-pagamento');
        var qtdCrediario = 0;
        pagamentos.forEach(function (p, idx) {
            var f = formaPorId(p.forma_id);
            var previa = linhasPagto[idx] ? linhasPagto[idx].querySelector('.crediario-previa') : null;
            if (f && f.crediario) { qtdCrediario++; }
            if (previa) { previa.innerHTML = previaParcelas(p); }
        });
        var t = totais();
        $('r-subtotal').textContent = moeda(t.sub / 100);
        $('r-desconto').textContent = moeda(t.desc / 100);
        $('linha-credito').hidden = t.credito === 0;
        $('r-credito').textContent = '- ' + moeda(t.credito / 100);
        var avisoCred = $('credito-aviso');
        var credExcede = t.credPedido > t.credMax;
        if (avisoCred) {
            avisoCred.hidden = !credExcede;
            avisoCred.textContent = credExcede ? 'Máximo que pode usar agora: ' + moeda(t.credMax / 100) + '.' : '';
        }
        $('r-total').textContent = moeda(t.total / 100);
        $('r-pago').textContent = moeda(t.pago / 100);
        var dif = t.total - t.pago;
        var el = $('r-dif');
        el.textContent = moeda(dif / 100);
        el.className = dif === 0 ? 'ok' : 'alerta-texto';
        // A cliente é obrigatória tanto para finalizar quanto para deixar em condição
        var temCliente = $('cliente-id').value !== '';
        // Crédito da loja que cobre a venda inteira: fecha com total zero (sem pagamento)
        var creditoCobre = t.credito > 0 && t.total === 0 && t.desc <= t.sub;
        $('finalizar').disabled = !(temCliente && itens.length > 0 && qtdCrediario <= 1 && !credExcede &&
            (creditoCobre || (pagamentos.length > 0 && dif === 0 && t.total > 0)));
        if (qtdCrediario > 1) { el.textContent = 'Use o crediário em uma linha só'; el.className = 'alerta-texto'; }
        if (creditoCobre) { el.textContent = moeda(0); el.className = 'ok'; }
        if (credExcede) { el.textContent = 'Diminua o crédito usado'; el.className = 'alerta-texto'; }
        if ($('condicao')) { $('condicao').disabled = !(temCliente && itens.length > 0); }
        $('aviso-cliente').hidden = temCliente || itens.length === 0;
    }

    function desenhar() { desenharItens(); desenharPagamentos(); resumo(); }

    // ---------- Início ----------
    $('desconto').addEventListener('input', resumo);
    if ($('credito_usar')) {
        $('credito_usar').addEventListener('input', resumo);
        $('credito-maximo').addEventListener('click', function () {
            var t = totais();
            $('credito_usar').value = (t.credMax / 100).toFixed(2).replace('.', ',');
            resumo();
        });
    }

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
    (estado.pagamentos || []).forEach(function (p) {
        pagamentos.push({ forma_id: p.forma_id, valor: p.valor, bandeira_id: p.bandeira_id, auto: false, parcelas: p.parcelas || 0, vencimento: p.vencimento || '' });
    });
    if (estado.cliente) { escolherCliente(estado.cliente); }
    if (!pagamentos.length && formas.length) {
        pagamentos.push({ forma_id: formas[0].id, valor: 0, bandeira_id: formas[0].bandeiras.length ? formas[0].bandeiras[0].id : 0, auto: true });
    }
    desenhar();
})();
