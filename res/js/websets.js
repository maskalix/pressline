/**
 * Hydrate web-settings inputs with values returned from the database, then
 * wire the save button to PUT each input back through setter-proxy.
 */
function renderWebSettings(settings) {
    const container = document.getElementById('web-settings');
    if (!container) return;

    settings.forEach(setting => {
        const input = container.querySelector(`[data-key="${setting.key}"]`);
        if (!input) return;
        if (input.type === 'checkbox') {
            input.checked = setting.value === '1' || setting.value === 1 || setting.value === true;
        } else {
            input.value = setting.value ?? '';
        }
    });

    const saveBtn = document.getElementById('webset-save');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', () => {
        const inputs = container.querySelectorAll('[data-key]');
        inputs.forEach(input => {
            const key = input.dataset.key;
            const value = input.type === 'checkbox'
                ? (input.checked ? '1' : '0')
                : input.value;

            const data = new FormData();
            data.append('action', 'edit');
            data.append('type', 'webset');
            data.append('where', 'key');
            data.append('id', key);
            data.append('value', value);
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (tokenMeta) data.append('csrf_token', tokenMeta.content);

            fetch('setter-proxy.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: data,
            }).catch(err => console.error('webset save failed:', err));
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof webSettings !== 'undefined') {
        renderWebSettings(webSettings);
    }
});
