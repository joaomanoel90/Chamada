/**
 * Filtros do calendário do dashboard sem recarregar a página.
 * O endpoint dashboard_calendario.php devolve somente o HTML do card.
 */
(function () {
  'use strict';

  var CONTAINER = 'calendarioDashboard';
  var requisicao = null;

  function dadosDoFormulario(form) {
    var p = new URLSearchParams();
    var dados = new FormData(form);
    dados.forEach(function (valor, nome) { p.append(nome, valor); });
    return p.toString();
  }

  function carregar(query, urlPublica) {
    var caixa = document.getElementById(CONTAINER);
    if (!caixa) return;

    if (requisicao) requisicao.abort();
    var controlador = window.AbortController ? new AbortController() : null;
    requisicao = controlador;

    var aviso = caixa.querySelector('.cal-aviso');
    if (aviso) aviso.textContent = 'Atualizando…';
    caixa.setAttribute('aria-busy', 'true');

    var opcoes = {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (controlador) opcoes.signal = controlador.signal;

    fetch('dashboard_calendario.php?' + query, opcoes)
      .then(function (resposta) {
        if (!resposta.ok) throw new Error('HTTP ' + resposta.status);
        return resposta.text();
      })
      .then(function (html) {
        caixa.innerHTML = html;
        caixa.removeAttribute('aria-busy');
        if (urlPublica && window.history && history.replaceState) {
          history.replaceState(null, '', urlPublica);
        }
      })
      .catch(function (erro) {
        if (erro && erro.name === 'AbortError') return;
        caixa.removeAttribute('aria-busy');
        var avisoErro = caixa.querySelector('.cal-aviso');
        if (avisoErro) avisoErro.textContent = 'Não foi possível atualizar o calendário.';
      });
  }

  document.addEventListener('submit', function (evento) {
    var form = evento.target;
    if (!form || !form.hasAttribute || !form.hasAttribute('data-cal-ajax')) return;
    evento.preventDefault();
    var query = dadosDoFormulario(form);
    var acao = form.getAttribute('action') || 'index.php';
    carregar(query, acao + '?' + query);
  }, true);

  document.addEventListener('change', function (evento) {
    var alvo = evento.target;
    if (!alvo || !alvo.form || !alvo.form.hasAttribute('data-cal-ajax')) return;
    var query = dadosDoFormulario(alvo.form);
    var acao = alvo.form.getAttribute('action') || 'index.php';
    carregar(query, acao + '?' + query);
  });

  document.addEventListener('click', function (evento) {
    if (!evento.target || typeof evento.target.closest !== 'function') return;
    var link = evento.target.closest('[data-cal-mes]');
    if (!link) return;
    evento.preventDefault();
    var url = new URL(link.href, window.location.href);
    carregar(url.searchParams.toString(), link.getAttribute('href'));
  });
})();
