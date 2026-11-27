/**
 * Mostra só o campo de abrangência relevante no formulário de exceções.
 * Sem inline handlers: a CSP do sistema não permite onclick/onchange.
 */
(function () {
  'use strict';

  function aplicar(form) {
    if (!form) return;
    var seletor = form.querySelector('[data-escopo-select]');
    if (!seletor) return;
    var atual = seletor.value;
    var grupos = form.querySelectorAll('[data-escopo]');
    for (var i = 0; i < grupos.length; i++) {
      var grupo = grupos[i];
      var visivel = grupo.getAttribute('data-escopo') === atual;
      if (grupo.classList) grupo.classList.toggle('d-none', !visivel);
      else grupo.style.display = visivel ? '' : 'none';
    }
  }

  function iniciar() {
    var seletores = document.querySelectorAll('[data-escopo-select]');
    for (var i = 0; i < seletores.length; i++) aplicar(seletores[i].form);
  }

  document.addEventListener('change', function (evento) {
    if (evento.target && evento.target.matches && evento.target.matches('[data-escopo-select]')) {
      aplicar(evento.target.form);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
