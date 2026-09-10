document.querySelectorAll('a[href^="#"]').forEach((link) => {
  link.addEventListener('click', (event) => {
    const target = document.querySelector(link.getAttribute('href'));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});

const revealItems = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.16 });

revealItems.forEach((item) => revealObserver.observe(item));

const modal = document.querySelector('#auth-modal');
const authForm = document.querySelector('#auth-form');
const authMessage = document.querySelector('.modal-message');
let selectedCourse = null;

function openAuth(course = null) {
  selectedCourse = course;
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open');
  authForm.elements.email.focus();
}

function closeAuth() {
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
  authMessage.textContent = '';
}

async function request(url, options = {}) {
  const response = await fetch(url, { headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.message || 'حدث خطأ، حاول مرة أخرى.');
  return payload;
}

async function enroll(course) {
  const result = await request('/api/enrollments', { method: 'POST', body: JSON.stringify({ course }) });
  authMessage.textContent = result.message;
  authMessage.classList.add('success');
}

document.querySelectorAll('.auth-trigger').forEach((button) => button.addEventListener('click', () => openAuth()));
document.querySelectorAll('.enroll-trigger').forEach((button) => button.addEventListener('click', async () => {
  selectedCourse = button.dataset.course;
  try { await enroll(selectedCourse); openAuth(); }
  catch (error) { if (error.message.includes('سجّل دخولك')) openAuth(selectedCourse); else alert(error.message); }
}));
document.querySelector('.modal-close').addEventListener('click', closeAuth);
modal.addEventListener('click', (event) => { if (event.target === modal) closeAuth(); });

authForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(authForm));
  const submit = authForm.querySelector('button[type="submit"]');
  submit.disabled = true;
  authMessage.textContent = 'جاري تجهيز حسابك...';
  try {
    await request(data.name ? '/api/auth/register' : '/api/auth/login', { method: 'POST', body: JSON.stringify(data) });
    if (selectedCourse) await enroll(selectedCourse);
    authMessage.textContent = selectedCourse ? 'تم الحجز بنجاح، أهلاً بك في The Legend!' : 'تم تسجيل الدخول بنجاح، أهلاً بك في The Legend!';
    authMessage.classList.add('success');
    authForm.reset();
  } catch (error) {
    authMessage.textContent = error.message;
    authMessage.classList.remove('success');
  } finally { submit.disabled = false; }
});
