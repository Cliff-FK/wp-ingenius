<?php

/**
 * Various html elements
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * HTIMO UTILE
 */
class DUPX_U_Html
{
    /** @var int */
    protected static $uniqueId = 0;

    /**
     * Initialize css for html elements
     *
     * @return void
     */
    public static function css()
    {
        self::lightBoxCss();
        self::inputPasswordToggleCss();
        self::checkboxSwitchCss();
    }

    /**
     * Initialize js for html elements
     *
     * @return void
     */
    public static function js()
    {
        self::lightBoxJs();
        self::inputPasswordToggleJs();
    }

    /**
     * Get unique id
     *
     * @return string
     */
    private static function getUniqueId()
    {
        self::$uniqueId++;
        return 'dup-html-id-' . self::$uniqueId . '-' . str_replace('.', '-', (string) microtime(true));
    }

    /**
     * This function returns a string with all the html attributes with this format key = "value" key2 = "value2"
     * an esc_attr is executed automatically
     *
     * @param array<string, mixed> $attrs attributes
     *
     * @return string
     */
    public static function arrayAttrToHtml($attrs)
    {
        $sttrsStr = [];
        foreach ($attrs as $key => $val) {
            $sttrsStr[] = $key . '="' . DUPX_U::esc_attr($val) . '"';
        }
        return implode(' ', $sttrsStr);
    }

    /**
     * Get header main
     *
     * @param string $htmlTitle Title
     *
     * @return void
     */
    public static function getHeaderMain($htmlTitle)
    {
        ?>
        <div id="header-main-wrapper" >
            <div class="dupx-logfile-link">
                <?php DUPX_View_Funcs::installerLogLink(); ?>
            </div>
            <div class="hdr-main">
                <?php echo $htmlTitle; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get light box
     *
     * @param string $linkLabelHtml    the link label
     * @param string $titleContent     the title of the light box
     * @param string $htmlContent      the content of the light box
     * @param bool   $echo             if true the light box is echoed
     * @param string $htmlAfterContent html after content
     *
     * @return string
     */
    public static function getLigthBox($linkLabelHtml, $titleContent, $htmlContent, $echo = true, $htmlAfterContent = '')
    {
        ob_start();
        $id = self::getUniqueId();
        ?>
        <span class="link-style dup-ligthbox-link" data-dup-ligthbox="<?php echo $id; ?>" ><?php echo $linkLabelHtml; ?></span>
        <div id="<?php echo $id; ?>" class="dub-ligthbox-content close">
            <div class="wrapper" >
                <h2 class="title" ><?php echo htmlspecialchars($titleContent); ?></h2>
                <div class="content" ><?php echo $htmlContent; ?></div><?php echo $htmlAfterContent; ?>
                <button class="close-button" title="Close" ><i class="fa fa-2x fa-times"></i></button>
            </div>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return ob_get_clean();
        }
    }

    /**
     * Light box from file content
     *
     * @param string $linkLabelHtml the link label
     * @param string $titleContent  the title of the light box
     * @param string $path          the path of the file
     * @param bool   $echo          if true the light box is echoed
     *
     * @return string
     */
    public static function getLightBoxFileContent($linkLabelHtml, $titleContent, $path, $echo = true)
    {
        if (file_exists($path) && (($fileContent = file_get_contents($path)) !== false)) {
            $lightBoxContent =
                '<div class="row-cols-1">' .
                    '<div class="col col-1">' .
                        '<pre>' . DUPX_U::esc_html($fileContent) . '</pre>' .
                    '</div>' .
                '</div>';
        } else {
            $lightBoxContent = '<p>File not found.</b>';
        }
        return DUPX_U_Html::getLigthBox($linkLabelHtml, $titleContent, $lightBoxContent, $echo);
    }

    /**
     * getLightBoxIframe
     *
     * @param string $linkLabelHtml        the link label
     * @param string $titleContent         the title of the light box
     * @param string $url                  the url of the iframe
     * @param bool   $autoUpdate           if true the iframe is auto updated
     * @param bool   $enableTargetDownload Download button enabled
     * @param bool   $echo                 if true the light box is echoed
     *
     * @return string
     */
    public static function getLightBoxIframe(
        $linkLabelHtml,
        $titleContent,
        $url,
        $autoUpdate = false,
        $enableTargetDownload = false,
        $echo = true
    ) {
        $classes      = ['dup-lightbox-iframe'];
        $afterContent = '<div class="tool-box">';
        if ($autoUpdate) {
            //$classes[]    = 'auto-update';
            $afterContent .= '<button class="button toggle-auto-update disabled" title="Enable auto reload" ><i class="fa fa-2x fa-redo-alt"></i></button>';
        }
        if ($enableTargetDownload) {
            $path = parse_url($url, PHP_URL_PATH);
            if (!empty($path)) {
                $urlPath  = parse_url($url, PHP_URL_PATH);
                $fileName = basename($urlPath);
            } else {
                $fileName = parse_url($url, PHP_URL_HOST);
            }
            $afterContent .= '<a target="_blank" class="button download-button" title="Download" download="' . DUPX_U::esc_attr($fileName) . '" href="' . DUPX_U::esc_attr($url) . '"><i class="fa fa-2x fa-download"></i></a>';
        }
        $afterContent .= '</div>';

        $lightBoxContent = '<iframe class="' . implode(' ', $classes) . '" data-iframe-url="' . DUPX_U::esc_attr($url) . '"></iframe> ';
        return DUPX_U_Html::getLigthBox($linkLabelHtml, $titleContent, $lightBoxContent, $echo, $afterContent);
    }

    /**
     * Light box CSS
     *
     * @return void
     */
    protected static function lightBoxCss()
    {
        ?>
        <style>
            .dup-ligthbox-link {
                text-decoration: underline;
                cursor: pointer;
            }
            .dub-ligthbox-content {
                position: fixed;
                top: 0;
                left: 0;
                width: calc(100vw - 120px);
                height: 100vh;
                background-color: #FFFFFF;
                background-color: rgba(255,255,255,0.95);
                z-index: 999999;
                overflow: hidden;
                margin: 0 60px;
            }
            .dub-ligthbox-content.close {
                width: 0;
                height: 0;
            }
            .dub-ligthbox-content.open {
                width: calc(100vw - 120px);
                height: 100vh;
            }

            .dub-ligthbox-content > .wrapper {
                width: calc(100vw - 120px);
                height: 100vh;
            }

            .dub-ligthbox-content > .wrapper > .title {
                height: 40px;
                line-height: 40px;
                margin: 0;
                padding: 0 15px;
            }

            .dub-ligthbox-content > .wrapper > .content {
                margin: 0 15px 15px;
                border: 1px solid darkgray;
                padding: 15px;
                height: calc(100% - 15px - 40px);
                box-sizing: border-box;
            }

            .dub-ligthbox-content > .wrapper > .tool-box {
                position: absolute;
                top: 0px;
                left: 200px;
            }

            .dub-ligthbox-content .tool-box .button {
                display: inline-block;
                background: transparent;
                border: 0 none;
                padding: 5px;
                margin: 0 10px;
                height: 40px;
                line-height: 40px;
                box-sizing: border-box;
                color: #000;
                cursor: pointer;
            }

            .dub-ligthbox-content .tool-box .button.disabled {
                color: #BABABA;
            }

            .dub-ligthbox-content > .wrapper > .close-button {
                position: absolute;
                top: 0px;
                right: 23px;
                background: transparent;
                border: 0 none;
                padding: 5px;
                margin: 0;
                height: 40px;
                line-height: 40px;
                box-sizing: border-box;
                color: #000;
                cursor: pointer;
            }


            .dub-ligthbox-content .row-cols-2,
            .dub-ligthbox-content .row-cols-1 {
                height: 100%;
            }

            .dub-ligthbox-content .row-cols-1 .col {
                width: 100%;
                box-sizing: border-box;
                float: left;
                height: 100%;
                overflow: auto;
            }

            .dub-ligthbox-content .row-cols-2 .col {
                width: 50%;
                box-sizing: border-box;
                float: left;
                border-right: 1px solid black;
                height: 100%;
                overflow: auto;
            }

            .dub-ligthbox-content .row-cols-2 .col-2 {
                padding-left: 15px;
            }

            .dub-ligthbox-content .dup-lightbox-iframe {
                border: 0 none;
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
            }

        </style>
        <?php
    }

    /**
     * Lightbox js
     *
     * @return void
     */
    protected static function lightBoxJs()
    {
        ?>
        <script>
            $(document).ready(function ()
            {
                var currentLightboxOpen = null;

                var toggleLightbox = function (target) {
                    if (target.hasClass('close')) {
                        target.removeClass('close').addClass('open').trigger('dup-lightbox-open');
                        currentLightboxOpen = target;
                    } else {
                        target.removeClass('open').addClass('close').trigger('dup-lightbox-close');
                        currentLightboxOpen = null;
                    }
                };

                function dupIframeLoaded(iframe, content) {
                    if (iframe.hasClass('auto-update')) {
                        setTimeout(function () {
                            dupIframeReload(iframe, content);
                        }, 3000);
                    }
                }
                ;

                function dupIframeReload(iframe, content) {
                    if (content.hasClass('open')) {
                        iframe[0].contentDocument.location.reload(true);
                        iframe.ready(function () {
                            dupIframeLoaded(iframe, content);
                        });
                    }
                }
                ;

                $('.dup-lightbox-iframe').on("load", function () {
                    $(this).contents().find('body').css({
                        'background': '#FFFFFF',
                        'color': "#000000"
                    });
                    this.contentWindow.scrollBy(0, 100000);
                });

                $('.dub-ligthbox-content').each(function () {
                    var content = $(this).detach().appendTo('body');
                    var iframe = content.find('.dup-lightbox-iframe');
                    if (iframe.length) {
                        content.
                                bind('dup-lightbox-open', function () {
                                    iframe.attr('src', iframe.data('iframe-url')).ready(function () {
                                        dupIframeLoaded(iframe, content);
                                    });
                                }).
                                bind('dup-lightbox-close', function () {
                                    iframe.attr('src', '');
                                });
                    }
                });

                $('[data-dup-ligthbox]').off().click(function (event) {
                    event.stopPropagation();
                    var target = $('#' + $(this).data('dup-ligthbox'));
                    toggleLightbox(target);
                });

                $('.dub-ligthbox-content .toggle-auto-update').off().click(function (event) {
                    event.stopPropagation();
                    var elem = $(this);
                    var content = elem.closest('.dub-ligthbox-content');
                    var iframe = content.find('.dup-lightbox-iframe');
                    if (iframe.hasClass('auto-update')) {
                        iframe.removeClass('auto-update');
                        elem.addClass('disabled').attr('title', 'Enable auto reload');
                    } else {
                        iframe.addClass('auto-update');
                        elem.removeClass('disabled').attr('title', 'Disable auto reload');
                        dupIframeReload(iframe, content);
                    }
                });

                $('.dub-ligthbox-content .close-button').off().click(function (event) {
                    event.stopPropagation();
                    toggleLightbox($(this).closest('.dub-ligthbox-content'));
                });

                $(window).keydown(function (event) {
                    if (event.key === 'Escape' && currentLightboxOpen !== null) {
                        currentLightboxOpen.find('.close-button').trigger('click');
                    }
                });
            });
        </script>
        <?php
    }

    /**
     *
     * @param string          $htmlContent html content
     * @param string|string[] $classes     additional classes on main div
     * @param int             $step        pixel foreach more step
     * @param string          $id          id on main div
     * @param bool            $echo        echo or return
     *
     * @return string|void
     */
    public static function getMoreContent($htmlContent, $classes = [], $step = 200, $id = '', $echo = true)
    {
        $inputCls    = filter_var($classes, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FORCE_ARRAY);
        $mainClasses = array_merge(['more-content'], $inputCls);
        $atStep      = max(100, $step);
        $idAttr      = empty($id) ? '' : 'id="' . $id . '" ';
        ob_start();
        ?>
        <div <?php echo $idAttr; ?>class="<?php echo implode(' ', $mainClasses); ?>" data-more-step="<?php echo $atStep; ?>" style="max-height: <?php echo $atStep; ?>px">
            <div class="more-wrapper" ><?php echo $htmlContent; ?></div>
            <p class="more-faq-link align-">
                Please search the <a href="https://duplicator.com/knowledge-base-article-categories/troubleshooting" target="_blank">Online Technical FAQs</a>
                for solutions to these issues.
            </p>
            <button class="more-button" type="button">[show more]</button>
            <button class="all-button" type="button" >[show all]</button>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
        } else {
            return ob_get_clean();
        }
    }

    /**
     * Input password with toggle button
     *
     * @param string                    $name          name of input
     * @param string                    $id            id of input
     * @param string[]                  $classes       classes of input
     * @param array<string, string|int> $attrs         attributes of input
     * @param bool                      $pwdSimulation if true emulate password type
     *
     * @return void
     */
    public static function inputPasswordToggle($name, $id = '', $classes = [], $attrs = [], $pwdSimulation = false)
    {
        if (!is_array($attrs)) {
            $attrs = [];
        }
        if (!is_array($classes)) {
            if (empty($classes)) {
                $classes = [];
            } else {
                $classes = [$classes];
            }
        }
        $idAttr    = empty($id) ? '_id_' . $name : $id;
        $classes[] = 'input-password-group input-postfix-btn-group';

        if ($pwdSimulation) {
            $attrs['type']  = 'text';
            $attrs['class'] = 'pwd-simulation text-security-disc';
        } else {
            $attrs['type'] = 'password';
        }
        $attrs['name'] = $name;
        $attrs['id']   = $idAttr;
        $attrsHtml     = [];

        foreach ($attrs as $atName => $atValue) {
            $attrsHtml[] = $atName . '="' . DUPX_U::esc_attr($atValue) . '"';
        }
        ?>
        <span class="<?php echo implode(' ', $classes); ?>" >
            <input <?php echo implode(' ', $attrsHtml); ?> />
            <button type="button" class="postfix" title="Show the password"><i class="fas fa-eye fa-xs"></i></button>
        </span>
        <?php
    }

    /**
     * Input password toggle css
     *
     * @return void
     */
    protected static function inputPasswordToggleCss()
    {
        ?>
        <style>
            .input-password-group {
                position: relative;
            }

            .input-password-group button i {
                line-height: 30px;
                margin: 0;
                padding: 0;
            }

            .input-password-group .parsley-errors-list {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                left: 10px;
            }

        </style>
        <?php
    }

    /**
     * Input password toggle js
     *
     * @return void
     */
    protected static function inputPasswordToggleJs()
    {
        ?>
        <script>
            $(document).ready(function () {
                $('.input-password-group').each(function () {
                    var group = $(this);
                    var pwdInput = group.find('input');
                    var pwdLock = group.find('button');

                    pwdLock.click(function () {
                        if (pwdInput.attr('type') === 'password' || pwdInput.hasClass('text-security-disc')) {
                            if (pwdInput.hasClass('pwd-simulation')) {
                                pwdInput.removeClass('text-security-disc');
                            } else {
                                pwdInput.attr('type', 'text');
                            }
                            pwdInput.attr('title', 'Hide the password');
                            pwdLock.find('i')
                                    .removeClass('fa-eye')
                                    .addClass('fa-eye-slash');
                        } else {
                            if (pwdInput.hasClass('pwd-simulation')) {
                                pwdInput.addClass('text-security-disc');
                            } else {
                                pwdInput.attr('type', 'password');
                            }
                            pwdInput.attr('title', 'Show the password');
                            pwdLock.find('i')
                                    .removeClass('fa-eye-slash')
                                    .addClass('fa-eye');
                        }

                    });
                });

            });
        </script>
        <?php
    }

    /**
     * CheckboxSwitch
     *
     * @param array<string, string|int> $inputAttrs  input attributes
     * @param array<string, string|int> $switchAttrs switch attributes
     *
     * @return void
     */
    public static function checkboxSwitch($inputAttrs = [], $switchAttrs = [])
    {
        $inputAttrs['type'] = 'checkbox';
        if (!isset($switchAttrs['class'])) {
            $switchAttrs['class'] = [];
        }
        $switchAttrs['class'] = implode(' ', array_merge(['checkbox-switch'], (array) $switchAttrs['class']));
        ?>
        <span  <?php echo self::arrayAttrToHtml($switchAttrs); ?> >
            <input <?php echo self::arrayAttrToHtml($inputAttrs); ?> >
            <span class="slider"></span>
        </span>
        <?php
    }

     /**
     * Checkbox Switch Css
     *
     * @return void
     */
    protected static function checkboxSwitchCss()
    {
        ?>
        <style>
            .checkbox-switch {
                position: relative;
                display: inline-block;
                width: 48px;
                height: 26px;
                box-sizing: border-box;
                bottom: 0;
                border-radius: 4px;
            }

            .checkbox-switch input {
                position: absolute;
                opacity: 0;
                width: 100%;
                height: 100%;
                z-index: 90;
                top: 0;
                left: 0;
            }

            .checkbox-switch .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                -webkit-transition: .4s;
                transition: .4s;
                border-radius: 4px;
                overflow: hidden;
            }

            .checkbox-switch .slider:before {
                position: absolute;
                content: "";
                height: 20px;
                width: 20px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                -webkit-transition: .4s;
                transition: .4s;
                border-radius: 4px;
                overflow: hidden;
            }

            .checkbox-switch input:checked + .slider {
                background-color: #2196F3;
            }

            .checkbox-switch input:focus + .slider {
                box-shadow: 0 0 1px #2196F3;
            }
            
            .checkbox-switch input:disabled + .slider {
                background-color: #ddd;
            }
            
            .checkbox-switch input:disabled:checked + .slider {
                background-color: #cbe1f2;
            }

            .checkbox-switch input:disabled:focus + .slider {
                box-shadow: 0 0 1px #cbe1f2;
            }
            
            .checkbox-switch input:disabled + .slider:before {
                background-color: #ccc;
            }

            .checkbox-switch input:checked + .slider:before {
                -webkit-transform: translateX(20px);
                -ms-transform: translateX(20px);
                transform: translateX(20px);
            }

            /* Rounded sliders */
            .checkbox-switch.round .slider {
                border-radius: 30px;
            }

            .checkbox-switch.round .slider:before {
                border-radius: 50%;
            } 
        </style>
        <?php
    }
}
