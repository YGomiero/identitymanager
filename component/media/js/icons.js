/** @license GNU General Public License version 2 or later; see LICENSE.txt */
// No remote favicon service: load only the administrator's configured image.
document.querySelectorAll('img[data-identitymanager-fallback]').forEach((image) => {
    const fallback = () => {
        const source = image.dataset.identitymanagerFallback;
        delete image.dataset.identitymanagerFallback;
        if (source) image.src = source;
    };
    image.addEventListener('error', fallback, { once: true });
    if (image.complete && image.naturalWidth === 0) fallback();
});
