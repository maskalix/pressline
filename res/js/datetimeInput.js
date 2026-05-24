document.addEventListener('DOMContentLoaded', () => {
    const custom = document.getElementById('custom_input');
    const native = document.getElementById('native_input');

    function isLeapYear(y) {
        return (y % 4 === 0 && y % 100 !== 0) || (y % 400 === 0);
    }

    function getDaysInMonth(m, y) {
        return [31, isLeapYear(y) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1];
    }

    function validateAndFormat(raw) {
        const cleaned = raw.replace(/[^0-9.:/\- ]/g, '');
        const regex = /^(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})(?:\s+(\d{1,2}):(\d{1,2}))?$/;
        const match = cleaned.match(regex);
        if (!match) return { valid: false };

        const parts = match.slice(1).map(x => x ? parseInt(x, 10) : 0);
        const [d, m, y, h = 0, min = 0] = parts;

        if (m < 1 || m > 12) return { valid: false };
        const maxDay = getDaysInMonth(m, y);
        if (d < 1 || d > maxDay) return { valid: false };
        if (h < 0 || h > 23) return { valid: false };
        if (min < 0 || min > 59) return { valid: false };

        return {
            valid: true,
            iso: `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}T${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`,
            formatted: `${String(d).padStart(2, '0')}.${String(m).padStart(2, '0')}.${y} ${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`
        };
    }

    // Manual input typed by user
    custom.addEventListener('input', () => {
        const result = validateAndFormat(custom.value);
        if (result.valid) {
            native.value = result.iso;
            custom.classList.remove('invalid');
        } else {
            custom.classList.add('invalid');
        }
    });

    // If user picks with native picker
    native.addEventListener('input', () => {
        const dt = new Date(native.value);
        if (!isNaN(dt)) {
            const formatted = `${String(dt.getDate()).padStart(2, '0')}.${String(dt.getMonth() + 1).padStart(2, '0')}.${dt.getFullYear()} ${String(dt.getHours()).padStart(2, '0')}:${String(dt.getMinutes()).padStart(2, '0')}`;
            custom.value = formatted;
            custom.classList.remove('invalid');
        }
    });

    // 🟢 Autorun on load if native input has a value
    if (native.value) {
        const dt = new Date(native.value);
        if (!isNaN(dt)) {
            const formatted = `${String(dt.getDate()).padStart(2, '0')}.${String(dt.getMonth() + 1).padStart(2, '0')}.${dt.getFullYear()} ${String(dt.getHours()).padStart(2, '0')}:${String(dt.getMinutes()).padStart(2, '0')}`;
            custom.value = formatted;
        }
    }
});