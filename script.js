const navbar = document.querySelector('#navbar');
const menuButton = document.querySelector('#menu-button');
const mobileMenu = document.querySelector('#mobile-menu');
const backTop = document.querySelector('#back-top');
const navLinks = [...document.querySelectorAll('.nav-link')];

function syncHeader() {
  const scrolled = window.scrollY > 24;
  navbar.classList.toggle('scrolled', scrolled);
  backTop.classList.toggle('translate-y-20', !scrolled || window.scrollY < 500);
  backTop.classList.toggle('opacity-0', !scrolled || window.scrollY < 500);
}

menuButton.addEventListener('click', () => {
  const open = mobileMenu.classList.toggle('open');
  menuButton.setAttribute('aria-expanded', String(open));
  menuButton.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  mobileMenu.setAttribute('aria-hidden', String(!open));
});

mobileMenu.addEventListener('click', ({ target }) => {
  if (target.closest('a')) {
    mobileMenu.classList.remove('open');
    menuButton.setAttribute('aria-expanded', 'false');
    mobileMenu.setAttribute('aria-hidden', 'true');
  }
});

const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal:not(.is-visible)').forEach((item) => revealObserver.observe(item));

const sectionObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) navLinks.forEach((link) => link.classList.toggle('active', link.hash === `#${entry.target.id}`));
  });
}, { rootMargin: '-38% 0px -55%', threshold: 0 });
document.querySelectorAll('main section[id]').forEach((section) => sectionObserver.observe(section));

backTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
window.addEventListener('scroll', syncHeader, { passive: true });
syncHeader();

const form = document.querySelector('#quote-form');
const status = document.querySelector('#form-status');
const submitButton = form.querySelector('button[type="submit"]');
const csrfField = form.querySelector('#csrf-token');

async function loadFormToken() {
  try {
    const response = await fetch('contact.php?action=token', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-RSI-Form': '1' }
    });
    if (!response.ok) throw new Error('Token no disponible');
    const data = await response.json();
    csrfField.value = data.csrf_token || '';
    return Boolean(csrfField.value);
  } catch {
    return false;
  }
}

loadFormToken();

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const fields = [...form.querySelectorAll('[required]')];
  fields.forEach((field) => field.classList.toggle('invalid', !field.checkValidity()));
  const invalid = fields.find((field) => !field.checkValidity());
  if (invalid) {
    status.textContent = 'Revise los campos requeridos antes de continuar.';
    status.className = 'mt-4 text-sm font-semibold text-rose-600';
    invalid.focus();
    return;
  }
  if (!csrfField.value && !(await loadFormToken())) {
    status.textContent = 'No fue posible iniciar el envío seguro. Recargue la página e intente nuevamente.';
    status.className = 'mt-4 text-sm font-semibold text-rose-600';
    return;
  }

  submitButton.disabled = true;
  submitButton.setAttribute('aria-busy', 'true');
  status.textContent = 'Enviando solicitud…';
  status.className = 'mt-4 text-sm font-semibold text-navy-700';

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-RSI-Form': '1' }
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo enviar la solicitud.');
    status.textContent = result.message;
    status.className = 'mt-4 text-sm font-semibold text-emerald-700';
    form.reset();
    csrfField.value = '';
    await loadFormToken();
  } catch (error) {
    status.textContent = error.message || 'Ocurrió un error. También puede escribirnos a ventas@rsirepuestos.com.';
    status.className = 'mt-4 text-sm font-semibold text-rose-600';
  } finally {
    submitButton.disabled = false;
    submitButton.removeAttribute('aria-busy');
  }
});
form.addEventListener('input', ({ target }) => target.classList.remove('invalid'));

const filterButtons = [...document.querySelectorAll('[data-filter]')];
const knowledgeCards = [...document.querySelectorAll('.knowledge-card')];
filterButtons.forEach((button) => button.addEventListener('click', () => {
  filterButtons.forEach((item) => item.classList.toggle('active', item === button));
  knowledgeCards.forEach((card) => card.classList.toggle('hidden-card', button.dataset.filter !== 'all' && card.dataset.type !== button.dataset.filter));
}));
document.querySelector('#year').textContent = new Date().getFullYear();
