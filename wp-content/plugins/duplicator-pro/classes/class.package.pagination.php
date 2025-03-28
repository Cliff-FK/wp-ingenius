<?php

use Duplicator\Views\UserUIOptions;

/**
 * List table class
 */
class DUP_PRO_Package_Pagination extends WP_List_Table
{
    /**
     * Get num items per page
     *
     * @return int
     */
    public static function get_per_page()
    {
        return UserUIOptions::getInstance()->get(UserUIOptions::VAL_PACKAGES_PER_PAGE);
    }

    /**
     * Display pagination
     *
     * @param int $total_items Total items
     * @param int $per_page    Per page
     *
     * @return void
     */
    public function display_pagination($total_items, $per_page = 10)
    {
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);
        $which = 'top';
        $this->pagination($which);
    }
}
