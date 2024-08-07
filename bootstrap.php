<?php

// Function to track if a change has already been made
if (!function_exists('file_line_replace')) {
    function file_has_changes(array|string $fname, string $checkString): bool
    {
        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                continue;
            }

            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                continue;
            }

            if (strpos($fileContents, $checkString) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('file_line_replace')) {
    /**
     * Replace occurrences of $search with $replace in the specified file(s).
     * Adds a comment indicating the replacement and a new line below the replaced line.
     */
    function file_line_replace(array|string $search, array|string $replace, array|string $fname, int &$count = null): void
    {
        $date = date('Y-m-d H:i:s');
        $author = basename(dirname(dirname(__FILE__)))."/".basename(dirname(__FILE__));
        if (file_has_changes($fname, implode('|', (array)$search))) {
            echo "      Replacement already applied.\n";
            return;
        }

        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "      File '$f' does not exist.\n";
                continue;
            }

            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "      Could not read the file '$f'.\n";
                continue;
            }

            // # This code has been automatically updated on $date by $author
            $newContents = [];
            $lines = explode(PHP_EOL, $fileContents);
            foreach ($lines as $line) {
                if (strpos($line, $search) !== false) {
                    $newContents[] = "// Original line replaced on `$date` by `$author`";
                    $newContents[] = "// $line"; // Comment out the original line
                    $newContents[] = str_replace($search, $replace, $line); // Add the new line
                } else {
                    $newContents[] = $line;
                }
            }
            $newContents[] = "######### End of automatic update"; // End of automatic update comment
            // ######### End of automatic update

            if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "      Could not write to the file '$f'.\n";
            } else {
                $count += substr_count($fileContents, $search); // Count occurrences of $search
            }
        }
    }
}

if (!function_exists('file_line_remove')) {
    /**
     * Comment out lines containing $search in the specified file(s) and add a new line below each commented line.
     */
    function file_line_remove(string $search, array|string $fname): void
    {
        $date = date('Y-m-d H:i:s');
        $author = basename(dirname(dirname(__FILE__)))."/".basename(dirname(__FILE__));
        if (file_has_changes($fname, $search)) {
            echo "      Line removal already applied.\n";
            return;
        }

        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "      File '$f' does not exist.\n";
                continue;
            }

            $fileContents = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($fileContents === false) {
                echo "      Could not read the file '$f'.\n";
                continue;
            }

            // # This code has been automatically updated on $date by $author
            $newContents = [];
            foreach ($fileContents as $line) {
                if (strpos($line, $search) !== false) {
                    $newContents[] = "// Original line commented out on `$date` by `$author`";
                    $newContents[] = "// $line"; // Comment out the line
                    $newContents[] = "// New line added after commenting out";
                } else {
                    $newContents[] = $line;
                }
            }
            // ######### End of automatic update

            if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "      Could not write to the file '$f'.\n";
            }
        }
    }
}

if (!function_exists('file_prepend')) {
    /**
     * Prepend $block before the first line containing $search in the specified file(s).
     */
    function file_prepend(string $search, string $block, array|string $fname): void
    {
        $date = date('Y-m-d H:i:s');
        $author = basename(dirname(dirname(__FILE__)))."/".basename(dirname(__FILE__));
        if (file_has_changes($fname, $block)) {
            echo "      Prepend block already applied.\n";
            return;
        }

        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "      File '$f' does not exist.\n";
                continue;
            }

            // Read existing file contents
            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "      Could not read the file '$f'.\n";
                continue;
            }

            // # This code has been automatically updated on $date by $author
            // Remove PHP tags from existing contents
            $fileContents = preg_replace('/<\?php\s*(.*?)\s*\?>/s', '$1', $fileContents);
            $fileContents = preg_replace('/<\?(?!php|\s)/', '', $fileContents);
            $fileContents = preg_replace('/\?>/', '', $fileContents);

            $lines = explode(PHP_EOL, $fileContents);
            $newContents = [];
            $found = false;

            foreach ($lines as $line) {
                if (strpos($line, $search) !== false && !$found) {
                    $newContents[] = "# This code has been automatically updated on `$date` by `$author`";
                    $newContents[] = $block; // Add the block before the matched line
                    $found = true;
                }
                $newContents[] = $line;
            }
            $newContents[] = "######### End of automatic update"; // End of automatic update comment
            // ######### End of automatic update

            // Write the updated content to the file
            if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "      Could not write to the file '$f'.\n";
            } else if (!$found) {
                echo "      No matching line found in '$f'.\n";
            }
        }
    }
}

if (!function_exists('file_append_block')) {
    /**
     * Append $block after the first line containing $search in the specified file(s).
     */
    function file_append_block(string $search, string $block, array|string $fname): void
    {
        $date = date('Y-m-d H:i:s');
        $author = basename(dirname(dirname(__FILE__)))."/".basename(dirname(__FILE__));
        if (file_has_changes($fname, $block)) {
            echo "      Append block already applied.\n";
            return;
        }

        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "      File '$f' does not exist.\n";
                continue;
            }

            // Read and preprocess the file content
            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "      Could not read the file '$f'.\n";
                continue;
            }

            // Remove PHP tags
            $fileContents = preg_replace('/<\?php\s*(.*?)\s*\?>/s', '$1', $fileContents);
            $fileContents = preg_replace('/<\?(?!php|\s)/', '', $fileContents);
            $fileContents = preg_replace('/\?>/', '', $fileContents);

            $lines = explode(PHP_EOL, $fileContents);
            $newContents = [];
            $found = false;

            foreach ($lines as $line) {
                $newContents[] = $line;
                if (strpos($line, $search) !== false) {
                    $newContents[] = "# This code has been automatically updated on `$date` by `$author`";
                    $newContents[] = $block;
                    $newContents[] = "######### End of automatic update"; // End of automatic update comment
                    $found = true;
                }
            }
            // ######### End of automatic update

            // Write modified content to file
            if ($found && file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "      Could not write to the file '$f'.\n";
            } elseif (!$found) {
                echo "      No matching line found in '$f'.\n";
            }
        }
    }
}

if (!function_exists('file_append_method')) {
    /**
     * Append $block after the specified method in the file(s).
     */
    function file_append_method(string $method, string $block, array|string $fname): void
    {
        $date = date('Y-m-d H:i:s');
        $author = basename(dirname(dirname(__FILE__)))."/".basename(dirname(__FILE__));
        if (file_has_changes($fname, $block)) {
            echo "      Append block already applied.\n";
            return;
        }

        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "      File '$f' does not exist.\n";
                continue;
            }

            // Read and preprocess the file content
            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "      Could not read the file '$f'.\n";
                continue;
            }

            // # This code has been automatically updated on $date by $author
            // Remove PHP tags
            $fileContents = preg_replace('/<\?php\s*(.*?)\s*\?>/s', '$1', $fileContents);
            $fileContents = preg_replace('/<\?(?!php|\s)/', '', $fileContents);
            $fileContents = preg_replace('/\?>/', '', $fileContents);

            $lines = explode(PHP_EOL, $fileContents);
            $newContents = [];
            $found = false;

            foreach ($lines as $line) {
                $newContents[] = $line;
                if (strpos($line, $method) !== false) {
                    $newContents[] = "# This code has been automatically updated on `$date` by `$author`";
                    $newContents[] = $block;
                    $newContents[] = "######### End of automatic update"; // End of automatic update comment
                    $found = true;
                }
            }
            // ######### End of automatic update

            // Write modified content to file
            if ($found && file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "      Could not write to the file '$f'.\n";
            } elseif (!$found) {
                echo "      No matching method found in '$f'.\n";
            }
        }
    }
}
