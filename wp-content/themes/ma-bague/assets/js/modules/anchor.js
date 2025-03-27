/*------------------------------------*/
// INIT SMOOTH SCROLL ANCHOR URL
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_anchor() {
    document.addEventListener('click', e => {
        const anchorLink = e.target.closest('a[href*="#"]');
        if (anchorLink) {
            e.preventDefault(); // Empêche le comportement par défaut
            const href = anchorLink.getAttribute('href');
            const fragment = href.split('#')[1]?.split('?')[0]; // Extrait le fragment avant les paramètres
            const targetElement = document.getElementById(fragment); // Cible l'élément par ID
            if (targetElement) { window.myLoco?.scrollTo
                ? window.myLoco.scrollTo(targetElement)
                : targetElement.scrollIntoView({ behavior: 'smooth' });
            } // Scrolle vers l'élément, avec fall back si pas locomotive.js
        }
    });
}
