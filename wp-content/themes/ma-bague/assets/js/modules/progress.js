/*------------------------------------*/
// INIT PROGRESS BAR SCROLL
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_progressBar() {
    const theBar = document.querySelector(".g-scrollbar_inner");
    if (theBar && vars.bodyDOM) {
        const winScroll = document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        if (height > 0) { const scrolled = (winScroll / height) * 100; theBar.style.width = `${scrolled}%`; }
    }
}
