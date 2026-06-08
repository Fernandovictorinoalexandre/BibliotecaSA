/**
 * utils.js — Utilitários compartilhados entre todas as páginas
 * Estação Literária
 */

// ── Constante de base da API ─────────────────────────
const API_BASE = 'http://localhost:9999/Estacao-refatorado/php';

// ── Sanitização XSS ──────────────────────────────────
const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ── Formatação de data DD/MM/AAAA ────────────────────
function fmt(iso) {
  if (!iso) return '—';
  const p = iso.split('T')[0].split('-');
  return p[2] + '/' + p[1] + '/' + p[0];
}

// ── Formatação de moeda R$ ───────────────────────────
function fmtMoney(v) {
  return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',');
}

// ── Toast (páginas de funcionário: #toast simples) ───
function toast(msg, tipo) {
  tipo = tipo || 'sucesso';
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = msg;
  el.className = 'toast ' + tipo + ' show';
  setTimeout(function() { el.classList.remove('show'); }, 3500);
}

// ── Toast flutuante (páginas de usuário: #toast-wrap) ─
function showToast(msg, type) {
  type = type || 'ok';
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) { toast(msg, type === 'error' || type === 'err' ? 'erro' : 'sucesso'); return; }
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(function() {
    t.classList.add('out');
    setTimeout(function() { t.remove(); }, 350);
  }, 3500);
}

// ── Modal genérico (class="modal-overlay") ───────────
function abrirModal(id)  { document.getElementById(id).classList.add('aberto'); }
function fecharModal(id) { document.getElementById(id).classList.remove('aberto'); }

function inicializarModais() {
  document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) { if (e.target === m) fecharModal(m.id); });
  });
}
