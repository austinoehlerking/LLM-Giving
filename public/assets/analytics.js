(() => {
  'use strict';
  const script = document.querySelector('script[data-google-analytics]');
  const id = script?.dataset.googleAnalytics;
  if (!/^G-[A-Z0-9]{6,20}$/.test(id || '')) return;

  const blocked = navigator.globalPrivacyControl === true || navigator.doNotTrack === '1';
  let previouslyDeclined = false;
  try { previouslyDeclined = localStorage.getItem('llmgiving.analytics.v1') === 'denied'; } catch {}
  if (blocked || previouslyDeclined) {
    window['ga-disable-' + id] = true;
    for (const cookie of document.cookie.split(';')) {
      const name = cookie.split('=')[0].trim();
      if (name !== '_ga' && !name.startsWith('_ga_')) continue;
      const domains = [null];
      const labels = location.hostname.split('.');
      while (labels.length > 1) { domains.push(labels.join('.'), '.' + labels.join('.')); labels.shift(); }
      for (const domain of domains) document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax' + (domain ? '; Domain=' + domain : '');
    }
    return;
  }

  window['ga-disable-' + id] = false;
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag('consent', 'default', { analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
  window.gtag('js', new Date());
  // Set before the remote library loads; never send search terms, fragments or referrers.
  window.gtag('set', { page_location: location.origin + location.pathname, page_referrer: '', allow_google_signals: false, allow_ad_personalization_signals: false, ads_data_redaction: true });
  window.gtag('config', id, { page_location: location.origin + location.pathname, page_referrer: '', page_title: document.title, allow_google_signals: false, allow_ad_personalization_signals: false });
  const tag = document.createElement('script');
  tag.async = true;
  tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
  document.head.append(tag);
})();