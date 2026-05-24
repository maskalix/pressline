/***
 * Theme management - PressLine Admin
 */

localStorage.setItem('mode', mode);
localStorage.setItem('color', color);

const root = document.querySelector(':root');

// Apply theme class (light, dark, amoled) and accent color class
const Mode = localStorage.getItem('mode');
const Color = localStorage.getItem('color');

// Map legacy 'wallpaper' mode to 'light'
const ResolvedMode = (Mode === 'wallpaper') ? 'light' : Mode;

document.documentElement.classList.add(ResolvedMode, Color);

/***
 * Sidebar nav: highlight active menu link
 */
document.addEventListener("DOMContentLoaded", function () {
    const menu = document.querySelector('aside');
    if (!menu) return;
    const links = menu.getElementsByTagName('a');
    const currentUrl = window.location.href;

    for (const link of links) {
        const href = link.href;
        if (href === currentUrl && href !== '#') {
            link.classList.add('active');
            const parent = link.parentElement.parentElement;
            if (parent.getAttribute('class') == 'dropdown-menu') {
                parent.classList.add('open');
            }
        }
    }
});

/***
 * Sidebar toggle: collapsible on desktop, drawer on mobile.
 *
 * Mobile (< 1024px): sidebar is full-screen overlay, slides in/out
 * Desktop (>= 1024px): sidebar is collapsible (hidden by default if user toggled)
 */
document.addEventListener("DOMContentLoaded", function () {
    const collapseButton = document.getElementById("collapseButton");
    const collapsibleDiv = document.getElementById("collapsibleDiv");
    const menu = document.querySelector('aside');
    const body = document.body;

    if (!collapseButton || !collapsibleDiv || !menu) return;

    collapsibleDiv.classList.remove("hide");

    // Restore desktop collapse state from localStorage
    const isDesktop = () => window.innerWidth >= 1024;
    const COLLAPSED_KEY = 'pl-sidebar-collapsed';

    function applyDesktopState() {
        if (isDesktop()) {
            const collapsed = localStorage.getItem(COLLAPSED_KEY) === '1';
            body.classList.toggle('sidebar-collapsed', collapsed);
            menu.classList.remove('opened');
            body.classList.remove('drawer-open');
        } else {
            body.classList.remove('sidebar-collapsed');
        }
    }
    applyDesktopState();
    window.addEventListener('resize', applyDesktopState);

    function syncDrawerState() {
        const open = menu.classList.contains("opened");
        body.classList.toggle("drawer-open", open && !isDesktop());
    }

    collapseButton.addEventListener("click", (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (isDesktop()) {
            const collapsed = !body.classList.contains('sidebar-collapsed');
            body.classList.toggle('sidebar-collapsed', collapsed);
            localStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');
        } else {
            menu.classList.toggle("opened");
            syncDrawerState();
        }
    });

    // Click outside drawer closes it (mobile only)
    document.addEventListener('click', function (event) {
        if (isDesktop()) return;
        if (!menu.classList.contains("opened")) return;
        if (menu.contains(event.target) || collapseButton.contains(event.target)) return;
        menu.classList.remove("opened");
        syncDrawerState();
    });

    // Clicking a menu link on mobile closes the drawer
    menu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            if (!isDesktop()) {
                menu.classList.remove("opened");
                syncDrawerState();
            }
        });
    });
});

/***
 * Query alerts
 */
function reloadWithParams(status, message) {
    const url = new URL(window.location.href);
    url.searchParams.set('state', status);
    url.searchParams.set('message', message);
    window.location.href = url.toString();
}

/***
 * Alert / Toast
 */
function showAlert(type, content, duration = 3000) {
    var alertBox = document.createElement('div');
    alertBox.classList.add('alert');

    var typeMap = {
        positive: '--success',
        negative: '--error',
        neutral: '--warn'
    };

    var iconMap = {
        positive: 'ic:round-check-circle',
        negative: 'ic:round-block',
        neutral: 'ic:round-error'
    };

    alertBox.style.background = 'var(' + (typeMap[type] || typeMap['neutral']) + ')';
    alertBox.style.color = '#fff';

    var icon = document.createElement('span');
    icon.classList.add('iconify-inline');
    icon.setAttribute('data-icon', iconMap[type] || iconMap['neutral']);

    alertBox.appendChild(icon);
    alertBox.appendChild(document.createTextNode(' ' + content));

    document.body.appendChild(alertBox);

    setTimeout(function() {
        alertBox.classList.add('hide');
        setTimeout(function() {
            if (alertBox.parentNode) document.body.removeChild(alertBox);
        }, 600);
    }, duration);
}

document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    const state = urlParams.get('state');
    const message = urlParams.get('message');

    if (state && message) {
        showAlert(state, message);
    }
});
