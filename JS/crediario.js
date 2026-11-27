/**
 * crediario.js - Tela de crediário: ao digitar um valor diferente da parcela, mostra como as próximas
 * parcelas vão ficar e pede confirmação antes de dar a baixa. O cálculo de verdade é feito no servidor.
 */
(function () {
    'use strict';

    var moeda = function (c) { return 'R$ ' + (c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
    var centavos = function (t) {
        t = String(t || '').trim();
        if (t.indexOf(',') >= 0) { t = t.replace(/\./g, '').replace(',', '.'); }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : Math.round(n * 100);
    };

    function explicar(form) {
        var saldo = centavos(form.getAttribute('data-saldo'));
        var n = Number(form.getAttribute('data-seguintes')) || 0;
        var totalSeg = centavos(form.getAttribute('data-total-seguintes'));
        var pago = centavos(form.querySelector('[name="valor_pago"]').value);
        var dif = saldo - pago;

        if (pago <= 0) { return { texto: 'Informe o valor que a cliente pagou.', erro: true }; }
        if (dif === 0) { return { texto: '', erro: false }; }
        if (pago > saldo + totalSeg) { return { texto: 'Valor maior que tudo o que falta (' + moeda(saldo + totalSeg) + ').', erro: true }; }
        if (dif > 0) {
            if (n === 0) { return { texto: 'Faltam ' + moeda(dif) + ': será criada uma parcela nova um mês depois da última.', erro: false }; }
            return { texto: 'Faltam ' + moeda(dif) + ': ' + (n === 1 ? 'somado à parcela seguinte.' : 'dividido entre as ' + n + ' parcelas seguintes (+' + moeda(Math.floor(dif / n)) + ' em cada).'), erro: false };
        }
        return { texto: 'Pagou ' + moeda(-dif) + ' a mais: ' + (n === 1 ? 'descontado da parcela seguinte.' : 'descontado das ' + n + ' parcelas seguintes, por igual.'), erro: false };
    }

    document.querySelectorAll('form.form-baixa').forEach(function (form) {
        var campo = form.querySelector('[name="valor_pago"]');
        var previa = form.querySelector('.previa-baixa');
        if (!campo || !previa) { return; }

        function atualizar() {
            var r = explicar(form);
            previa.textContent = r.texto;
            previa.hidden = r.texto === '';
            previa.classList.toggle('erro', r.erro);
            return r;
        }
        campo.addEventListener('input', atualizar);

        form.addEventListener('submit', function (ev) {
            var r = atualizar();
            if (r.erro) { ev.preventDefault(); return; }
            if (r.texto !== '' && !window.confirm('Parcela ' + form.getAttribute('data-parcela') + ': ' + r.texto + ' Confirmar a baixa?')) {
                ev.preventDefault();
            }
        });
    });
})();
