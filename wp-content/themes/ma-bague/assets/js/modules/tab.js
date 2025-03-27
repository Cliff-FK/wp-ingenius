/*------------------------------------*/
// INIT TABS COMPONENTS
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_tab() {
    let aCls='active',aCtn='wbd-tab-init',aRef='wbd-tab-ref',aId='wbd-tab-id';
    document.querySelectorAll(`[${aCtn}]`).forEach(ctn => {
        const allBtn = ctn.querySelectorAll(`:scope [${aRef}]:not(:scope [${aCtn}] [${aRef}])`);
        const allPanel = ctn.querySelectorAll(`:scope [${aId}]:not(:scope [${aCtn}] [${aId}])`);
        const initIndex = Math.min(parseInt(ctn.getAttribute(aCtn)) || 0, allBtn.length-1); // Index de démarrage

        // Fonction pour activer une tab et désactiver les autres
        function activateTab(tabRef, tabPanel) {
            [...tabRef.parentElement.children, ...tabPanel.parentElement.children]
                .forEach(el => el.classList.remove(aCls));
            setTimeout(() => tabPanel.classList.add(aCls), 10); // Ajoute la classe active au panel
            [...tabPanel.parentElement.children].forEach(p => p.style.display = 'none');
            tabPanel.style.display = 'flex'; // Affiche le panel
            tabRef.classList.add(aCls); // Ajoute la classe active au bouton
        }

        // Active la tab correspondante à initIndex, ou la première par défaut
        if (allBtn[initIndex] && allPanel[initIndex]) {
            activateTab(allBtn[initIndex], allPanel[initIndex]);
        }

        // Gestion des clics pour changer de tab
        ctn.addEventListener('click', (e) => {
            let tabRef = e.target.closest(`[${aRef}]`);
            if (tabRef && ctn.contains(tabRef)) { e.stopPropagation(); // important de stopPropagation si tabs nested
                let tabId = tabRef.getAttribute(aRef); // Récupère l'attribut de référence du bouton
                let tabPanel = ctn.querySelector(`[${aId}="${tabId}"]`); // Sélectionne le panel correspondant
                if (tabPanel) activateTab(tabRef, tabPanel);
            }
        });
    });
}