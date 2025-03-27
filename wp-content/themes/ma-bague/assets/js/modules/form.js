/*------------------------------------*/
import * as vars from './vars.js';

/*------------------------------------*/
 // ON SUBMIT UX EVENT(S)
/*------------------------------------*/
export function wbd_form_submit() {
    document.addEventListener('submit', e => {
        if (!e.target.matches('.c-frm')) return;
        e.target.classList.add('o-valided');
        if (!e.target.checkValidity()) {
            e.preventDefault(); e.stopPropagation();
        } else {
            const btn = e.target.querySelector('[type="submit"]');
            if (btn) {
                btn.textContent = btn.value = "Patientez...";
                btn.classList.add('disabled');
                btn.disabled = true;
            }
        }
    }, true);
}

/*------------------------------------*/
// ADD FOCUS CLASS ON INPUTS FOCUS
/*------------------------------------*/
export function wbd_form_focus() {
    const selector = 'input:not([type="radio"]):not([type="checkbox"]), select, textarea';
    const toggleFocus = elm => elm.parentNode.classList.toggle('js-focus', !!elm.value);
    document.addEventListener('focusin', e => { if (e.target.matches(selector))e.target.parentNode.classList.add('js-focus') });
    document.addEventListener('focusout', e => { if (e.target.matches(selector)) toggleFocus(e.target) });
    document.addEventListener('input', e => { if (e.target.matches(selector)) toggleFocus(e.target) });
    document.addEventListener('animationstart', ({ target, animationName }) =>
        target.matches(selector) && (animationName === 'onAutoFillStart'
            ? target.parentNode.classList.add('js-focus')
            : animationName === 'onAutoFillCancel' && toggleFocus(target))
    );
}

/*------------------------------------*/
// ACTIVE BTN CLASS
/*------------------------------------*/
export function wbd_form_active_btn() {
    document.addEventListener('click', e => {
        const btn = e.target.closest('[type="radio"], [type="checkbox"]'); if (!btn) return;
        if (btn.type === 'checkbox') {
            btn.parentElement.classList.toggle('active', btn.checked); // .classList.toggle('active') selon l’état coché
        }
        else if (btn.type === 'radio') {
            const allSiblings = document.querySelectorAll(`[name="${btn.name}"]`); // On retire 'active' et on décoche tous les radios frères
            allSiblings.forEach(elm => { elm.parentElement.classList.remove('active'); elm.checked = false; });
            btn.parentElement.classList.toggle('active'); // On toggle l’état du radio cliqué
            btn.checked = btn.checked === true ? false : true; // Même logique bizarre : si c’était coché, on décoche, sinon on coche
        }
    });
}

/*------------------------------------*/
// DYNAMIC REQUIRED ON CHECKBOX
/*------------------------------------*/
export function wbd_form_dynamic_required() {
    document.addEventListener('change', e => {
        const el = e.target.closest('[type="checkbox"][required], [type="radio"][required]'); if (!el) return;
        const group = document.querySelectorAll(`[name="${el.name}"]`); // On récupère tous les inputs du même name
        const isChecked = [...group].some(cb => cb.checked); // Vérifie s’il y en a au moins un de coché
        group.forEach(cb => { cb.required = !isChecked; }); // Si un est coché, on désactive le required sur tout le groupe
    });
}

/*------------------------------------*/
// HONEYPOT(S) INPUT(S)
/*------------------------------------*/
export function wbd_form_honeypot() {
    setInterval(() => {
        document.querySelectorAll('[name="mkiS"]').forEach(el => el.value = 0 );
    }, 4000);
}
