/*------------------------------------*/
// INIT SKEW FUNCTION
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_skew() {
    document.querySelectorAll("[class*='u-skew']").forEach(el => {
        const wrapWords = node => {
            if (node.nodeType === Node.TEXT_NODE && node.parentNode.nodeName !== 'X') {
                const words = node.textContent.split(/(\s+)/);
                const fragment = document.createDocumentFragment();
                words.forEach(word => {
                    if (word.trim()) {
                        const i = document.createElement('x');
                        i.textContent = word;
                        fragment.appendChild(i);
                    } else {
                        fragment.appendChild(document.createTextNode(word));
                    }
                });
                node.replaceWith(fragment);
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                Array.from(node.childNodes).forEach(wrapWords);
            }
        };

        Array.from(el.childNodes).forEach(wrapWords);
    });
}