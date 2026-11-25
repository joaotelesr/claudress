/**
 * mascaras.js - máscaras de digitação.
 * Uso no HTML: <input data-mascara="cpf"> ou <input data-mascara="telefone">
 */
(function () {
    function digitos(valor) {
        return valor.replace(/\D/g, '');
    }

    var mascaras = {
        cpf: function (valor) {
            valor = digitos(valor).slice(0, 11);
            return valor
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        },

        telefone: function (valor) {
            valor = digitos(valor).slice(0, 11);
            if (valor.length > 10) {
                return valor.replace(/(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
            }
            if (valor.length > 6) {
                return valor.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
            }
            if (valor.length > 2) {
                return valor.replace(/(\d{2})(\d+)/, '($1) $2');
            }
            return valor;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-mascara]').forEach(function (campo) {
            var aplicar = mascaras[campo.dataset.mascara];
            if (!aplicar) { return; }

            campo.addEventListener('input', function () {
                campo.value = aplicar(campo.value);
            });
            campo.value = aplicar(campo.value);
        });
    });
})();