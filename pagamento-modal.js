// ═══════════════════════════════════════════════════════
// pagamento-modal.js — Fluxo: Pagamento → Ticket
// Inclua este script em CatalogoLivros.html e LivroDetalhe.html
// ═══════════════════════════════════════════════════════

(function () {

  // ── Configurações ──────────────────────────────────────
  const PRECO         = 15.00;
  const CHAVE_PIX     = 'estacaoliteraria@biblioteca.com.br';
  const PRAZO_DIAS    = 30; // 1 mês

  // ── Estado ─────────────────────────────────────────────
  let livroAtual = null;

  // ── HTML do modal injetado no body ─────────────────────
  const modalHTML = `
  <div id="pg-overlay" class="pg-overlay" onclick="PG.fecharSeForaDoModal(event)">

    <!-- ══ PASSO 1: ESCOLHA DO MÉTODO ══ -->
    <div class="pg-modal" id="pg-passo1">
      <button class="pg-fechar" onclick="PG.fechar()">✕</button>

      <div class="pg-header">
        <div class="pg-icon">📚</div>
        <h2>Confirmar Empréstimo</h2>
        <p id="pg-titulo-livro" class="pg-livro-nome"></p>
      </div>

      <div class="pg-preco-box">
        <span class="pg-preco-label">Valor do empréstimo (30 dias)</span>
        <span class="pg-preco-valor">R$ 15,00</span>
      </div>

      <p class="pg-subtitulo">Escolha a forma de pagamento</p>

      <div class="pg-metodos">
        <button class="pg-metodo-btn" onclick="PG.irParaPix()">
          <span class="pg-metodo-icon">⚡</span>
          <div>
            <strong>PIX</strong>
            <small>Aprovação imediata</small>
          </div>
          <span class="pg-metodo-arrow">›</span>
        </button>

        <button class="pg-metodo-btn" onclick="PG.irParaCartao()">
          <span class="pg-metodo-icon">💳</span>
          <div>
            <strong>Cartão de Crédito</strong>
            <small>Débito ou crédito</small>
          </div>
          <span class="pg-metodo-arrow">›</span>
        </button>
      </div>
    </div>

    <!-- ══ PASSO 2A: PIX ══ -->
    <div class="pg-modal pg-hidden" id="pg-passo-pix">
      <button class="pg-fechar" onclick="PG.fechar()">✕</button>
      <button class="pg-voltar" onclick="PG.voltarParaMetodos()">← Voltar</button>

      <div class="pg-header">
        <div class="pg-icon">⚡</div>
        <h2>Pagar com PIX</h2>
        <p class="pg-preco-destaque">R$ 15,00</p>
      </div>

      <!-- QR Code gerado via CSS art -->
      <div class="pg-qr-wrap">
        <div class="pg-qr-code">
          <canvas id="pg-qr-canvas" width="180" height="180"></canvas>
        </div>
        <p class="pg-qr-instrucao">Escaneie o QR Code com o app do seu banco</p>
      </div>

      <div class="pg-pix-chave-box">
        <span class="pg-chave-label">Chave PIX (e-mail)</span>
        <div class="pg-chave-row">
          <span class="pg-chave-valor" id="pg-chave-texto">${CHAVE_PIX}</span>
          <button class="pg-copiar-btn" onclick="PG.copiarChave()" id="pg-btn-copiar">
            Copiar
          </button>
        </div>
      </div>

      <div class="pg-pix-instrucoes">
        <div class="pg-instrucao-item">
          <span class="pg-num">1</span>
          <span>Abra o app do seu banco</span>
        </div>
        <div class="pg-instrucao-item">
          <span class="pg-num">2</span>
          <span>Escaneie o QR Code ou cole a chave PIX</span>
        </div>
        <div class="pg-instrucao-item">
          <span class="pg-num">3</span>
          <span>Confirme o valor de <strong>R$ 15,00</strong></span>
        </div>
        <div class="pg-instrucao-item">
          <span class="pg-num">4</span>
          <span>Clique em "Já paguei" após o pagamento</span>
        </div>
      </div>

      <button class="pg-btn-confirmar" onclick="PG.confirmarPagamento('PIX')">
        ✓ Já realizei o pagamento
      </button>
    </div>

    <!-- ══ PASSO 2B: CARTÃO ══ -->
    <div class="pg-modal pg-hidden" id="pg-passo-cartao">
      <button class="pg-fechar" onclick="PG.fechar()">✕</button>
      <button class="pg-voltar" onclick="PG.voltarParaMetodos()">← Voltar</button>

      <div class="pg-header">
        <div class="pg-icon">💳</div>
        <h2>Dados do Cartão</h2>
        <p class="pg-preco-destaque">R$ 15,00</p>
      </div>

      <div class="pg-form-cartao">

        <div class="pg-campo">
          <label>Número do Cartão</label>
          <input type="text" id="pg-numero" placeholder="0000 0000 0000 0000"
            maxlength="19" oninput="PG.mascaraCartao(this)" inputmode="numeric">
        </div>

        <div class="pg-campo">
          <label>Nome no Cartão</label>
          <input type="text" id="pg-nome-cartao" placeholder="NOME COMO NO CARTÃO"
            oninput="this.value = this.value.toUpperCase()">
        </div>

        <div class="pg-campos-row">
          <div class="pg-campo">
            <label>Validade</label>
            <input type="text" id="pg-validade" placeholder="MM/AA"
              maxlength="5" oninput="PG.mascaraValidade(this)" inputmode="numeric">
          </div>
          <div class="pg-campo">
            <label>CVV</label>
            <input type="text" id="pg-cvv" placeholder="123"
              maxlength="4" inputmode="numeric">
          </div>
        </div>

        <div class="pg-campo">
          <label>Parcelas</label>
          <select id="pg-parcelas">
            <option value="1">1x de R$ 15,00 (sem juros)</option>
            <option value="2">2x de R$ 7,50 (sem juros)</option>
            <option value="3">3x de R$ 5,00 (sem juros)</option>
          </select>
        </div>

        <p id="pg-erro-cartao" class="pg-erro-msg"></p>

        <button class="pg-btn-confirmar" onclick="PG.confirmarCartao()">
          Pagar R$ 15,00
        </button>

      </div>
    </div>

    <!-- ══ PASSO 3: PROCESSANDO ══ -->
    <div class="pg-modal pg-modal-sm pg-hidden" id="pg-passo-processando">
      <div class="pg-loader-wrap">
        <div class="pg-loader"></div>
        <p>Processando pagamento...</p>
      </div>
    </div>

  </div>
  `;

  // ── CSS injetado ────────────────────────────────────────
  const css = `
  .pg-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 9000;
    background: rgba(5, 13, 37, 0.85);
    backdrop-filter: blur(8px);
    align-items: center; justify-content: center;
    padding: 1rem;
  }
  .pg-overlay.aberto { display: flex; }

  .pg-modal {
    background: #0d1b3e;
    border: 1px solid rgba(0,245,212,0.2);
    border-radius: 20px;
    padding: 2rem;
    width: 420px; max-width: 95vw;
    max-height: 90vh; overflow-y: auto;
    position: relative;
    box-shadow: 0 0 60px rgba(0,245,212,0.08), 0 30px 80px rgba(0,0,0,0.6);
    animation: pg-entrar .25s cubic-bezier(.22,1,.36,1);
  }
  .pg-modal-sm { width: 280px; padding: 3rem 2rem; text-align: center; }

  @keyframes pg-entrar {
    from { opacity: 0; transform: translateY(24px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  .pg-hidden { display: none !important; }

  .pg-fechar {
    position: absolute; top: 1rem; right: 1rem;
    background: rgba(255,255,255,.06); border: none;
    color: #aaa; width: 32px; height: 32px; border-radius: 50%;
    cursor: pointer; font-size: 1rem; line-height: 1;
    transition: background .2s, color .2s;
  }
  .pg-fechar:hover { background: rgba(255,113,108,.2); color: #ff716c; }

  .pg-voltar {
    background: none; border: none;
    color: #00f5d4; font-size: .82rem; cursor: pointer;
    padding: 0; margin-bottom: 1rem; display: block;
  }
  .pg-voltar:hover { text-decoration: underline; }

  .pg-header { text-align: center; margin-bottom: 1.5rem; }
  .pg-icon { font-size: 2.5rem; margin-bottom: .5rem; }
  .pg-header h2 { color: #fff; font-size: 1.3rem; margin: 0 0 .25rem; }
  .pg-livro-nome { color: #00f5d4; font-size: .9rem; margin: 0; font-weight: 600; }
  .pg-preco-destaque { color: #00f5d4; font-size: 1.4rem; font-weight: 700; margin: 0; }

  .pg-preco-box {
    background: rgba(0,245,212,.06);
    border: 1px solid rgba(0,245,212,.15);
    border-radius: 12px; padding: .9rem 1.2rem;
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.5rem;
  }
  .pg-preco-label { color: #8899bb; font-size: .85rem; }
  .pg-preco-valor { color: #00f5d4; font-size: 1.4rem; font-weight: 700; }

  .pg-subtitulo { color: #8899bb; font-size: .82rem; margin-bottom: .75rem; text-align: center; }

  .pg-metodos { display: flex; flex-direction: column; gap: .75rem; }
  .pg-metodo-btn {
    display: flex; align-items: center; gap: 1rem;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 14px; padding: 1rem 1.2rem;
    cursor: pointer; transition: all .2s; text-align: left;
    color: #fff;
  }
  .pg-metodo-btn:hover {
    border-color: rgba(0,245,212,.4);
    background: rgba(0,245,212,.06);
    transform: translateX(4px);
  }
  .pg-metodo-icon { font-size: 1.6rem; }
  .pg-metodo-btn strong { display: block; font-size: .95rem; color: #fff; }
  .pg-metodo-btn small  { color: #8899bb; font-size: .78rem; }
  .pg-metodo-arrow { margin-left: auto; color: #00f5d4; font-size: 1.4rem; }

  /* PIX */
  .pg-qr-wrap { text-align: center; margin: 1rem 0; }
  .pg-qr-code {
    display: inline-block;
    background: #fff; border-radius: 12px;
    padding: 12px; margin-bottom: .75rem;
    box-shadow: 0 0 30px rgba(0,245,212,.2);
  }
  .pg-qr-instrucao { color: #8899bb; font-size: .82rem; }

  .pg-pix-chave-box {
    background: rgba(0,245,212,.06);
    border: 1px solid rgba(0,245,212,.2);
    border-radius: 12px; padding: .9rem 1rem;
    margin-bottom: 1.2rem;
  }
  .pg-chave-label { color: #8899bb; font-size: .75rem; display: block; margin-bottom: .4rem; }
  .pg-chave-row { display: flex; align-items: center; gap: .75rem; }
  .pg-chave-valor { color: #00f5d4; font-size: .85rem; flex: 1; word-break: break-all; }
  .pg-copiar-btn {
    background: rgba(0,245,212,.15); border: 1px solid rgba(0,245,212,.3);
    color: #00f5d4; border-radius: 8px; padding: .35rem .8rem;
    font-size: .78rem; cursor: pointer; white-space: nowrap;
    transition: background .2s;
  }
  .pg-copiar-btn:hover { background: rgba(0,245,212,.3); }
  .pg-copiar-btn.copiado { background: rgba(0,245,212,.3); color: #fff; }

  .pg-pix-instrucoes { display: flex; flex-direction: column; gap: .6rem; margin-bottom: 1.5rem; }
  .pg-instrucao-item {
    display: flex; align-items: center; gap: .75rem;
    color: #ccd; font-size: .83rem;
  }
  .pg-num {
    background: rgba(0,245,212,.15); color: #00f5d4;
    width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700; flex-shrink: 0;
  }

  /* Cartão */
  .pg-form-cartao { display: flex; flex-direction: column; gap: 1rem; }
  .pg-campo { display: flex; flex-direction: column; gap: .4rem; }
  .pg-campos-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .pg-campo label { color: #8899bb; font-size: .78rem; }
  .pg-campo input, .pg-campo select {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 10px; padding: .7rem 1rem;
    color: #fff; font-size: .9rem;
    transition: border-color .2s;
  }
  .pg-campo input:focus, .pg-campo select:focus {
    outline: none;
    border-color: rgba(0,245,212,.5);
    box-shadow: 0 0 0 3px rgba(0,245,212,.08);
  }
  .pg-campo input::placeholder { color: #445; }
  .pg-campo select { cursor: pointer; }
  .pg-campo select option { background: #0d1b3e; }

  .pg-erro-msg {
    color: #ff716c; font-size: .8rem;
    min-height: 1rem; margin: 0;
  }

  /* Botão confirmar */
  .pg-btn-confirmar {
    width: 100%; padding: .9rem;
    background: linear-gradient(135deg, #00f5d4, #00c9aa);
    border: none; border-radius: 12px;
    color: #00221a; font-weight: 700; font-size: .95rem;
    cursor: pointer; transition: opacity .2s, transform .2s;
    margin-top: .5rem;
  }
  .pg-btn-confirmar:hover { opacity: .9; transform: translateY(-1px); }
  .pg-btn-confirmar:active { transform: translateY(0); }

  /* Loader */
  .pg-loader-wrap { display: flex; flex-direction: column; align-items: center; gap: 1.2rem; }
  .pg-loader-wrap p { color: #8899bb; margin: 0; }
  .pg-loader {
    width: 48px; height: 48px;
    border: 3px solid rgba(0,245,212,.2);
    border-top-color: #00f5d4;
    border-radius: 50%;
    animation: pg-spin .7s linear infinite;
  }
  @keyframes pg-spin { to { transform: rotate(360deg); } }

  /* Toast copiado */
  .pg-toast {
    position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%);
    background: #00f5d4; color: #00221a;
    padding: .6rem 1.4rem; border-radius: 20px;
    font-size: .875rem; font-weight: 700;
    z-index: 9999; opacity: 0;
    transition: opacity .3s;
    pointer-events: none;
  }
  .pg-toast.show { opacity: 1; }
  `;

  // ── Injeta CSS e HTML ───────────────────────────────────
  function init() {
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    const div = document.createElement('div');
    div.innerHTML = modalHTML;
    document.body.appendChild(div.firstElementChild);

    gerarQRCode();
  }

  // ── Gera QR Code simples no canvas ─────────────────────
  function gerarQRCode() {
    const canvas = document.getElementById('pg-qr-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const size = 180;
    const cell = 6;
    const cols = Math.floor(size / cell);

    // Padrão visual de QR (decorativo mas realista)
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, size, size);
    ctx.fillStyle = '#000';

    // Marcadores de canto
    function marcador(x, y) {
      ctx.fillRect(x, y, 7*cell, 7*cell);
      ctx.fillStyle = '#fff';
      ctx.fillRect(x+cell, y+cell, 5*cell, 5*cell);
      ctx.fillStyle = '#000';
      ctx.fillRect(x+2*cell, y+2*cell, 3*cell, 3*cell);
    }
    marcador(0, 0);
    marcador((cols-7)*cell, 0);
    marcador(0, (cols-7)*cell);

    // Dados aleatórios mas com seed fixa (aparência de QR)
    const seed = 42;
    function rand(i) { return ((i * 1664525 + seed) % 65536) / 65536; }
    let idx = 0;
    for (let r = 0; r < cols; r++) {
      for (let c = 0; c < cols; c++) {
        const emMarcador =
          (r < 8 && c < 8) ||
          (r < 8 && c >= cols-8) ||
          (r >= cols-8 && c < 8);
        if (!emMarcador && rand(idx++) > 0.5) {
          ctx.fillRect(c*cell, r*cell, cell, cell);
        }
      }
    }
  }

  // ── API Pública ─────────────────────────────────────────
  window.PG = {

    // Abre o modal de pagamento passando o livro
    abrir(livro) {
      livroAtual = livro;
      document.getElementById('pg-titulo-livro').textContent = livro.titulo || livro.title || livro;
      mostrar('pg-passo1');
      document.getElementById('pg-overlay').classList.add('aberto');
      document.body.style.overflow = 'hidden';
    },

    fechar() {
      document.getElementById('pg-overlay').classList.remove('aberto');
      document.body.style.overflow = '';
      // Limpa campos do cartão
      ['pg-numero','pg-nome-cartao','pg-validade','pg-cvv'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
      });
      document.getElementById('pg-erro-cartao').textContent = '';
    },

    fecharSeForaDoModal(e) {
      if (e.target.id === 'pg-overlay') PG.fechar();
    },

    irParaPix()    { mostrar('pg-passo-pix'); },
    irParaCartao() { mostrar('pg-passo-cartao'); },
    voltarParaMetodos() { mostrar('pg-passo1'); },

    copiarChave() {
      navigator.clipboard.writeText(CHAVE_PIX).then(() => {
        const btn = document.getElementById('pg-btn-copiar');
        btn.textContent = '✓ Copiado!';
        btn.classList.add('copiado');
        mostrarToast('Chave PIX copiada!');
        setTimeout(() => {
          btn.textContent = 'Copiar';
          btn.classList.remove('copiado');
        }, 2500);
      });
    },

    confirmarPagamento(metodo) {
      mostrar('pg-passo-processando');
      // Simula processamento de 2s
      setTimeout(() => {
        PG.fechar();
        // Abre o ticket (função já existente nas páginas)
        if (typeof openTicket === 'function') {
          openTicket(livroAtual, metodo);
        } else if (typeof emprestarLivro === 'function') {
          emprestarLivro(livroAtual.id || livroAtual, metodo);
        }
      }, 2000);
    },

    confirmarCartao() {
      const numero = document.getElementById('pg-numero').value.replace(/\s/g,'');
      const nome   = document.getElementById('pg-nome-cartao').value.trim();
      const val    = document.getElementById('pg-validade').value;
      const cvv    = document.getElementById('pg-cvv').value;
      const erro   = document.getElementById('pg-erro-cartao');

      if (numero.length < 16) { erro.textContent = 'Número do cartão inválido.'; return; }
      if (nome.length < 3)    { erro.textContent = 'Informe o nome como no cartão.'; return; }
      if (val.length < 5)     { erro.textContent = 'Data de validade inválida.'; return; }
      if (cvv.length < 3)     { erro.textContent = 'CVV inválido.'; return; }
      erro.textContent = '';

      PG.confirmarPagamento('Cartão');
    },

    mascaraCartao(input) {
      let v = input.value.replace(/\D/g,'').substring(0,16);
      input.value = v.replace(/(.{4})/g,'$1 ').trim();
    },

    mascaraValidade(input) {
      let v = input.value.replace(/\D/g,'').substring(0,4);
      if (v.length >= 2) v = v.slice(0,2) + '/' + v.slice(2);
      input.value = v;
    }
  };

  // ── Helpers ─────────────────────────────────────────────
  function mostrar(id) {
    const ids = ['pg-passo1','pg-passo-pix','pg-passo-cartao','pg-passo-processando'];
    ids.forEach(i => {
      const el = document.getElementById(i);
      if (el) el.classList.toggle('pg-hidden', i !== id);
    });
  }

  function mostrarToast(msg) {
    let t = document.querySelector('.pg-toast');
    if (!t) {
      t = document.createElement('div');
      t.className = 'pg-toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
  }

  // ── Inicializa ao carregar ──────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
