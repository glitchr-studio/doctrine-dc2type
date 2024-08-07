<?php

if (!function_exists('file_replace')) {
    function file_replace(array|string $search, array|string $replace, array|string $fname, int &$count = null): void
    {
        if (!is_array($fname)) {
            $fname = [$fname];
        }
        
        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "File '$f' does not exist.\n";
                continue;
            }

            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "Could not read the file '$f'.\n";
                continue;
            }

            $newContents = str_replace($search, $replace, $fileContents, $replaceCount);
            if (file_put_contents($f, $newContents, LOCK_EX) === false) {
                echo "Could not write to the file '$f'.\n";
            } else {
                $count += $replaceCount;
            }
        }
    }
}

if (!function_exists('file_remove_line')) {
    function file_remove_line(string $search, array|string $fname): void
    {
        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "File '$f' does not exist.\n";
                continue;
            }

            $fileContents = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($fileContents === false) {
                echo "Could not read the file '$f'.\n";
                continue;
            }

            $newContents = array_filter($fileContents, function ($line) use ($search) {
                return strpos($line, $search) === false;
            });

            if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "Could not write to the file '$f'.\n";
            }
        }
    }
}

if (!function_exists('file_prepend')) {
    function file_prepend(string $search, string $block, array|string $fname): void
    {
        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "File '$f' does not exist.\n";
                continue;
            }

            // Read existing file contents
            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "Could not read the file '$f'.\n";
                continue;
            }

            // Remove PHP tags from existing contents
            $fileContents = preg_replace('/<\?php\s*(.*?)\s*\?>/s', '$1', $fileContents);
            $fileContents = preg_replace('/<\?(?!php|\s)/', '', $fileContents);
            $fileContents = preg_replace('/\?>/', '', $fileContents);

            $lines = explode(PHP_EOL, $fileContents);
            $newContents = [];
            $found = false;

            foreach ($lines as $line) {
                if (strpos($line, $search) !== false && !$found) {
                    $newContents[] = $block; // Add the block before the matched line
                    $found = true;
                }
                $newContents[] = $line;
            }

            // Write the updated content to the file
            if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "Could not write to the file '$f'.\n";
            } else if (!$found) {
                echo "No matching line found in '$f'.\n";
            }
        }
    }
}

if (!function_exists('file_append_block')) {
    function file_append_block(string $search, string $block, array|string $fname): void
    {
        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "File '$f' does not exist.\n";
                continue;
            }

            // Read and preprocess the file content
            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "Could not read the file '$f'.\n";
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
                    $newContents[] = $block;
                    $found = true;
                }
            }

            // Write modified content to file
            if ($found && file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "Could not write to the file '$f'.\n";
            } elseif (!$found) {
                echo "No matching line found in '$f'.\n";
            }
        }
    }
}

if (!function_exists('file_append_method')) {
    function file_append_method(string $functionName, string $block, array|string $fname): void
    {
        if (!is_array($fname)) {
            $fname = [$fname];
        }

        foreach ($fname as $f) {
            if (!file_exists($f)) {
                echo "File '$f' does not exist.\n";
                continue;
            }

            // Read existing file contents
            $fileContents = file_get_contents($f);
            if ($fileContents === false) {
                echo "Could not read the file '$f'.\n";
                continue;
            }

            // Remove PHP tags from existing contents
            $fileContents = preg_replace('/<\?php\s*(.*?)\s*\?>/s', '$1', $fileContents);
            $fileContents = preg_replace('/<\?(?!php|\s)/', '', $fileContents);
            $fileContents = preg_replace('/\?>/', '', $fileContents);

            $lines = explode(PHP_EOL, $fileContents);
            $newContents = [];
            $insideFunction = false;
            $functionStarted = false;

            foreach ($lines as $line) {
                if (preg_match('/function\s+' . preg_quote($functionName, '/') . '\s*\(/', $line)) {
                    $insideFunction = true;
                    $functionStarted = true;
                }

                if ($insideFunction && strpos(trim($line), '}') !== false) {
                    $insideFunction = false;
                    // Add block of code right after the closing bracket
                    if ($functionStarted) {
                        $newContents[] = $line;
                        $newContents[] = $block;
                        $functionStarted = false;
                        continue;
                    }
                }

                $newContents[] = $line;
            }

            // Write the updated content to the file
            if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
                echo "Could not write to the file '$f'.\n";
            }
        }
    }
}