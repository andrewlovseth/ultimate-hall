<?php
/**
 * Inaugural Year Helper Functions
 *
 * Helper functions for handling inaugural year designation in class titles
 */

// Define inaugural year constant
define('INAUGURAL_YEAR', '2004');


/**
 * Get class title with inaugural designation if applicable
 *
 * Returns the formatted class title, adding "Inaugural" prefix for the inaugural year.
 *
 * @param string $year The year to format (e.g., '2004', '2023')
 * @return string Formatted class title (e.g., "Inaugural Class of 2004" or "Class of 2023")
 *
 * @example
 * echo inaugural_get_class_title('2004'); // Output: "Inaugural Class of 2004"
 * echo inaugural_get_class_title('2023'); // Output: "Class of 2023"
 */
function inaugural_get_class_title($year) {
    if ($year === INAUGURAL_YEAR) {
        return 'Inaugural Class of ' . esc_html($year);
    }
    return 'Class of ' . esc_html($year);
}


/**
 * Get class prefix (inaugural designation only)
 *
 * Returns "Inaugural " (with trailing space) for the inaugural year, empty string otherwise.
 * Useful when you want to insert the inaugural text inline.
 *
 * @param string $year The year to check
 * @return string "Inaugural " or empty string
 *
 * @example
 * <h2><?php echo inaugural_get_class_prefix($year); ?>Class of <?php echo $year; ?></h2>
 * // Output: <h2>Inaugural Class of 2004</h2> or <h2>Class of 2023</h2>
 */
function inaugural_get_class_prefix($year) {
    return ($year === INAUGURAL_YEAR) ? 'Inaugural ' : '';
}
