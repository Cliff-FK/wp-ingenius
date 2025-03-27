/*------------------------------------*/
// INIT MODAL COMPONENTS
/*------------------------------------*/
import * as vars from './vars.js';
import Swiper from '../_libs/swiper-bundle.min.js' 
export function wbd_modal() {
    if (vars.frontDOM) {

        document.querySelectorAll('.c-mdl[wbd-mdl-id]').forEach(mdl => {
            document.body.appendChild(mdl);
          });

        const hasEventListeners = (element) => {
            if (!element.parentNode) return false; // Pas de parent, donc pas d'événements
            const clone = element.cloneNode();
            element.parentNode.insertBefore(clone, element);
            const hasListeners = element.outerHTML !== clone.outerHTML; // Vérifie les différences dues aux événements
            clone.remove();
            return hasListeners;
        };

        const create_modal = (items, initialIndex = 0) => {
            if (!items || items.length === 0) return; // Rien à créer si aucun élément
            const mdl = document.createElement('aside');
            mdl.className = 'o-mdl'; mdl.setAttribute('role', 'dialog'); mdl.setAttribute('tabindex', '0');

            const mdlCtn = document.createElement('div'); mdlCtn.className = 'o-ctn';
            const mdlClose = document.createElement('button'); mdlClose.className = 'o-mdl_close'; mdlClose.textContent = '✕';
            const mdlBody = document.createElement('div'); mdlBody.className = 'o-mdl_body'; mdlBody.setAttribute('data-lenis-prevent', '');
            const swiperContainer = document.createElement('div'); swiperContainer.className = 'swiper';
            const swiperWrapper = document.createElement('div'); swiperWrapper.className = 'swiper-wrapper';

            swiperContainer.appendChild(swiperWrapper); mdlBody.appendChild(swiperContainer);

            const navigationWrapper = document.createElement('div'); navigationWrapper.className = 'swiper-navigation';
            const nextButton = document.createElement('button'); nextButton.classList.add('next', 'o-deficon-arrow-small-right');
            const prevButton = document.createElement('button'); prevButton.classList.add('prev', 'o-deficon-arrow-small-left');
            navigationWrapper.append(prevButton, nextButton); mdlCtn.append(navigationWrapper, mdlClose, mdlBody); mdl.appendChild(mdlCtn);

            items.forEach((el) => {
                const realCls = el.className, originalParent = el.parentNode, originalNextSibling = el.nextSibling;
                el.dataset.originalParent = originalParent ? originalParent.tagName : null; // Stocke le parent d'origine
                el.dataset.originalNextSibling = originalNextSibling ? originalNextSibling.className : null; // Stocke le sibling d'origine
                el.dataset.realCls = realCls; // Sauvegarde de la classe initiale

                const shouldMove = hasEventListeners(el) || [...el.querySelectorAll('*')].some(hasEventListeners); // Test des événements
                const itemToAppend = shouldMove ? el : el.cloneNode(true); // Clone ou déplace selon les événements
                itemToAppend.className = 'o-mdl_item';

                const swiperSlide = document.createElement('div'); swiperSlide.className = 'swiper-slide'; swiperSlide.appendChild(itemToAppend);
                swiperWrapper.appendChild(swiperSlide);
                if (shouldMove) el.dataset.isMoved = true; // Marque les éléments déplacés
            });

            setTimeout(() => {
                vars.bodyDOM.classList.add('mdl-open');
                mdl.classList.add('active');
                mdl.querySelectorAll('[wbd-src]').forEach(el =>  el.src = el.getAttribute('wbd-src') );
            }, 10); // Active la modale après délai

            vars.bodyDOM.insertAdjacentElement('afterbegin', mdl); // Ajoute la modale au DOM

            setTimeout(() => {
                new Swiper('.o-mdl .swiper', {
                    initialSlide: initialIndex, slidesPerView: 'auto', loop: items.length > 1, spaceBetween: 15,
                    navigation: { prevEl: prevButton, nextEl: nextButton }, keyboard: { enabled: true, onlyInViewport: true },
                    mousewheel: { sensitivity: 1, forceToAxis: true, invert: true }
                });
            }, 0);
        };
        
        if(!window.mdlEventInit){ document.addEventListener('click', (e) => {
            if (!vars.bodyDOM.classList.contains('mdl-open') && e.target.closest('[wbd-mdl-target]')) {
                let mdlID = e.target.closest('[wbd-mdl-target]').getAttribute('wbd-mdl-target');
                let mdlElm = document.querySelectorAll('[wbd-mdl-id="' + mdlID + '"]:not([wbd-mdl-id*="http"])');
                let clickedIndex = Array.from(mdlElm).indexOf(e.target.closest('[wbd-mdl-id="' + mdlID + '"]'));
                create_modal(mdlElm, clickedIndex); e.preventDefault(); // Création de la modale pour cible wbd-mdl-target
            }

            if (e.target.closest('a')?.href.includes('modal=ID')) {
                e.preventDefault();
                let mdlID = new URL(e.target.closest('a').href).searchParams.get('modal');
                if (mdlID) create_modal(document.querySelectorAll('[wbd-mdl-id="' + mdlID + '"]')); // Création pour modal=ID
            }

            if ((e.target.closest('a')?.href.includes('modal=iframe')) || e.target.closest('[wbd-mdl-target]')?.getAttribute('wbd-mdl-target')?.includes('http')) {
                e.preventDefault(); const iframe = document.createElement('iframe'); // Création de l'iframe pour les vidéos
                iframe.allowFullscreen = true;
                iframe.src = e.target.closest('[wbd-mdl-target]')
                    ? e.target.closest('[wbd-mdl-target]').getAttribute('wbd-mdl-target')
                    : e.target.closest('a').getAttribute('href');       
                create_modal([iframe]);
            }

            if (vars.bodyDOM.classList.contains('mdl-open') && !e.target.closest('.o-mdl_item, .o-mdl :is(.prev,.next)')) {
                const topModal = document.querySelector('.o-mdl:first-of-type'); // Sélectionne la première modale en haut du DOM
                if (topModal) {
                    const transiTime = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--wbd-transi-time')) * 1000; // Temps de transition
                    topModal.classList.remove('active'); // Supprime uniquement la modale en haut du DOM
                    setTimeout(() => {
                        topModal.remove();
                        if (!document.querySelector('.o-mdl')) vars.bodyDOM.classList.remove('mdl-open'); // Retire le fond noir si plus aucune modale n'est visible
                    }, transiTime);
            
                    // Réintègre les éléments déplacés de la modale
                    topModal.querySelectorAll('.o-mdl_item').forEach(modalItem => {
                        const originalParent = modalItem.dataset.originalParent ? document.querySelector(modalItem.dataset.originalParent) : null;
                        const originalNextSibling = modalItem.dataset.originalNextSibling ? document.querySelector(modalItem.dataset.originalNextSibling) : null;
                        modalItem.className = modalItem.dataset.realCls; // Réinitialise les classes originales
            
                        if (modalItem.dataset.isMoved && originalParent) {
                            if (originalNextSibling && originalParent.contains(originalNextSibling)) {
                                originalParent.insertBefore(modalItem, originalNextSibling); // Réinsère à l'emplacement d'origine
                            } else {
                                originalParent.appendChild(modalItem); // Ajoute en fin si sibling manquant
                            }
                        }
                    });
                }
            }
            
        })};
        window.mdlEventInit = true; // Important, ne déclencher l'event que une fois, même si on rappelle la fonction pour X raisons
    } // if (vars.frontDOM)
// end wbd_modal()
}