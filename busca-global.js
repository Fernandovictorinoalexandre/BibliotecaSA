// ═══════════════════════════════════════════════════════
// busca-global.js — Barra de pesquisa global
// Inclua em todas as páginas do portal do leitor
//
// Como usar no HTML:
//   <input class="search" id="buscaGlobal" type="text"
//          placeholder="Pesquisar livro...">
//   <script src="busca-global.js"></script>
// ═══════════════════════════════════════════════════════

(function () {

  // Aguarda o DOM estar pronto
  document.addEventListener('DOMContentLoaded', function () {

    // Pega TODOS os inputs de busca do header (.search)
    // exceto os que já têm função própria (campoBusca do catálogo)
    const inputs = document.querySelectorAll(
      'input.search:not(#campoBusca)'
    );

    inputs.forEach(function (input) {

      // Define placeholder padrão
      if (!input.placeholder || input.placeholder === 'Pesquisar...') {
        input.placeholder = 'Pesquisar livro...';
      }

      // ── ENTER → redireciona para o catálogo ──────────
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          const termo = input.value.trim();
          if (termo.length > 0) {
            irParaCatalogo(termo);
          }
        }
      });

      // ── Ícone de lupa ao lado do input (se não existir) ──
      // Adiciona botão de busca visível para mobile
      const wrapper = input.parentElement;
      if (wrapper && !wrapper.querySelector('.btn-buscar-global')) {
        input.style.paddingRight = '2.5rem';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-buscar-global';
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">search</span>';
        btn.style.cssText = `
          position: absolute;
          right: .5rem;
          top: 50%;
          transform: translateY(-50%);
          background: none;
          border: none;
          color: var(--primary);
          cursor: pointer;
          display: flex;
          align-items: center;
          padding: 4px;
          opacity: .7;
          transition: opacity .2s;
        `;
        btn.onmouseenter = () => btn.style.opacity = '1';
        btn.onmouseleave = () => btn.style.opacity = '.7';
        btn.onclick = function () {
          const termo = input.value.trim();
          if (termo.length > 0) irParaCatalogo(termo);
        };

        // Garante que o wrapper tem position relative
        const wrapperStyle = getComputedStyle(wrapper);
        if (wrapperStyle.position === 'static') {
          wrapper.style.position = 'relative';
        }
        wrapper.appendChild(btn);
      }
    });
  });

  // ── Redireciona para o catálogo com o termo na URL ──
  function irParaCatalogo(termo) {
    const url = 'CatalogoLivros.html?busca=' + encodeURIComponent(termo);
    window.location.href = url;
  }

})();
