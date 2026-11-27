/**
 * cliente_form.js - Tela de cadastro de cliente.
 *  1) Ao digitar o CEP completo, busca o endereço no ViaCEP e preenche rua, bairro, cidade e UF.
 *  2) Ao salvar, se faltar algum campo pedido (marcados com *), pergunta se quer salvar mesmo assim.
 *     Celular, e-mail e complemento são opcionais e não entram no aviso.
 */
(function () {
    'use strict';

    var form = document.getElementById('form-cliente');
    if (!form) { return; }

    function campo(id) { return document.getElementById(id); }

    /* ---------- 1) CEP -> endereço ---------- */
    var cep = form.querySelector('[data-buscar-cep]');
    var situacao = campo('cep-situacao');
    var ultimoBuscado = cep ? cep.value.replace(/\D/g, '') : '';

    function avisarCep(texto, erro) {
        if (!situacao) { return; }
        situacao.textContent = texto;
        situacao.className = 'cep-situacao' + (erro ? ' cep-situacao-erro' : '');
    }

    function preencher(id, valor) {
        var c = campo(id);
        if (c && valor) {
            c.value = valor;
            c.classList.remove('campo-pendente');
        }
    }

    function buscarCep() {
        var digitos = cep.value.replace(/\D/g, '');
        if (digitos.length !== 8 || digitos === ultimoBuscado) { return; }
        ultimoBuscado = digitos;
        avisarCep('Buscando endereço...', false);

        fetch('https://viacep.com.br/ws/' + digitos + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (cep.value.replace(/\D/g, '') !== digitos) { return; } // o CEP mudou enquanto buscava
                if (!d || d.erro) {
                    avisarCep('CEP não encontrado. Preencha o endereço manualmente.', true);
                    return;
                }
                preencher('endereco', d.logradouro);
                preencher('bairro', d.bairro);
                preencher('cidade', d.localidade);
                preencher('uf', d.uf);
                var comp = campo('complemento');
                if (comp && !comp.value && d.complemento) { comp.value = d.complemento; }
                avisarCep('Endereço preenchido pelo CEP. Confira e informe o número.', false);
                var numero = campo('numero');
                if (numero) { numero.focus(); }
            })
            .catch(function () {
                ultimoBuscado = '';
                avisarCep('Não foi possível buscar o CEP agora (sem internet?). Preencha manualmente.', true);
            });
    }

    if (cep) {
        cep.addEventListener('input', buscarCep);
        cep.addEventListener('blur', buscarCep);
    }

    /* ---------- 2) Aviso de campos faltando ---------- */
    var pedidos = form.querySelectorAll('[data-pedido]');

    pedidos.forEach(function (c) {
        c.addEventListener('input', function () { if (c.value.trim() !== '') { c.classList.remove('campo-pendente'); } });
        c.addEventListener('change', function () { if (c.value.trim() !== '') { c.classList.remove('campo-pendente'); } });
    });

    form.addEventListener('submit', function (ev) {
        var faltando = [];
        var primeiro = null;

        pedidos.forEach(function (c) {
            if (c.value.trim() === '') {
                faltando.push(c.getAttribute('data-pedido'));
                c.classList.add('campo-pendente');
                if (!primeiro) { primeiro = c; }
            } else {
                c.classList.remove('campo-pendente');
            }
        });

        if (faltando.length === 0) { return; }

        var ok = window.confirm(
            'Faltam dados neste cadastro:\n\n- ' + faltando.join('\n- ') +
            '\n\nTem certeza que deseja salvar o cliente sem esses dados?\n' +
            '(Ele vai aparecer no filtro "Dados incompletos" da lista de clientes para completar depois.)'
        );
        if (!ok) {
            ev.preventDefault();
            if (primeiro) { primeiro.focus(); }
        }
    });
})();
