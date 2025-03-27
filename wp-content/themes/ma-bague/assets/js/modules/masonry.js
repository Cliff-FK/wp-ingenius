/*------------------------------------*/
// INIT SORT TABLE
/*------------------------------------*/
import * as vars from './vars.js';

export function wbd_masonry() {
    const observer = new MutationObserver(() =>
        document.querySelectorAll('[wbd-masonry]:not(.masonry-initialized)').forEach(ctn => {
          new Masonry(ctn, { itemSelector: ':scope' });
          ctn.classList.add('masonry-initialized');
        })
      );
    observer.observe(document.body, {
        childList: true, subtree: true
    });
}
