/*------------------------------------*\
* INIT FUNCTION GET VIEWPORT WIDTH
* https://gomakethings.com/breakpoint-conditional-javascript-in-vanilla-js
\*------------------------------------*/
function setViewportWidth() {
    window.viewportWidth = window.innerWidth ||
    document.documentElement.clientWidth;
} // end function

/*------------------------------------*\
* PRELOAD LINKS ON MOUSEOVER
* https://wpspeedmatters.com/quicklink-vs-instant-page-vs-flying-pages/
* https://css-tricks.com/prerender-on-hover/
\*------------------------------------*/
function initPreloadPage(){
    var links = document.querySelectorAll('a');
    [].forEach.call(links, function(link) { 
        link.addEventListener("mouseenter", function() { 
            var newPreLoadLink = document.createElement("link");
            newPreLoadLink.rel = "prefetch";
            newPreLoadLink.href = link.href;
            document.head.appendChild(newPreLoadLink);
        })
    });
}

/*------------------------------------*\
* UPDATE COUNTER RESULT S&F PLUGIN 
\*------------------------------------*/
function upSFproProps1(){
  if(typeof searchAndFilter === 'object'){
      $(".searchandfilter").searchAndFilter();
      var val = document.querySelector('.c-query-counter_val');
      var txt = document.querySelector('.b-list-posts_counter');
      if(val && txt){ txt.innerHTML = val.value;
          $(document).on("sf:ajaxfinish", ".searchandfilter", function(event){
              if (event.target.classList.contains("searchandfilter")) {
                  var val = document.querySelector('.c-query-counter_val');
                  var txt = document.querySelector('.b-list-posts_counter');
                  txt.innerHTML = val.value;
              }
          });
      }
  }} // end function

  
/*------------------------------------*\
* UPDATE COUNTER RESULT S&F PLUGIN 
\*------------------------------------*/
function upSFproProps2(){
    if(typeof searchAndFilter === 'object'){
      jQuery(document).on("sf:ajaxfinish", ".searchandfilter", function(){
          initLocoScroll()
      });
  }} // end function


/*------------------------------------*\  
* INIT SMOOTH SCROLL PLUGIN
* https://github.com/studio-freight/lenis
\*------------------------------------*/
function initLenisScroll() {
if(typeof Lenis === 'function' && html){
    const lenis = new Lenis()  
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf) }
    requestAnimationFrame(raf)       
}} // end function

/*------------------------------------*\  
* INIT PARALAX PLUGIN
* https://dixonandmoe.com/rellax
\*------------------------------------*/
function initParalax() {
if(typeof Rellax === 'function'){
    if(document.querySelector(".prlx")) new Rellax('.prlx', { center: true, });         
    if(document.querySelector(".prlx-h")) new Rellax('.prlx', { center: true, horizontal: true, verticalScrollAxis: "x" });         
}} // end function

/*------------------------------------*\  
* INIT AOS LIB
* https://github.com/michalsnik/aos
\*------------------------------------*/
function initAOS() {
    if(typeof AOS === 'object'){
        setTimeout(function(){ AOS.init({ offset: 60 }); }, 0);
    }} // end function
    

// Réglages des args de swiper pour des bullets custom.
// Il faut un attr wbd-bullet-html sur les .swiper-slide avec du code html dedans
const args_4 = {
    pagination: {
        el: pagination,
        clickable: true,
        renderBullet: function (index, className) {
            var customHtml = this.slides[index].getAttribute('wbd-bullet-html');
            return '<button data-index="'+index+'" class="'+className+'">'+customHtml+'</button>';
        },
    },
}

/*------------------------------------*\  
* INIT ALPINE JS COMPONENTS
* https://alpinejs.dev/start-here
\*------------------------------------*/
function initAlpine() {
    if(typeof Alpine === 'object' && Alpine === 'undefined' ){
    Alpine.start()
}}