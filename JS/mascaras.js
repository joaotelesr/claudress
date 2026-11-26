/**
 * mascaras.js - Máscaras de digitação. Uso: <input data-mascara="cpf|telefone|cep">
 * Os valores são gravados só com dígitos no servidor, então a máscara é apenas visual.
 */
(function () {
    'use strict';

    var mascaras = {
        cpf: function (v) {
            v = v.replace(/\D/g, '').slice(0, 11);
            return v.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1-$2');
        },
        telefone: function (v) {
            v = v.replace(/\D/g, '').slice(0, 11);
            if (v.length > 10) { return v.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3'); }
            if (v.length > 6)  { return v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3'); }
            if (v.length > 2)  { return v.replace(/^(\d{2})(\d*)/, '($1) $2'); }
            return v;
        },
        cep: function (v) {
            v = v.replace(/\D/g, '').slice(0, 8);
            return v.length > 5 ? v.replace(/^(\d{5})(\d*)/, '$1-$2') : v;
        }
    };

    document.querySelectorAll('input[data-mascara]').forEach(function (campo) {
        var f = mascaras[campo.getAttribute('data-mascara')];
        if (!f) { return; }
        campo.addEventListener('input', function () { campo.value = f(campo.value); });
    });
})();
