/**
 * login-utils.js — Lógica de bloqueio por tentativas
 * Usado em: LoginUsuario, LoginFuncionario
 */

let _countdownTimer = null;

function limparErroServidor() {
  const erroEl = document.getElementById('erro-servidor');
  const avisoEl = document.getElementById('aviso-tentativas');
  if (erroEl)  erroEl.style.display  = 'none';
  if (avisoEl) avisoEl.style.display = 'none';
  if (_countdownTimer) {
    clearInterval(_countdownTimer);
    _countdownTimer = null;
    const btn = document.getElementById('btnEntrar');
    if (btn) {
      btn.disabled    = false;
      btn.textContent = 'Entrar';
      delete btn.dataset.bloqueado;
    }
  }
}

function iniciarCountdown(minutos) {
  const btn = document.getElementById('btnEntrar');
  const el  = document.getElementById('erro-servidor');
  btn.dataset.bloqueado = 'true';
  let segundos = minutos * 60;
  if (_countdownTimer) clearInterval(_countdownTimer);

  function atualizar() {
    const m = Math.floor(segundos / 60);
    const s = String(segundos % 60).padStart(2, '0');
    btn.disabled    = true;
    btn.textContent = 'Bloqueado — aguarde ' + m + ':' + s;
    if (segundos <= 0) {
      clearInterval(_countdownTimer);
      btn.disabled    = false;
      btn.textContent = 'Entrar';
      delete btn.dataset.bloqueado;
      if (el) el.style.display = 'none';
    }
    segundos--;
  }
  atualizar();
  _countdownTimer = setInterval(atualizar, 1000);
}
