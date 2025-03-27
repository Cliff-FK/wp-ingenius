/*------------------------------------*/
// INIT SWIPPER PLUGIN
// https://swiperjs.com/swiper-api
/*------------------------------------*/
import * as vars from './vars.js';
import Swiper from '../_libs/swiper-bundle.min.js' 

export function wbd_swiper() {
    if(typeof Swiper === 'function'){
    if (window.AllSliders) { window.AllSliders.forEach(sld => sld.destroy()); } // Détruire les instances existantes
    window.AllSliders=[]; // Re-init les futures intances
    document.querySelectorAll("[swiper-type]:not(.swiper-initialized, [swiper-type='disable'])").forEach((slider, i) => {
        var navPagi = slider.querySelector(":scope .swiper-pagination") || slider.querySelector(":scope ~ .swiper-pagination");
        var navNavi = slider.querySelector(":scope .swiper-navigation") || slider.querySelector(":scope ~ .swiper-navigation");
        var nbSlide = Math.floor(slider.querySelectorAll(':scope .swiper-slide').length);
        var prevEl = navNavi?.querySelector(":scope button.prev:not(.sub-prev)") ?? null;
        var nextEl = navNavi?.querySelector(":scope button.next:not(.sub-next)") ?? null;
        var type = slider.getAttribute('swiper-type');
        var midIndx = Math.floor(nbSlide / 2);

        const baseArgs = {
            loop: true,
            speed: 600,
            spaceBetween: 15,   
            autoplay: { delay: 4000 },
            effect: "slide",
            pagination: {
                el: navPagi,
                type: 'bullets',
                clickable: true
            },
            navigation: {
                prevEl: prevEl,
                nextEl: nextEl,
            },
        }

        if(type=="default") {
            var args= {
                ...baseArgs,
                autoplay: false,
                slidesPerView: 1,
            }
        }

        if(type=="sld_posts") {
            var args= {
                ...baseArgs,
                slidesPerView: 3,
                breakpoints: {
                    980: { slidesPerView: 3, },
                    767: { slidesPerView: 2, loop: true, },
                    0 : { slidesPerView: 1.382, loop: false, }
                },
            }
        }

        if(type=="wall") {
            var args= {
                loop: true,
                speed: 15000,
                autoplay: true,
                spaceBetween: 10,
                allowTouchMove: false,
                slidesPerView: 'auto',
                effect: "slide",
            }
        }

        if(type=="hero") {
            var args= {
                effect: "fade",
                autoplay: true,
                parallax: true,
                speed: 900,
                loop: true,
            }
            slider.querySelectorAll(':scope *>*').forEach(el => {
                el.setAttribute('data-swiper-parallax-opacity', '0');
                el.setAttribute('data-swiper-parallax', '-90');
            });
        }

        if(type=="single-bvv") {
            var args= {
                ...baseArgs,
                slidesPerView: 1,
                initialSlide: midIndx,
                watchSlidesProgress	: true,
                autoplay: false,
                loop: false,
            }
        }

        if(type=="thumbs-bvv") {
            var args= {
                ...baseArgs,
                slidesPerView: 5,
                loop: false,
                autoplay: false,
                centeredSlides: true,
                slideToClickedSlide: true,
                initialSlide: midIndx,
                breakpoints: {
                    1240: { slidesPerView: 5, },
                    1024: { slidesPerView: 4, },
                    767: { slidesPerView: 3, },
                    0 : { slidesPerView: 2, }
                },
            }
        }

        if(type=="thumbs-gdp") {
            var args= {
                ...baseArgs,
                slidesPerView: 'auto',
                loop: false,
                autoplay: false,
                spaceBetween: 0,
                centeredSlides: true,
                slideToClickedSlide: true,
                initialSlide: midIndx,
                // un initialSlide dans un parent hidden doit être réinitialisé
                observeParents: true,
                observer: true,
                on: {
                    observerUpdate: function() {
                        if (this.el.closest('.wbd-tab-init')) {
                            this.slideTo(midIndx, 0, false);
                        }
                    },
                },
            }
        }

        if(type=="gdp") {   
            var args= {
                loop: false,
                autoplay: false,
                slidesPerView: 'auto',
                touchReleaseOnEdges: true,
                initialSlide: midIndx,
                effect: "creative",
                 // un initialSlide dans un parent hidden doit être réinitialisé
                 observeParents: true,
                 observer: true,
                 on: {
                    observerUpdate: function() {
                        if (this.el.closest('.wbd-tab-init')) {
                            this.slideTo(midIndx, 0, false);
                        }
                    },
                },
                creativeEffect: {
                  prev: {
                      translate: [-20, 0, 0],
                      opacity: 0,
                  },
                  next: {
                      translate: [20, 0, 0],
                      opacity: 0,
                  },
                },
            }
        }   

        setTimeout(() => { // Timeout pour laisser le temps aux sliders de charger
            window.AllSliders[i]= new Swiper( slider, args); // Init des sliders en fonctions des args (en référence au type de slider)
            if(['thumbs', 'thumbs-gdp'].includes(type)) {
                window.AllSliders[i-1].controller.control = window.AllSliders[i];
                window.AllSliders[i].controller.control = window.AllSliders[i-1];
            }
        }, 10);
        
    }) // end instance Slider(s)
}} // end wbd_swiper()