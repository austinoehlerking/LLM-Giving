(() => {
  const button = document.querySelector('.giving-motion');
  if (!button) return;
  const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
  button.hidden = false;
  const sync = () => {
    const paused = document.body.classList.contains('giving-paused');
    button.disabled = preference.matches;
    button.textContent = preference.matches ? 'Reduced motion enabled' : paused ? 'Play animation' : 'Pause animation';
    button.setAttribute('aria-pressed', String(paused || preference.matches));
  };
  button.addEventListener('click', () => { document.body.classList.toggle('giving-paused'); sync(); });
  preference.addEventListener('change', sync);
  sync();
})();
