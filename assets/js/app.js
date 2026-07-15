/* Password visibility toggle */
document.querySelectorAll('.toggle-pw').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.querySelector(btn.dataset.target);
    if (!input) return;
    const isText = input.type === 'text';
    input.type  = isText ? 'password' : 'text';
    btn.textContent = isText ? '👁' : '🙈';
    btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
  });
});

/* Password strength meter */
const pwInput     = document.getElementById('password');
const strengthWrap = document.querySelector('.pw-strength');
const strengthFill = document.querySelector('.pw-strength-fill');
const strengthLbl  = document.querySelector('.pw-strength-label');

if (pwInput && strengthWrap) {
  pwInput.addEventListener('input', () => {
    const val = pwInput.value;
    if (!val) {
      strengthWrap.classList.remove('visible');
      return;
    }
    strengthWrap.classList.add('visible');

    let score = 0;
    if (val.length >= 8)                      score++;
    if (val.length >= 12)                     score++;
    if (/[A-Z]/.test(val))                    score++;
    if (/[0-9]/.test(val))                    score++;
    if (/[^A-Za-z0-9]/.test(val))            score++;

    const levels = [
      { pct: '20%',  color: '#ef4444', label: 'Too weak'  },
      { pct: '40%',  color: '#f97316', label: 'Weak'      },
      { pct: '60%',  color: '#eab308', label: 'Fair'      },
      { pct: '80%',  color: '#84cc16', label: 'Good'      },
      { pct: '100%', color: '#22c55e', label: 'Strong 💪' },
    ];

    const lvl = levels[Math.min(score - 1, 4)] || levels[0];
    strengthFill.style.width      = lvl.pct;
    strengthFill.style.background = lvl.color;
    strengthLbl.textContent       = lvl.label;
    strengthLbl.style.color       = lvl.color;
  });
}

/* Confirm password live match indicator */
const confirmInput = document.getElementById('confirm_password');
if (pwInput && confirmInput) {
  const checkMatch = () => {
    if (!confirmInput.value) return;
    const match = pwInput.value === confirmInput.value;
    confirmInput.classList.toggle('error', !match);
    let hint = confirmInput.parentElement.nextElementSibling;
    if (!hint || !hint.classList.contains('form-error')) {
      hint = null;
    }
    if (!match) {
      if (!hint) {
        const el = document.createElement('p');
        el.className = 'form-error';
        el.id = 'pw-match-hint';
        el.textContent = 'Passwords do not match';
        confirmInput.parentElement.after(el);
      }
    } else {
      const el = document.getElementById('pw-match-hint');
      if (el) el.remove();
      confirmInput.classList.remove('error');
    }
  };
  confirmInput.addEventListener('input', checkMatch);
  pwInput.addEventListener('input', checkMatch);
}

/* Submit button → spinner */
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', function () {
    const btn = this.querySelector('button[type="submit"]');
    if (!btn) return;
    // Small delay so the browser runs its own validation first
    setTimeout(() => {
      if (form.checkValidity && !form.checkValidity()) return;
      btn.disabled = true;
      const orig = btn.innerHTML;
      btn.dataset.orig = orig;
      btn.innerHTML = '<span class="spinner"></span> Please wait…';
    }, 10);
  });
});

/* Auto-dismiss alerts */
document.querySelectorAll('.alert').forEach(alert => {
  if (alert.classList.contains('alert-success')) {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity    = '0';
      setTimeout(() => alert.remove(), 500);
    }, 4000);
  }
});

/* Input focus — clear error state */
document.querySelectorAll('.form-input').forEach(input => {
  input.addEventListener('input', () => {
    input.classList.remove('error');
    const errEl = input.closest('.form-group')?.querySelector('.form-error.server-error');
    if (errEl) errEl.remove();
  });
});

/* Session timeout warning (dashboard) */
const SESSION_MS  = 30 * 60 * 1000;   // 30 min
const WARNING_MS  = 5  * 60 * 1000;   // warn at 5 min remaining

if (document.body.classList.contains('is-dashboard')) {
  let lastActive = Date.now();

  const resetTimer = () => { lastActive = Date.now(); };
  ['click','keydown','mousemove','touchstart'].forEach(e =>
    document.addEventListener(e, resetTimer, { passive: true })
  );

  const warningEl = document.getElementById('session-warning');

  setInterval(() => {
    const idle = Date.now() - lastActive;
    if (idle >= SESSION_MS) {
      window.location.href = 'logout.php?reason=timeout';
    } else if (idle >= SESSION_MS - WARNING_MS && warningEl) {
      warningEl.style.display = 'flex';
    } else if (warningEl) {
      warningEl.style.display = 'none';
    }
  }, 30000); // check every 30 s
}

/* Dismiss session warning */
const dismissBtn = document.getElementById('dismiss-warning');
const warningBanner = document.getElementById('session-warning');
if (dismissBtn && warningBanner) {
  dismissBtn.addEventListener('click', () => {
    warningBanner.style.display = 'none';
    // Send a lightweight ping to reset server-side session timer
    fetch('ping.php', { method: 'POST', credentials: 'same-origin' }).catch(() => {});
  });
}

/* Animate stat cards on load (dashboard) */
if (document.body.classList.contains('is-dashboard')) {
  const cards = document.querySelectorAll('.stat-card, .info-card');
  cards.forEach((card, i) => {
    card.style.opacity   = '0';
    card.style.transform = 'translateY(12px)';
    card.style.transition = `opacity 0.35s ease ${i * 0.06}s, transform 0.35s ease ${i * 0.06}s`;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        card.style.opacity   = '1';
        card.style.transform = 'translateY(0)';
      });
    });
  });
}
