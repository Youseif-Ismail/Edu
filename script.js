document.querySelectorAll('a[href^="#"]').forEach((link) => {
  link.addEventListener('click', (event) => {
    const target = document.querySelector(link.getAttribute('href'));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});

document.querySelector('.watch').addEventListener('click', () => {
  document.querySelector('#how').scrollIntoView({ behavior: 'smooth', block: 'start' });
});
