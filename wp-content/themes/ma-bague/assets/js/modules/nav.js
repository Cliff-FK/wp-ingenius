/*------------------------------------*/
// NAV FIXED ON SCROLL
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_headerNav() {
    if (!vars.header) return; // Si le header n’existe pas, on sort
    if (typeof wbd_headerNav.prev === "undefined") {
        wbd_headerNav.prev = window.scrollY;
        wbd_headerNav.disableScrollHandling = false;
    }
    const cur = window.scrollY; // Lecture du scroll actuel
    if (!wbd_headerNav.disableScrollHandling) {
        vars.header.classList.toggle("o-fix", cur > 5);
        vars.header.classList.toggle("o-fix-down", cur > wbd_headerNav.prev && cur > 5);
    }
    wbd_headerNav.prev = cur;
}

/*------------------------------------*/
// INIT BURGER MENU
/*------------------------------------*/
export function wbd_burgerNav(cssClass = 'brg-open') {
    if(!vars.burger || !vars.header) return;
    // 1. Clique sur le burger
    vars.burger.addEventListener('click', () => {
        vars.burger.classList.toggle(cssClass);
        vars.header.classList.toggle(cssClass);
    });
    // 2. Clique sur document (hors header)
    document.addEventListener('click', (e) => {
        if (!vars.header.contains(e.target) && vars.header !== e.target) {
        vars.burger.classList.remove(cssClass);
        vars.header.classList.remove(cssClass);
        }
    });
}
  
/*------------------------------------*/
// INIT TOGGLE MOBIL NAV
/*------------------------------------*/
export function wbd_mobNav() {
    const allNav = document.querySelectorAll('.g-mob-nav, .sub-menu');
    if (vars.bodyDOM && allNav.length && vars.burger) {
        vars.burger.addEventListener('click', () => {
            const isActive = vars.bodyDOM.classList.toggle('sidebar-open');
            allNav.forEach(e => !isActive && e.classList.remove('brg-open'));
        });
        document.addEventListener('click', e => {
            if (!e.target.closest('header') || e.target.matches('a[href^="#"]')) {
                vars.bodyDOM.classList.remove('sidebar-open');
                vars.burger.classList.remove('brg-open');
            }
        });
    }
}
