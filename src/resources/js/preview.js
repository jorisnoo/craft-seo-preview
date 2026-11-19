(() => {
    const root = document.querySelector('.seo-preview');
    if (!root) return;

    const meta = (selector) => document.head.querySelector(`meta[${selector}]`)?.content ?? '';
    const googleTitle = document.title;
    const googleDescription = meta('name="description"');
    const facebookTitle = meta('property="og:title"') || googleTitle;
    const facebookDescription = meta('property="og:description"') || googleDescription;
    const hostName = root.dataset.host || window.location.hostname;
    const fields = {
        hostName, googleTitle, googleDescription, facebookTitle, facebookDescription,
        siteName: meta('property="og:site_name"') || hostName,
        xTitle: meta('name="twitter:title"') || facebookTitle,
        xDescription: meta('name="twitter:description"') || facebookDescription,
    };

    for (const el of root.querySelectorAll('[data-field]')) {
        el.textContent = fields[el.dataset.field];
    }
    for (const counter of root.querySelectorAll('[data-count]')) {
        const length = fields[counter.dataset.count].length;
        const limit = Number(counter.dataset.limit);
        const status = !length ? 'empty' : length > limit ? 'over' : length > limit * 0.9 ? 'warning' : 'ok';
        counter.classList.add(`is-${status}`);
        counter.querySelector('.char-count__value').textContent = `${length} / ${limit}`;
        counter.querySelector('.char-count__bar-fill').style.width = `${Math.min(100, 100 * length / limit)}%`;
    }

    const facebook = meta('property="og:image"');
    const xImage = meta('name="twitter:image"') || facebook;
    const xSize = meta('name="twitter:card"') === 'summary_large_image' ? 'large' : 'small';
    for (const [slot, url] of [['facebook', facebook], [xSize, xImage]]) {
        if (!url) continue;
        const container = root.querySelector(`[data-image="${slot}"]`);
        container.querySelector('img').src = url;
        container.hidden = false;
    }

    const favicon = root.querySelector('.google-card__favicon');
    const hideFavicon = () => { favicon.hidden = true; };
    favicon.addEventListener('error', hideFavicon);
    if (favicon.complete && !favicon.naturalWidth) hideFavicon();
})();
