<?php

/**
 * Search and reaplace manager
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Libs\Snap\SnapIO;

class DUPX_TemplateItem
{
    /** @var string */
    protected $name;
    /** @var string */
    protected $mainFolder;
    /** @var null|DUPX_TemplateItem */
    protected $parent;

    /**
     * Class contructor
     *
     * @param string             $name       Template name
     * @param string             $mainFolder Main folder
     * @param ?DUPX_TemplateItem $parent     Parent template
     */
    public function __construct($name, $mainFolder, $parent = null)
    {
        if (empty($name)) {
            throw new Exception('The name of template can\'t be empty');
        }

        if (!is_dir($mainFolder) || !is_readable($mainFolder)) {
            throw new Exception('The main main folder doesn\'t exist');
        }

        if (!is_null($parent) && !$parent instanceof self) {
            throw new Exception('the parent must be a instance of ' . self::class);
        }

        $this->name       = $name;
        $this->mainFolder = SnapIO::safePathUntrailingslashit($mainFolder);
        $this->parent     = $parent;
    }

    /**
     * Render template
     *
     * @param string               $fileTpl Template file is a relative path from root template folder
     * @param array<string, mixed> $args    Array key / val where key is the var name in template
     * @param bool                 $echo    If false return template in string
     *
     * @return string
     */
    public function render($fileTpl, $args = [], $echo = true)
    {
        ob_start();
        if (($renderFile = $this->getFileTemplate($fileTpl)) !== false) {
            foreach ($args as $var => $value) {
                ${$var} = $value;
            }
            require($renderFile);
        } else {
            echo '<p>FILE TPL NOT FOUND: ' . $fileTpl . '</p>';
        }
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return ob_get_clean();
        }
    }

    /**
     * Acctept html of php extensions. if the file have unknown extension automatic add the php extension
     *
     * @param string $fileTpl File template
     *
     * @return boolean|string return false if don\'t find the template file
     */
    protected function getFileTemplate($fileTpl)
    {
        $fileExtension = strtolower(pathinfo($fileTpl, PATHINFO_EXTENSION));
        switch ($fileExtension) {
            case 'php':
            case 'html':
                $fileName = $fileTpl;

                break;
            default:
                $fileName = $fileTpl . '.php';
        }
        $fullPath = $this->mainFolder . '/' . $fileName;
        if (file_exists($fullPath)) {
            return $fullPath;
        } elseif (!is_null($this->parent)) {
            return $this->parent->getFileTemplate($fileName);
        } else {
            return false;
        }
    }
}
