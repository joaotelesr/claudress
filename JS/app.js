/**
 * app.js - Comportamentos comuns: menu no celular, confirmação de formulários e fechar alertas.
 */
(function () {
    'use strict';

    var menu = document.getElementById('botao-menu');
    var sidebar = document.getElementById('sidebar');
    if (menu && sidebar) {
        menu.addEventListener('click', function () { sidebar.classList.toggle('aberta'); });
        document.addEventListener('click', function (ev) {
            if (sidebar.classList.contains('aberta') && !sidebar.contains(ev.target) && !menu.contains(ev.target)) {
                sidebar.classList.remove('aberta');
            }
        });
    }

    // <form data-confirmar="texto"> pergunta antes de enviar
    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirmar'))) { ev.preventDefault(); }
        });
    });

    // Alertas de sucesso somem sozinhos
    document.querySelectorAll('.alerta-sucesso').forEach(function (a) {
        setTimeout(function () { a.style.display = 'none'; }, 6000);
    });
})();
