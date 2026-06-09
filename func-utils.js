/**
 * func-utils.js — Utilitários exclusivos do portal do funcionário
 * Usado em: EmprestimoFuncionario, FuncionarioLivro, FuncionarioUsuario,
 *           GestaoFuncionario, PainelFuncionario
 */

// ── Proteção de rota ─────────────────────────────────
(function() {
  if (localStorage.getItem('func_logado') !== 'true') {
    window.location.href = 'LoginFuncionario.html';
  }
})();

// ── Logout ───────────────────────────────────────────
async function logout() {
  try {
    await fetch(API_BASE + '/logout.php', { method: 'POST' });
  } catch {}
  ['func_logado','func_id','func_nome','func_email','func_cargo','func_matricula']
    .forEach(function(k) { localStorage.removeItem(k); });
  window.location.href = 'LoginFuncionario.html';
}

// ── Avatar com iniciais na navbar ────────────────────
document.addEventListener('DOMContentLoaded', function() {
  const nome   = localStorage.getItem('func_nome') || '';
  const elNome = document.getElementById('func-nome-nav');
  const elAv   = document.getElementById('user-avatar-nav');
  if (elNome) elNome.textContent = nome;
  if (elAv && nome) {
    const partes   = nome.trim().split(' ').filter(Boolean);
    const iniciais = (partes[0] ? partes[0][0] : '') + (partes[1] ? partes[1][0] : '');
    elAv.innerHTML = '<span style="font-size:.8rem;font-weight:700;color:#09122c;">' + iniciais.toUpperCase() + '</span>';
    elAv.style.background     = '#73ffe3';
    elAv.style.display        = 'flex';
    elAv.style.alignItems     = 'center';
    elAv.style.justifyContent = 'center';
  }
});
