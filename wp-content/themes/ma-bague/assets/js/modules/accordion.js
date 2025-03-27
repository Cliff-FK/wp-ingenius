/*------------------------------------*/
// INIT ACCORDIONS COMPONENTS
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_accordion() {
    let aAttr= 'open', aCtn= '[wbd-acc-ctn]', aMore= "[class*='more']",
        aItm= '[wbd-acc-item]', aBtn= '[wbd-acc-cta]', aPnl= '[wbd-acc-panel]';
    document.querySelectorAll(aCtn).forEach((ctn) => {
        function close(){ ctn.querySelectorAll(aItm).forEach(el => el.removeAttribute(aAttr),''); }
        ctn.addEventListener('click', (e) => { // Délégation d'event, on écoute uniquement le parent des cibles
            let btn = e.target.closest(aBtn), more = e.target.closest(aMore);
            let item = btn ? btn.closest(aItm) : null; // Trouve l'item parent du bouton
            let panel = item ? item.querySelector(aPnl) : null; // Sélectionne le panel dans le même item
            if(more && ctn.closest(aPnl)){ e.stopPropagation(); ctn.closest(aPnl).style.maxHeight = '9999px'; } // si bouton load-more
            if(btn && btn.closest(aCtn) === ctn) { // On vérifie que le bouton appartient bien à l'itération forEach en cours
                let allPanels = [...ctn.querySelectorAll(aPnl)].filter(el => el.closest(aCtn) === ctn);
                allPanels.forEach(el =>  el.style.maxHeight = 0);
                if(item.closest(aPnl)) item.closest(aPnl).style.maxHeight = '9999px';
                item.hasAttribute(aAttr) ?
                    (close(), item.removeAttribute(aAttr), panel.style.maxHeight = 0) :
                    (close(), item.setAttribute(aAttr,''), panel.style.maxHeight = panel.scrollHeight + 'px');
            };
        });
    });
}